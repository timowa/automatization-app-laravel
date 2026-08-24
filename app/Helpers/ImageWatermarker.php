<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use RuntimeException;

class ImageWatermarker
{
    private const LOGO_PATH = __DIR__ . '/../../storage/app/assets/images/brokerplus_logo.jpg';
    private const OPACITY = 60;
    private const LOGO_WIDTH_RATIO = 0.2;
    private const MARGIN = 15;

    public function apply(string $imagePath): string
    {
        try {
            if (!file_exists(self::LOGO_PATH)) {
                Log::channel('vk')->warning('Файл логотипа не найден: ' . self::LOGO_PATH);

                return $imagePath;
            }

            $manager = ImageManager::usingDriver(GdDriver::class);

            $image = $manager->decodePath($imagePath);
            $logo = $manager->decodePath(self::LOGO_PATH);

            $logoWidth = (int) ($image->width() * self::LOGO_WIDTH_RATIO);
            $logo->scale(width: $logoWidth);

            $this->placeWithOpacity($image, $logo, self::MARGIN);

            $image->save($imagePath);

            return $imagePath;
        } catch (\Throwable $e) {
            Log::channel('vk')->error('Ошибка нанесения водяного знака: ' . $e->getMessage());

            return $imagePath;
        }
    }

    private function placeWithOpacity($image, $logo, int $margin): void
    {
        try {
            $logo->opacity(self::OPACITY);
            $image->place($logo, 'top-right', $margin, $margin);

            return;
        } catch (\Throwable $e) {
            // opacity() недоступен — fallback через GD
        }

        $gdImage = $image->core()->native();
        $gdLogo = $logo->core()->native();
        $logoW = imagesx($gdLogo);
        $logoH = imagesy($gdLogo);
        $x = imagesx($gdImage) - $logoW - $margin;
        $y = $margin;

        imagecopymerge($gdImage, $gdLogo, $x, $y, 0, 0, $logoW, $logoH, self::OPACITY);
    }
}
