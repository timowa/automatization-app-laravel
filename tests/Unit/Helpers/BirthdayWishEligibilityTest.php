<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Enums\BirthdayWishGenderStatus;
use App\Enums\Vk\Sex;
use App\Helpers\BirthdayWishEligibility;
use App\Models\Setting;
use App\Services\Vk\Message\BirthdayWishTextGenerator;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BirthdayWishEligibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_full_birth_date_requires_year(): void
    {
        $this->assertTrue(BirthdayWishEligibility::hasFullBirthDate('1990-05-10'));
        $this->assertFalse(BirthdayWishEligibility::hasFullBirthDate('05-10'));
        $this->assertFalse(BirthdayWishEligibility::hasFullBirthDate(null));
    }

    #[DataProvider('ageProvider')]
    public function test_age_range(string $bdate, string $today, bool $expected): void
    {
        Carbon::setTestNow(Carbon::parse($today));

        $this->assertSame($expected, BirthdayWishEligibility::ageInRange($bdate));
    }

    public static function ageProvider(): array
    {
        return [
            'exactly 18' => ['2008-10-07', '2026-10-07', true],
            'exactly 70' => ['1956-10-07', '2026-10-07', true],
            'under 18' => ['2009-10-08', '2026-10-07', false],
            'over 70' => ['1955-10-06', '2026-10-07', false],
            'without year' => ['10-07', '2026-10-07', false],
        ];
    }

    public function test_message_uses_custom_text_when_enabled(): void
    {
        $setting = new Setting([
            'wish_happy_birthday_male' => true,
            'wish_happy_birthday_female' => false,
            'birthday_wish_male_text' => '  Привет, мужчина  ',
            'birthday_wish_female_text' => 'Текст Ж',
        ]);

        $this->assertSame(
            'Привет, мужчина',
            BirthdayWishEligibility::messageForFriend($setting, Sex::MALE->value, 'Иван', 'Агент')
        );
        $this->assertNull(
            BirthdayWishEligibility::messageForFriend($setting, Sex::FEMALE->value, 'Анна', 'Агент')
        );
        $this->assertNull(
            BirthdayWishEligibility::messageForFriend($setting, Sex::UNSPECIFIED->value, 'Иван', 'Агент')
        );
    }

    public function test_message_falls_back_to_template_when_text_empty(): void
    {
        $setting = new Setting([
            'wish_happy_birthday_male' => true,
            'wish_happy_birthday_female' => true,
            'birthday_wish_male_text' => null,
            'birthday_wish_female_text' => '',
        ]);

        $generator = new class extends BirthdayWishTextGenerator
        {
            public function generate(string $friendName, string $agentName): string
            {
                return "Шаблон: {$friendName} / {$agentName}";
            }
        };

        $this->assertSame(
            'Шаблон: Иван / Агент',
            BirthdayWishEligibility::messageForFriend($setting, Sex::MALE->value, 'Иван', 'Агент', $generator)
        );
        $this->assertSame(
            'Шаблон: Анна / Агент',
            BirthdayWishEligibility::messageForFriend($setting, Sex::FEMALE->value, 'Анна', 'Агент', $generator)
        );
    }

    public function test_status_for_sex(): void
    {
        $setting = new Setting([
            'wish_happy_birthday_male' => false,
            'wish_happy_birthday_female' => true,
            'birthday_wish_male_text' => 'Есть текст',
            'birthday_wish_female_text' => null,
        ]);

        $this->assertSame(
            BirthdayWishGenderStatus::Off,
            BirthdayWishEligibility::statusForSex($setting, Sex::MALE->value)
        );
        $this->assertSame(
            BirthdayWishGenderStatus::Default,
            BirthdayWishEligibility::statusForSex($setting, Sex::FEMALE->value)
        );

        $setting->birthday_wish_female_text = 'Свой текст';
        $this->assertSame(
            BirthdayWishGenderStatus::Custom,
            BirthdayWishEligibility::statusForSex($setting, Sex::FEMALE->value)
        );
    }

    public function test_was_wished_this_year(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07'));

        $this->assertFalse(BirthdayWishEligibility::wasWishedThisYear(null));
        $this->assertFalse(BirthdayWishEligibility::wasWishedThisYear('2025-10-07'));
        $this->assertTrue(BirthdayWishEligibility::wasWishedThisYear('2026-01-15'));
        $this->assertTrue(BirthdayWishEligibility::wasWishedThisYear('2026-10-07'));
    }
}
