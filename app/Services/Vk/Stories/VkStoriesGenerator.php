<?php

declare(strict_types=1);

namespace App\Services\Vk\Stories;

use App\Exceptions\StoryPublicationSkippedException;
use App\Helpers\ImageWatermarker;
use App\Interfaces\VkStoriesTemplateInterface;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;
use RuntimeException;
use Throwable;

class VkStoriesGenerator
{
    private const BANNER_TEMPLATE = __DIR__.'/../../../../storage/app/assets/images/vkstory.png';

    private const FONT_PATH = __DIR__.'/../../../../storage/app/assets/fonts/ProximaNova.ttf';

    private const FONT_BOLD_PATH = __DIR__.'/../../../../storage/app/assets/fonts/ProximaNovaSemibold.ttf';

    private const IMAGE_DOWNLOAD_TIMEOUT_SECONDS = 15;

    public function generate(VkStoriesContext $context, VkStoriesTemplateInterface $template): string
    {
        $imagePath = null;

        try {
            $url = trim($context->image);
            if ($url === '') {
                throw new StoryPublicationSkippedException('У объекта нет фото, публикация истории пропущена');
            }

            if (! file_exists(self::BANNER_TEMPLATE)) {
                throw new RuntimeException('Файл шаблона не найден по пути: '.self::BANNER_TEMPLATE);
            }

            if (! file_exists(self::FONT_PATH)) {
                throw new RuntimeException('Файл шрифта не найден по пути: '.self::FONT_PATH);
            }

            $imagePath = $this->downloadImageToTemp($url);

            $imagePath = (new ImageWatermarker)->apply($imagePath);

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

            $insertImage = $manager->decodePath($imagePath);
            $insertImage->cover(width: 900, height: 600);
            $img->insert($insertImage, 0, 200, 'center');

            $fileName = sprintf(
                storage_path('app/tmp/story_%s.jpg'),
                uniqid()
            );

            $img->save($fileName);

            return $fileName;
        } catch (StoryPublicationSkippedException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($this->isUnavailableImageError($e)) {
                $message = str_contains($e->getMessage(), '404')
                    ? 'Ссылка на фото недоступна (404), публикация истории пропущена'
                    : 'Ссылка на фото недоступна, публикация истории пропущена';

                throw new StoryPublicationSkippedException($message, previous: $e);
            }

            Log::channel('vk')->error('Ошибка генерации изображения истории: '.$e->getMessage());
            throw $e;
        } finally {
            $this->deleteTemporaryImage($imagePath);
        }
    }

    private function downloadImageToTemp(string $url): string
    {
        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        });

        try {
            $contents = file_get_contents($url, false, $this->imageStreamContext());
        } finally {
            restore_error_handler();
        }

        if (! is_string($contents) || $contents === '') {
            $message = is_string($warning) && str_contains($warning, '404')
                ? 'Ссылка на фото недоступна (404), публикация истории пропущена'
                : 'Ссылка на фото недоступна, публикация истории пропущена';

            throw new StoryPublicationSkippedException($message);
        }

        $imageInfo = @getimagesizefromstring($contents);
        if (! is_array($imageInfo) || ! isset($imageInfo[2])) {
            throw new StoryPublicationSkippedException('Ссылка на фото недоступна, публикация истории пропущена');
        }

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

        if (file_put_contents($imagePath, $contents) === false) {
            throw new RuntimeException('Не удалось сохранить временный файл изображения истории');
        }

        return $imagePath;
    }

    /**
     * @return resource
     */
    private function imageStreamContext()
    {
        return stream_context_create([
            'http' => [
                'timeout' => self::IMAGE_DOWNLOAD_TIMEOUT_SECONDS,
                'follow_location' => 1,
            ],
            'https' => [
                'timeout' => self::IMAGE_DOWNLOAD_TIMEOUT_SECONDS,
                'follow_location' => 1,
            ],
        ]);
    }

    private function isUnavailableImageError(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '404')
            || str_contains($message, 'failed to open stream')
            || str_contains($message, 'timed out');
    }

    private function deleteTemporaryImage(mixed $imagePath): void
    {
        if (! is_string($imagePath) || $imagePath === '' || ! file_exists($imagePath)) {
            return;
        }

        try {
            unlink($imagePath);
        } catch (Throwable) {
        }
    }
}
