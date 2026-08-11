<?php

declare(strict_types=1);

namespace App\Services\Vk\Stories;

use App\Interfaces\VkStoriesTemplateInterface;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;
use RuntimeException;
use Throwable;

class VkStoriesGenerator
{
    private const BANNER_TEMPLATE = __DIR__ . '/../../../../../storage/app/assets/images/vkstory.png';
    private const FONT_PATH = __DIR__ . '/../../../../../storage/app/assets/fonts/ProximaNova.ttf';
    private const FONT_BOLD_PATH = __DIR__ . '/../../../../../storage/app/assets/fonts/ProximaNovaSemibold.ttf';

    public function generate(VkStoriesContext $context, VkStoriesTemplateInterface $template): string
    {
        if (!file_exists(self::BANNER_TEMPLATE)) {
            throw new RuntimeException('Файл шаблона не найден по пути: ' . self::BANNER_TEMPLATE);
        }

        if (!file_exists(self::FONT_PATH)) {
            throw new RuntimeException('Файл шрифта не найден по пути: ' . self::FONT_PATH);
        }

        $lines = explode("\n", $template->getDetails($context));
        $price = $template->getPrice($context);

        $manager = ImageManager::usingDriver(GdDriver::class);
        $img = $manager->decodePath(self::BANNER_TEMPLATE);

        $img->drawRectangle(function ($r) {
            $r->at(0, 400);
            $r->size(1080, 150);
            $r->background('#ce1b21');
        });

        $img->text($price, 250, 510, function (FontFactory $font) {
            $font->filename(self::FONT_BOLD_PATH);
            $font->size(90);
            $font->color('ffffff');
        });

        $y = 620;
        foreach ($lines as $line) {
            $img->text($line, 90, $y, function (FontFactory $font) {
                $font->filename(self::FONT_BOLD_PATH);
                $font->size(60);
                $font->color('333333');
                $font->align('left', 'top');
            });
            $y += 70;
        }

        try {
            $url = $context->image;
            $imageInfo = getimagesize($url);

            $extension = match ($imageInfo[2]) {
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_WEBP => 'webp',
                IMAGETYPE_GIF => 'gif',
                IMAGETYPE_AVIF => 'avif',
                default => throw new RuntimeException('Unsupported image type'),
            };

            $imagePath = sprintf(
                storage_path('app/tmp/image_%s.%s'),
                uniqid(),
                $extension
            );

            copy($url, $imagePath);
            $insertImage = $manager->decodePath($imagePath);
            $insertImage->cover(width: 900, height: 600);
            $img->insert($insertImage, 0, 200, 'center');
            unlink($imagePath);
        } catch (Throwable $e) {
            Log::channel('vk')->error('Ошибка генерации изображения истории: ' . $e->getMessage());
            throw $e;
        }

        $fileName = sprintf(
            storage_path('app/tmp/story_%s.jpg'),
            uniqid()
        );

        $img->save($fileName);

        return $fileName;
    }
}
