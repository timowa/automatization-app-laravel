<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GetAgentPublicationStatsAction;
use App\Actions\Vk\SyncVkUserAction;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Http\Requests\UpdateAgentSettingsRequest;
use App\Models\Agent;
use App\Models\Setting;
use App\Models\VkUser;
use App\Services\Vk\VkApiService;
use Illuminate\Http\JsonResponse;
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

        $settings = Setting::all()->keyBy('agent_id');

        return view('agents-list', compact('agents', 'vkUsers', 'stats', 'settings'));
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

        Setting::create([
            'agent_id' => $agent->id,
            'wish_happy_birthday' => false,
        ]);

        Log::channel('job')->info('Агент создан', ['agent_id' => $agent->id, 'name' => $agent->name]);

        viewJson(true, ['Агент создан'], '/agents/edit/'.$agent->id);
    }

    public function edit(int $id, GetAgentPublicationStatsAction $publicationStats)
    {
        $agent = Agent::findOrFail($id);
        $vkUser = $agent->vkUser ?? new VkUser;
        $stats = $publicationStats->execute($agent->id);
        $statsByOffer = $stats['statsByOffer'];
        $scenarioOrder = $stats['scenarioOrder'];

        return view('agent-form', compact('agent', 'vkUser', 'statsByOffer', 'scenarioOrder'));
    }

    public function updateSettings(UpdateAgentSettingsRequest $request, int $id): JsonResponse
    {
        $agent = Agent::findOrFail($id);
        $wishHappyBirthday = $request->boolean('wish_happy_birthday');

        Setting::updateOrCreate(
            ['agent_id' => $agent->id],
            ['wish_happy_birthday' => $wishHappyBirthday]
        );

        Log::channel('job')->info('Настройки агента обновлены', [
            'agent_id' => $id,
            'wish_happy_birthday' => $wishHappyBirthday,
        ]);

        return response()->json([
            'success' => true,
            'messages' => ['Настройки сохранены'],
            'redirect' => null,
            'wish_happy_birthday' => $wishHappyBirthday,
        ]);
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
