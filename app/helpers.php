<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;

if (!function_exists('viewJson')) {
    function viewJson(bool $success = true, ?array $messages = null, ?string $redirect = null, array $extra = []): never
    {
        response()->json(array_merge([
            'success' => $success,
            'messages' => $messages,
            'redirect' => $redirect,
        ], $extra))->send();
        exit;
    }
}

if (!function_exists('downloadFile')) {
    function downloadFile(string $url, string $directory): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!$extension) {
            $extension = 'jpg';
        }

        $filename = sprintf(
            '%s/%s.%s',
            rtrim($directory, '/'),
            uniqid('vk_', true),
            $extension
        );

        $fp = fopen($filename, 'wb');

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FAILONERROR => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            fclose($fp);
            @unlink($filename);
            throw new RuntimeException($error);
        }

        curl_close($ch);
        fclose($fp);

        return $filename;
    }
}

if (!function_exists('isImageUrl')) {
    function isImageUrl(string $url): bool
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
            CURLOPT_RETURNTRANSFER => true,
        ]);

        curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return false;
        }

        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        return $contentType && str_starts_with($contentType, 'image/');
    }
}

if (!function_exists('formatPrice')) {
    function formatPrice(int $price): string
    {
        return number_format($price, 0, '', ' ');
    }
}

if (!function_exists('formatArea')) {
    function formatArea(float $area): string
    {
        return number_format($area, 1, ',', ' ');
    }
}

if (!function_exists('phoneFormat')) {
    function phoneFormat($phone)
    {
        $mask = "#";
        $format = [
            '7' => '###-##-##',
            '10' => '+7 (###) ###-##-##',
            '11' => '# (###) ###-##-##',
        ];

        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (is_array($format)) {
            if (array_key_exists(strlen($phone), $format)) {
                $format = $format[strlen($phone)];
            } else {
                return false;
            }
        }

        $pattern = '/' . str_repeat('([0-9])?', substr_count($format, $mask)) . '(.*)/';

        $counter = 0;
        $format = preg_replace_callback(
            str_replace('#', $mask, '/([#])/'),
            function () use (&$counter) {
                return '${' . (++$counter) . '}';
            },
            $format
        );

        if ($phone) {
            $phone = trim(preg_replace($pattern, $format, $phone, 1));
            if (mb_substr($phone, 0, 1) == '7') {
                $phone = '+' . $phone;
            }
        }

        return $phone;
    }
}

if (!function_exists('array_keys_from_column')) {
    function array_keys_from_column($array, $column)
    {
        if (!$array || !$column || empty(array_column($array, $column))) {
            return false;
        }

        $newArray = [];
        foreach ($array as $subArray) {
            if (!empty($newArray[$subArray[$column]])) {
                return false;
            }

            $newArray[$subArray[$column]] = $subArray;
        }

        return $newArray;
    }
}

if (!function_exists('appLogger')) {
    function appLogger(string $channel = 'job')
    {
        return Log::channel($channel);
    }
}
