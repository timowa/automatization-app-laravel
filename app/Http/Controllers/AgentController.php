<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Vk\SyncVkUserAction;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Jobs\CreateVkPostJob;
use App\Models\Agent;
use App\Models\VkUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
{
    public function list()
    {
        $agents = Agent::all();
        $vkUsers = VkUser::all()->keyBy(fn (VkUser $u) => $u->getAgentId());

        return view('agents-list', compact('agents', 'vkUsers'));
    }

    public function create()
    {
        $agent = new Agent();
        $vkUser = new VkUser();

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

        viewJson(true, ['Агент создан'], '/agents/edit/' . $agent->id);
    }

    public function edit(int $id)
    {
        $agent = Agent::findOrFail($id);
        $vkUser = $agent->vkUser ?? new VkUser();

        return view('agent-form', compact('agent', 'vkUser'));
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

        viewJson(true, ['Агент обновлён'], '/agents/edit/' . $id);
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

        $vkUser = $agent->vkUser ?? new VkUser();
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

        viewJson(true, ['Токен обновлён'], '/agents/edit/' . $id);
    }
}
