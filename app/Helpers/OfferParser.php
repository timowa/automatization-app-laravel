<?php

declare(strict_types=1);

namespace App\Helpers;

use App\DTO\OfferData;
use App\Enums\Category;
use App\Enums\City;
use App\Enums\Deal;
use App\Enums\OfferStatus;
use App\Exceptions\OfferParserException;
use App\Models\Agent;

final class OfferParser
{
    public function parse(array $data): OfferData
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

        if (! $agent) {
            throw new OfferParserException('Не найден агент по номеру телефона');
        }

        $cityLabel = $data['location']['city'] ?? null;
        $dealLabel = $data['deal'] ?? null;
        $categoryLabel = $data['category'] ?? null;
        $statusLabel = $data['status'] ?? null;

        $city = City::tryFromLabel($cityLabel);
        if ($city === null) {
            throw new OfferParserException('Неизвестный город: '.($cityLabel ?? 'null'));
        }

        $deal = Deal::tryFromLabel($dealLabel);
        if ($deal === null) {
            throw new OfferParserException('Неизвестный тип сделки: '.($dealLabel ?? 'null'));
        }

        $category = Category::tryFromLabel($categoryLabel);
        if ($category === null) {
            throw new OfferParserException('Неизвестная категория: '.($categoryLabel ?? 'null'));
        }

        $status = OfferStatus::tryFromLabel($statusLabel);
        if ($status === null) {
            throw new OfferParserException('Неизвестный статус: '.($statusLabel ?? 'null'));
        }

        if (! array_key_exists('stage', $data) || $data['stage'] === null) {
            throw new OfferParserException('Не передан этап объекта');
        }

        return $this->buildOfferData($data, $agent->id, $city, $deal, $category, $status);
    }

    /**
     * Best-effort сбор атрибутов при ошибке парсинга (без задач).
     */
    public function buildSkippedOfferData(array $data, string $skipReason): OfferData
    {
        $agentId = null;
        $agentPhone = $data['agent']['phone'] ?? null;
        if (! empty($agentPhone)) {
            $phone = preg_replace('/[^0-9]/', '', (string) $agentPhone);
            if ($phone !== '') {
                $agentId = Agent::where('phone', $phone)->value('id');
            }
        }

        $city = City::tryFromLabel($data['location']['city'] ?? null);
        $deal = Deal::tryFromLabel($data['deal'] ?? null);
        $category = Category::tryFromLabel($data['category'] ?? null);
        $status = OfferStatus::tryFromLabel($data['status'] ?? null);

        return $this->buildOfferData(
            $data,
            $agentId !== null ? (int) $agentId : null,
            $city,
            $deal,
            $category,
            $status,
            $skipReason,
            $data,
        );
    }

    private function buildOfferData(
        array $data,
        ?int $agentId,
        ?City $city,
        ?Deal $deal,
        ?Category $category,
        ?OfferStatus $status,
        ?string $skipReason = null,
        ?array $payload = null,
    ): OfferData {
        $imageUrls = [];
        foreach ($data['photos'] ?? [] as $photo) {
            $image = $photo['url'] ?? '';
            if ($image !== '') {
                $imageUrls[] = $image;
            }
        }

        return new OfferData(
            isset($data['id']) ? (int) $data['id'] : null,
            (string) ($data['code'] ?? ''),
            isset($data['stage']) && $data['stage'] !== null ? (int) $data['stage'] : null,
            $status,
            $city,
            $agentId,
            isset($data['price']) ? (int) $data['price'] : null,
            isset($data['commission']) ? (int) $data['commission'] : null,
            isset($data['deposit']) ? (int) $data['deposit'] : null,
            isset($data['totalArea']) ? (float) $data['totalArea'] : null,
            isset($data['kitchenArea']) ? (float) $data['kitchenArea'] : null,
            isset($data['livingArea']) ? (float) $data['livingArea'] : null,
            isset($data['rooms']) ? (int) $data['rooms'] : null,
            isset($data['roomsOffered']) ? (int) $data['roomsOffered'] : null,
            isset($data['floor']) ? (int) $data['floor'] : null,
            isset($data['floors']) ? (int) $data['floors'] : null,
            $imageUrls,
            $deal,
            $category,
            $data['location'] ?? null,
            $skipReason,
            $payload,
        );
    }
}
