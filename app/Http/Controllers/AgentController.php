<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Vk\SyncVkUserAction;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\VkUser;
use App\Scenarios\ScenarioFactory;
use App\Services\Vk\VkApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
{
    public function list()
    {
        $agents = Agent::all();
        $vkUsers = VkUser::all()->keyBy(fn (VkUser $u) => $u->getAgentId());

        // Суммарная статистика за 7 дней по каждому агенту (один запрос с GROUP BY agent_id)
        $stats = DB::table('vk_post_stats')
            ->join('vk_posts', 'vk_posts.id', '=', 'vk_post_stats.vk_post_id')
            ->join('offers', 'offers.id', '=', 'vk_posts.offer_id')
            ->where('vk_post_stats.datetime', '>=', now()->subDays(7))
            ->select(
                'offers.agent_id',
                DB::raw('SUM(vk_post_stats.views) as views'),
                DB::raw('SUM(vk_post_stats.reposts) as reposts'),
                DB::raw('SUM(vk_post_stats.likes) as likes'),
                DB::raw('SUM(vk_post_stats.comments) as comments'),
            )
            ->groupBy('offers.agent_id')
            ->get()
            ->keyBy('agent_id');

        return view('agents-list', compact('agents', 'vkUsers', 'stats'));
    }

    public function create()
    {
        $agent = new Agent;
        $vkUser = new VkUser;

        return view('agent-form', compact('agent', 'vkUser'));
    }

    public function store(StoreAgentRequest $request)
    {
        $phone = preg_replace('/[^0-9]/', '', $request->input('phone'));

        $agent = Agent::create([
            'name' => $request->input('name'),
            'phone' => $phone,
        ]);

        Log::channel('job')->info('Агент создан', ['agent_id' => $agent->id, 'name' => $agent->name]);

        viewJson(true, ['Агент создан'], '/agents/edit/'.$agent->id);
    }

    public function edit(int $id)
    {
        $agent = Agent::findOrFail($id);
        $vkUser = $agent->vkUser ?? new VkUser;

        // Статистика по офферам и сценариям — берём последний снимок для каждого поста
        $latestStats = DB::table('vk_post_stats as ps')
            ->join('vk_posts as vp', 'vp.id', '=', 'ps.vk_post_id')
            ->join('publication_tasks as pt', 'pt.id', '=', 'vp.task_id')
            ->join('publications as p', 'p.id', '=', 'pt.publication_id')
            ->join('offers as o', 'o.id', '=', 'vp.offer_id')
            ->where('o.agent_id', $id)
            ->where('ps.datetime', function ($query) {
                $query->selectRaw('MAX(ps2.datetime)')
                    ->from('vk_post_stats as ps2')
                    ->whereColumn('ps2.vk_post_id', 'ps.vk_post_id');
            })
            ->select(
                'o.code',
                'p.scenario',
                'ps.views',
                'ps.reposts',
                'ps.likes',
                'ps.comments',
            )
            ->get();

        // Группировать по офферу
        $statsByOffer = [];
        foreach ($latestStats as $row) {
            $code = $row->code;
            if (! isset($statsByOffer[$code])) {
                $statsByOffer[$code] = [
                    'total' => ['views' => 0, 'reposts' => 0, 'likes' => 0, 'comments' => 0],
                    'scenarios' => [],
                ];
            }
            $statsByOffer[$code]['total']['views'] += $row->views;
            $statsByOffer[$code]['total']['reposts'] += $row->reposts;
            $statsByOffer[$code]['total']['likes'] += $row->likes;
            $statsByOffer[$code]['total']['comments'] += $row->comments;

            $scenario = $row->scenario;
            if (! isset($statsByOffer[$code]['scenarios'][$scenario])) {
                $statsByOffer[$code]['scenarios'][$scenario] = [
                    'views' => 0, 'reposts' => 0, 'likes' => 0, 'comments' => 0,
                ];
            }
            $statsByOffer[$code]['scenarios'][$scenario]['views'] += $row->views;
            $statsByOffer[$code]['scenarios'][$scenario]['reposts'] += $row->reposts;
            $statsByOffer[$code]['scenarios'][$scenario]['likes'] += $row->likes;
            $statsByOffer[$code]['scenarios'][$scenario]['comments'] += $row->comments;
        }

        // Порядок сценариев из ScenarioFactory
        $scenarioOrder = collect(app(ScenarioFactory::class)->list())
            ->map(fn ($class) => (new $class)->type()->value)
            ->toArray();

        return view('agent-form', compact('agent', 'vkUser', 'statsByOffer', 'scenarioOrder'));
    }

    public function save(UpdateAgentRequest $request, int $id)
    {
        $agent = Agent::findOrFail($id);
        $phone = preg_replace('/[^0-9]/', '', $request->input('phone'));

        $agent->update([
            'name' => $request->input('name'),
            'phone' => $phone,
        ]);

        $vkUser = $agent->vkUser;
        if ($vkUser && $vkUser->exists()) {
            app(SyncVkUserAction::class)->execute($vkUser);
        }

        Log::channel('job')->info('Агент обновлен', ['agent_id' => $id, 'name' => $agent->name]);

        viewJson(true, ['Агент обновлён'], '/agents/edit/'.$id);
    }

    public function delete(Request $request, int $id)
    {
        $agent = Agent::findOrFail($id);
        $password = $request->input('password', '');
        $expectedPassword = config('app.rudenko_password', '');

        if ($expectedPassword === '' || $password !== $expectedPassword) {
            viewJson(false, ['Неверный пароль']);
        }

        $agent->delete();

        Log::channel('job')->info('Агент удален', ['agent_id' => $id]);

        viewJson(true, ['Агент удалён'], '/agents');
    }

    public function changeToken(int $id)
    {
        $agent = Agent::findOrFail($id);

        return view('agent-token', compact('agent'));
    }

    public function updateToken(Request $request, int $id)
    {
        $agent = Agent::findOrFail($id);

        $postLink = trim($request->input('link', ''));
        if ($postLink === '') {
            viewJson(false, ['Укажите ссылку после авторизации']);
        }

        $parsed = parse_url($postLink);
        $fragment = $parsed['fragment'] ?? '';
        if ($fragment === '') {
            viewJson(false, ['Указана некорректная ссылка: отсутствуют параметры авторизации']);
        }

        parse_str($fragment, $query);

        if (empty($query['access_token']) || empty($query['user_id'])) {
            viewJson(false, ['Указана некорректная ссылка: не найден access_token или user_id']);
        }

        $vkUser = $agent->vkUser ?? new VkUser;
        $vkUser->agent_id = $agent->id;
        $vkUser->vk_user_id = (string) $query['user_id'];
        $vkUser->vk_token = $query['access_token'];
        $vkUser->email = $query['email'] ?? null;
        $vkUser->is_token_available = true;
        $vkUser->save();

        Log::channel('job')->info('Токен агента обновлён', [
            'agent_id' => $id,
            'vk_user_id' => $vkUser->vk_user_id,
        ]);

        viewJson(true, ['Токен обновлён'], '/agents/edit/'.$id);
    }

    public function getTokenPermissions(int $id)
    {
        $agent = Agent::findOrFail($id);
        $vkUser = $agent->vkUser;

        if (! $vkUser || ! $vkUser->exists()) {
            viewJson(false, ['У агента не сохранён VK-пользователь']);
        }

        $token = $vkUser->getToken();
        if ($token === '') {
            viewJson(false, ['Токен не найден']);
        }

        try {
            $vkApi = app(VkApiService::class);
            $vkApi->setToken($token);
            $response = $vkApi->getClient()->account()->getAppPermissions($token);

            Log::channel('job')->info('Получены права токена агента', [
                'agent_id' => $id,
                'vk_user_id' => $vkUser->vk_user_id,
            ]);

            viewJson(true, [json_encode($response, JSON_UNESCAPED_UNICODE)]);
        } catch (\Throwable $e) {
            Log::channel('vk')->error('Ошибка получения прав токена: '.$e->getMessage(), [
                'agent_id' => $id,
                'vk_user_id' => $vkUser->vk_user_id,
            ]);

            viewJson(false, ['Ошибка получения прав токена']);
        }
    }
}
