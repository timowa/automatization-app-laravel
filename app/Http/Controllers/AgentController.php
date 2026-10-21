<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GetAgentPublicationStatsAction;
use App\Actions\Vk\ListAgentVkFriendsAction;
use App\Actions\Vk\StoreAgentWelcomeCardAction;
use App\Actions\Vk\SyncVkUserAction;
use App\Enums\Vk\Deactivated;
use App\Enums\Vk\OccupationType;
use App\Enums\Vk\Relation;
use App\Enums\Vk\Sex;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Http\Requests\UpdateAgentSettingsRequest;
use App\Http\Requests\UploadAgentWelcomeCardRequest;
use App\Models\Agent;
use App\Models\Setting;
use App\Models\VkUser;
use App\Services\Vk\VkApiService;
use Carbon\CarbonInterface;
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

        $settings = Setting::query()->with('welcomeCard')->get()->keyBy('agent_id');

        $friendCounts = DB::table('agents_vk_friends')
            ->select('agent_id', DB::raw('COUNT(*) as friends_count'))
            ->groupBy('agent_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->agent_id => (int) $row->friends_count]);

        $birthdayTextsByAgent = $agents->mapWithKeys(function (Agent $agent) use ($settings): array {
            $setting = $settings[$agent->id] ?? null;
            $card = $setting?->welcomeCard;
            $hasCard = $setting !== null && $setting->hasWelcomeCard();

            return [
                (string) $agent->id => [
                    'male_enabled' => (bool) ($setting->wish_happy_birthday_male ?? false),
                    'female_enabled' => (bool) ($setting->wish_happy_birthday_female ?? false),
                    'male' => (string) ($setting->birthday_wish_male_text ?? ''),
                    'female' => (string) ($setting->birthday_wish_female_text ?? ''),
                    'welcome_enabled' => (bool) ($setting->friend_request_welcome_enabled ?? false),
                    'welcome_text' => (string) ($setting->friend_request_welcome_text ?? ''),
                    'has_card' => $hasCard,
                    'card_url' => $hasCard && $card !== null ? $card->previewUrl() : '',
                ],
            ];
        });

        return view('agents-list', compact('agents', 'vkUsers', 'stats', 'settings', 'birthdayTextsByAgent', 'friendCounts'));
    }

    public function friends(Request $request, int $id, ListAgentVkFriendsAction $listFriends)
    {
        $agent = Agent::findOrFail($id);
        $vkUser = $agent->vkUser ?? new VkUser;
        $filters = $this->friendFilters($request);
        $friends = $listFriends->execute($agent->id, $filters);
        $cities = $listFriends->cities($agent->id);
        $countries = $listFriends->countries($agent->id);
        $hometowns = $listFriends->hometowns($agent->id);
        $syncedAt = $vkUser->friends_synced_at;
        $friendsSyncedAtLabel = $syncedAt instanceof CarbonInterface
            ? $syncedAt->timezone((string) config('app.timezone'))->format('d.m.Y H:i')
            : '—';

        return view('agent-friends', compact(
            'agent',
            'vkUser',
            'friends',
            'filters',
            'cities',
            'countries',
            'hometowns',
            'friendsSyncedAtLabel',
        ));
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
            'wish_happy_birthday_male' => false,
            'wish_happy_birthday_female' => false,
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
        $wishMale = $request->boolean('wish_happy_birthday_male');
        $wishFemale = $request->boolean('wish_happy_birthday_female');
        $maleText = $this->nullableTrimmedText($request->input('birthday_wish_male_text'));
        $femaleText = $this->nullableTrimmedText($request->input('birthday_wish_female_text'));
        $welcomeEnabled = $request->boolean('friend_request_welcome_enabled');
        $welcomeText = $this->nullableTrimmedText($request->input('friend_request_welcome_text'));

        $setting = Setting::query()->with('welcomeCard')->firstOrNew(['agent_id' => $agent->id]);
        $setting->wish_happy_birthday_male = $wishMale;
        $setting->wish_happy_birthday_female = $wishFemale;
        $setting->birthday_wish_male_text = $maleText;
        $setting->birthday_wish_female_text = $femaleText;
        $setting->friend_request_welcome_enabled = $welcomeEnabled;
        $setting->friend_request_welcome_text = $welcomeText;
        $setting->save();

        if ($request->boolean('remove_welcome_card')) {
            app(StoreAgentWelcomeCardAction::class)->deleteForSetting($setting);
        }

        $setting->refresh()->load('welcomeCard');

        Log::channel('job')->info('Настройки агента обновлены', [
            'agent_id' => $id,
            'wish_happy_birthday_male' => $wishMale,
            'wish_happy_birthday_female' => $wishFemale,
            'friend_request_welcome_enabled' => $welcomeEnabled,
        ]);

        $hasCard = $setting->hasWelcomeCard();
        $card = $setting->welcomeCard;

        return response()->json([
            'success' => true,
            'messages' => ['Настройки сохранены'],
            'redirect' => null,
            'wish_happy_birthday_male' => $wishMale,
            'wish_happy_birthday_female' => $wishFemale,
            'birthday_wish_male_text' => $maleText ?? '',
            'birthday_wish_female_text' => $femaleText ?? '',
            'has_male_text' => $maleText !== null,
            'has_female_text' => $femaleText !== null,
            'friend_request_welcome_enabled' => $welcomeEnabled,
            'friend_request_welcome_text' => $welcomeText ?? '',
            'has_welcome_text' => $welcomeText !== null,
            'has_card' => $hasCard,
            'card_url' => $hasCard && $card !== null ? $card->previewUrl() : '',
        ]);
    }

    public function uploadWelcomeCard(
        UploadAgentWelcomeCardRequest $request,
        int $id,
        VkApiService $vkApi,
        StoreAgentWelcomeCardAction $storeWelcomeCard,
    ): JsonResponse {
        $agent = Agent::with('vkUser')->findOrFail($id);
        $vkUser = $agent->vkUser;
        $paths = null;

        Log::channel('vk-images')->info('Цикл загрузки визитки: старт', [
            'agent_id' => $agent->id,
            'vk_user_id' => $vkUser?->vk_user_id,
        ]);

        if ($vkUser === null || $vkUser->getToken() === '' || ! $vkUser->is_token_available) {
            Log::channel('vk-images')->warning('Цикл загрузки визитки: нет рабочего токена', [
                'agent_id' => $agent->id,
            ]);

            return response()->json([
                'success' => false,
                'messages' => ['Нет рабочего токена ВКонтакте для загрузки визитки'],
            ], 422);
        }

        $file = $request->file('card');
        if ($file === null) {
            Log::channel('vk-images')->warning('Цикл загрузки визитки: файл не получен', [
                'agent_id' => $agent->id,
            ]);

            return response()->json([
                'success' => false,
                'messages' => ['Файл визитки не получен'],
            ], 422);
        }

        Log::channel('vk-images')->info('Цикл загрузки визитки: исходный файл', [
            'agent_id' => $agent->id,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        try {
            $paths = $storeWelcomeCard->writeLocalFiles($agent, $file);

            $vkApi->setToken($vkUser->getToken());
            Log::channel('vk-images')->info('Цикл загрузки визитки: отправка во ВК', [
                'agent_id' => $agent->id,
                'vk_user_id' => $vkUser->vk_user_id,
                'absolute_path' => $paths['absolute_original'],
            ]);

            $mediaId = $vkApi->uploadMessagesPhoto($paths['absolute_original']);
            $card = $storeWelcomeCard->persist($agent, $paths, $mediaId);
        } catch (\Throwable $e) {
            $storeWelcomeCard->cleanupLocalFiles($paths);

            Log::channel('job')->warning('Ошибка загрузки визитки автоприветствия', [
                'agent_id' => $agent->id,
            ]);
            Log::channel('vk-images')->error('Цикл загрузки визитки: ошибка', [
                'agent_id' => $agent->id,
                'vk_user_id' => $vkUser->vk_user_id,
                'path' => $paths['path'] ?? null,
                'thumb_path' => $paths['thumb_path'] ?? null,
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            Log::channel('vk')->error($e->getMessage(), [
                'agent_id' => $agent->id,
                'context' => 'upload_welcome_card',
                'exception' => $e::class,
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'messages' => ['Не удалось загрузить визитку во ВКонтакте. Попробуйте другое изображение.'],
            ], 422);
        }

        Log::channel('job')->info('Визитка автоприветствия загружена', [
            'agent_id' => $agent->id,
            'media_id' => $card->media_id,
            'card_id' => $card->id,
        ]);
        Log::channel('vk-images')->info('Цикл загрузки визитки: завершён успешно', [
            'agent_id' => $agent->id,
            'vk_user_id' => $vkUser->vk_user_id,
            'card_id' => $card->id,
            'path' => $card->path,
            'thumb_path' => $card->thumb_path,
            'media_id' => $card->media_id,
        ]);

        return response()->json([
            'success' => true,
            'messages' => ['Визитка загружена'],
            'has_card' => true,
            'card_url' => $card->previewUrl(),
            'friend_request_card_id' => $card->id,
            'friend_request_card_media_id' => $card->media_id,
        ]);
    }

    /**
     * @return array{
     *     q: string,
     *     sex: int|null,
     *     city: list<string>,
     *     country: string|null,
     *     relation: int|null,
     *     page: string|null,
     *     closed: int|null,
     *     home_town: string|null,
     *     occupation: string|null
     * }
     */
    private function friendFilters(Request $request): array
    {
        $sex = $request->input('sex');
        $relation = $request->input('relation');
        $page = $request->input('page');
        $closed = $request->input('closed');
        $occupation = $request->input('occupation');

        return [
            'q' => mb_substr(trim((string) $request->input('q', '')), 0, 100),
            'sex' => is_numeric($sex) && Sex::tryFrom((int) $sex) !== null ? (int) $sex : null,
            'city' => $this->cityFilters($request->input('city')),
            'country' => $this->nullableFilterString($request->input('country')),
            'relation' => is_numeric($relation) && Relation::tryFrom((int) $relation) !== null ? (int) $relation : null,
            'page' => is_string($page) && ($page === 'active' || Deactivated::tryFrom($page) !== null) ? $page : null,
            'closed' => $closed === '0' || $closed === '1' ? (int) $closed : null,
            'home_town' => $this->nullableFilterString($request->input('home_town')),
            'occupation' => is_string($occupation) && OccupationType::tryFrom($occupation) !== null ? $occupation : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function cityFilters(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        $cities = [];

        foreach ($items as $item) {
            if (! is_string($item)) {
                continue;
            }

            $city = mb_substr(trim($item), 0, 255);
            if ($city === '' || in_array($city, $cities, true)) {
                continue;
            }

            $cities[] = $city;
            if (count($cities) >= 20) {
                break;
            }
        }

        return $cities;
    }

    private function nullableFilterString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        return $text === '' ? null : mb_substr($text, 0, 255);
    }

    private function nullableTrimmedText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        return $text === '' ? null : $text;
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
