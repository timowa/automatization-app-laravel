<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\VkUser;
use App\Services\Vk\VkApiService;
use App\Services\Vk\VkFriendRowMapper;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VkFriendRowMapperTest extends TestCase
{
    public function test_maps_flat_fields_and_ignores_account_capabilities(): void
    {
        $row = (new VkFriendRowMapper)->map(5, [
            'id' => '15',
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'bdate' => '1.2',
            'university_name' => 'ТГУ',
            'faculty' => '8',
            'faculty_name' => 'Исторический',
            'graduation' => '2001',
            'university' => 4,
            'mobile_phone' => '12345',
            'city' => 'Абакан',
            'can_post' => 1,
            'can_see_all_posts' => 1,
            'can_write_private_message' => 1,
            'timezone' => -3,
            'online' => 0,
            'has_mobile' => 1,
        ], new VkUser, Carbon::parse('2026-10-13 02:00:00'));

        $this->assertNotNull($row);
        $this->assertSame(15, $row['user_id']);
        $this->assertSame('02-01', $row['bdate']);
        $this->assertSame('ТГУ', $row['university_name']);
        $this->assertSame(4, $row['university_id']);
        $this->assertSame(8, $row['faculty_id']);
        $this->assertSame('Исторический', $row['faculty_name']);
        $this->assertSame(2001, $row['graduation']);
        $this->assertSame('12345', $row['mobile_phone']);
        $this->assertSame('Абакан', $row['city_name']);
        $this->assertNull($row['city_id']);
        $this->assertSame(-3, $row['timezone']);
        $this->assertSame(0, $row['online']);
        $this->assertSame(1, $row['has_mobile']);
        $this->assertArrayNotHasKey('can_post', $row);
    }

    public function test_maps_nested_education_contacts_and_location(): void
    {
        $row = (new VkFriendRowMapper)->map(5, [
            'id' => 77,
            'first_name' => 'Анна',
            'last_name' => 'Смирнова',
            'nickname' => 'ann',
            'education' => [
                'university' => 10,
                'university_name' => 'ТувГУ',
                'faculty' => 3,
                'faculty_name' => 'Филологический',
                'graduation' => 2012,
            ],
            'contacts' => [
                'mobile_phone' => '+79990001122',
                'home_phone' => '221100',
            ],
            'city' => ['id' => 99, 'title' => 'Кызыл'],
            'country' => ['id' => 1, 'title' => 'Россия'],
            'universities' => [
                ['name' => 'ТувГУ', 'faculty_name' => 'Филологический', 'graduation' => 2012],
            ],
        ], new VkUser, Carbon::parse('2026-10-13 02:00:00'));

        $this->assertNotNull($row);
        $this->assertSame('ann', $row['nickname']);
        $this->assertSame('ann', $row['middle_name']);
        $this->assertSame(10, $row['university_id']);
        $this->assertSame('ТувГУ', $row['university_name']);
        $this->assertSame(3, $row['faculty_id']);
        $this->assertSame('+79990001122', $row['mobile_phone']);
        $this->assertSame('221100', $row['home_phone']);
        $this->assertSame(99, $row['city_id']);
        $this->assertSame('Кызыл', $row['city_name']);
        $this->assertSame(1, $row['country_id']);
        $this->assertSame('Россия', $row['country_name']);
        $this->assertStringContainsString('ТувГУ', (string) $row['universities']);
    }

    public function test_skips_row_without_user_id(): void
    {
        $this->assertNull((new VkFriendRowMapper)->map(1, [
            'first_name' => 'Без идентификатора',
        ], new VkUser, Carbon::parse('2026-10-13 02:00:00')));
    }

    public function test_friend_fields_exclude_account_capabilities(): void
    {
        $fields = VkApiService::FRIEND_FIELDS;

        $this->assertNotContains('can_post', $fields);
        $this->assertNotContains('can_see_all_posts', $fields);
        $this->assertNotContains('can_write_private_message', $fields);
        $this->assertContains('bdate', $fields);
        $this->assertContains('city', $fields);
        $this->assertContains('contacts', $fields);
        $this->assertContains('education', $fields);
        $this->assertContains('universities', $fields);
        $this->assertContains('relation', $fields);
        $this->assertContains('deactivated', $fields);
        $this->assertContains('is_closed', $fields);
        $this->assertContains('home_town', $fields);
        $this->assertContains('occupation', $fields);
        $this->assertContains('schools', $fields);
    }

    public function test_maps_deleted_page_occupation_and_friend_lists(): void
    {
        $row = (new VkFriendRowMapper)->map(5, [
            'id' => 9,
            'first_name' => 'DELETED',
            'last_name' => '',
            'deactivated' => 'deleted',
            'is_closed' => false,
            'has_photo' => 0,
            'home_town' => 'Абакан',
            'occupation' => ['type' => 'work', 'name' => 'Магазин'],
            'schools' => [
                ['name' => 'Школа 1', 'year_graduated' => 2005],
            ],
            'lists' => [2, 7],
            'relation_partner' => ['id' => 15, 'first_name' => 'Пётр', 'last_name' => 'Петров'],
            'blacklisted_by_me' => 1,
            'education_form' => 'Очное отделение',
        ], new VkUser, Carbon::parse('2026-10-13 02:00:00'), [
            2 => 'Клиенты',
        ]);

        $this->assertNotNull($row);
        $this->assertSame('deleted', $row['deactivated']);
        $this->assertSame(0, $row['is_closed']);
        $this->assertSame(0, $row['has_photo']);
        $this->assertSame('Абакан', $row['home_town']);
        $this->assertSame('work', $row['occupation_type']);
        $this->assertSame('Магазин', $row['occupation_name']);
        $this->assertSame('Клиенты, 7', $row['friend_list_names']);
        $this->assertSame(15, $row['relation_partner_id']);
        $this->assertSame('Пётр Петров', $row['relation_partner_name']);
        $this->assertSame(1, $row['blacklisted_by_me']);
        $this->assertSame('Очное отделение', $row['education_form']);
        $this->assertStringContainsString('Школа 1', (string) $row['schools']);
    }
}
