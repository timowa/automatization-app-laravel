<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Logger as MonologLogger;

/**
 * Подключает JSONL-форматтер и маскирование к файловому каналу Laravel.
 */
final class ConfigureStructuredLogging
{
    public function __invoke(Logger $logger, string $logChannel = ''): void
    {
        $monolog = $logger->getLogger();
        if (! $monolog instanceof MonologLogger) {
            return;
        }

        $formatter = new StructuredJsonFormatter(
            logChannel: $logChannel !== '' ? $logChannel : 'app',
            service: (string) config('app.name'),
            environment: (string) config('app.env'),
            release: $this->optionalString(config('changelog.current')),
            commitSha: $this->optionalString(config('logging.commit_sha')),
        );

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter($formatter);
            }
        }

        $monolog->pushProcessor(new RedactSensitiveProcessor);

        $handlers = $monolog->getHandlers();
        if ($handlers !== [] && ! $handlers[0] instanceof WhatFailureGroupHandler) {
            $monolog->setHandlers([new WhatFailureGroupHandler($handlers)]);
        }
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
