<?php

declare(strict_types=1);

namespace App\Logging;

use DateTimeInterface;
use Stringable;
use Throwable;

/**
 * Убирает секреты и обрезает слишком большие значения до записи в журнал.
 */
final class SensitiveDataRedactor
{
    private const MAX_DEPTH = 6;

    private const MAX_ITEMS = 40;

    private const MAX_STRING = 2000;

    private const MAX_TRACE = 8000;

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'passwd',
        'password_confirmation',
        'current_password',
        'secret',
        'client_secret',
        'token',
        'access_token',
        'refresh_token',
        'vk_token',
        'user_token',
        'authorization',
        'cookie',
        'cookies',
        'set_cookie',
        'session',
        'session_id',
        'sessionid',
        'api_key',
        'apikey',
        'credential',
        'credentials',
        'remember_token',
        'auth',
        'bearer',
    ];

    public function redactValue(mixed $value, int $depth = 0, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return '***';
        }

        if ($value instanceof Throwable || $value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_string($value)) {
            $limit = $this->isTraceKey($key) ? self::MAX_TRACE : self::MAX_STRING;

            return $this->limit($this->isTraceKey($key) ? $this->redactTrace($value) : $this->redactString($value), $limit);
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        if ($depth >= self::MAX_DEPTH) {
            return '…';
        }

        if (is_array($value)) {
            $redacted = [];
            $count = 0;
            foreach ($value as $childKey => $child) {
                $count++;
                if ($count > self::MAX_ITEMS) {
                    $redacted['_truncated'] = true;
                    break;
                }
                $name = is_string($childKey) ? $childKey : null;
                $redacted[$childKey] = $this->redactValue($child, $depth + 1, $name);
            }

            return $redacted;
        }

        if ($value instanceof Stringable) {
            return $this->limit($this->redactString((string) $value), self::MAX_STRING);
        }

        if (is_object($value)) {
            return ['object' => $value::class];
        }

        return '[unsupported]';
    }

    public function redactString(string $value): string
    {
        $value = $this->stripDatabaseText($value);
        $value = preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/=]+/i', 'Bearer ***', $value) ?? $value;
        $value = preg_replace('/\bvk1\.[A-Za-z0-9._\-]{8,}\b/', 'vk1.***', $value) ?? $value;
        $value = preg_replace(
            '/\b((?:access_token|refresh_token|token|password|secret|api_key|session))\s*=\s*[^&\s]+/i',
            '$1=***',
            $value,
        ) ?? $value;

        return $value;
    }

    public function redactTrace(string $value): string
    {
        $value = $this->redactString($value);
        $value = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", "'***'", $value) ?? $value;

        return $this->limit($value, self::MAX_TRACE);
    }

    private function stripDatabaseText(string $value): string
    {
        $databaseError = str_contains($value, 'SQLSTATE')
            || str_contains($value, '(Connection:')
            || preg_match('/\bSQL:\s/i', $value) === 1;

        if (! $databaseError) {
            return $value;
        }

        $value = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", "'***'", $value) ?? $value;
        $value = preg_replace('/\s*\(Connection:\s.*$/s', '', $value) ?? $value;
        $value = preg_replace('/\bSQL:\s*.*/is', 'SQL: [redacted]', $value) ?? $value;

        return trim($value);
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace('-', '_', $key));

        return in_array($normalized, self::SENSITIVE_KEYS, true);
    }

    private function isTraceKey(?string $key): bool
    {
        if ($key === null) {
            return false;
        }

        $normalized = strtolower(str_replace('-', '_', $key));

        return $normalized === 'trace' || $normalized === 'stack_trace';
    }

    private function limit(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max).'…';
    }
}
