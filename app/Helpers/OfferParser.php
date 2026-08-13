<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\Category;
use App\Enums\City;
use App\Enums\Deal;
use App\Enums\OfferStatus;
use App\Exceptions\OfferParserException;
use App\Models\Agent;

final class OfferParser
{
    public function parse(array $data): \App\DTO\OfferData
    {
        $agentPhone = $data['agent']['phone'] ?? null;
        $offerCode = $data['code'] ?? null;

        if (empty($agentPhone)) {
            throw new OfferParserException('Не передан номер телефона агента');
        }

        if (empty($offerCode)) {
            throw new OfferParserException('Не передан код объекта');
        }

        $phone = preg_replace('/[^0-9]/', '', $agentPhone);
        /** @var Agent|null $agent */
        $agent = Agent::where('phone', $phone)->first();

        if (!$agent) {
            throw new OfferParserException('Не найден агент по номеру телефона');
        }

        $images = [];
        foreach ($data['photos'] ?? [] as $photo) {
            $image = $photo['url'] ?? '';
            if ($image !== '') {
                $images[] = $image;
            }
        }

        $city = City::tryFromLabel($data['location']['city'] ?? null) ?? null;
        $deal = Deal::tryFromLabel($data['deal'] ?? null) ?? null;
        $category = Category::tryFromLabel($data['category'] ?? null) ?? null;
        $status = OfferStatus::tryFromLabel($data['status']);

        return new \App\DTO\OfferData(
            (int) $data['id'],
            (string) $data['code'],
            $data['stage'],
            $status,
            $city,
            $agent->id,
            (int) ($data['price'] ?? 0),
            isset($data['commission']) ? (int) $data['commission'] : null,
            isset($data['deposit']) ? (int) $data['deposit'] : null,
            (float) ($data['totalArea'] ?? 0),
            isset($data['kitchenArea']) ? (float) $data['kitchenArea'] : null,
            isset($data['livingArea']) ? (float) $data['livingArea'] : null,
            (int) ($data['rooms'] ?? 0),
            isset($data['roomsOffered']) ? (int) $data['roomsOffered'] : null,
            isset($data['floor']) ? (int) $data['floor'] : null,
            isset($data['floors']) ? (int) $data['floors'] : null,
            $images,
            $deal,
            $category,
            $data['location'] ?? null,
        );
    }
}
