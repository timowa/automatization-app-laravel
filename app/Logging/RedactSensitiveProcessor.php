<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Маскирует секреты в сообщении и контексте до форматтера.
 */
final class RedactSensitiveProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly SensitiveDataRedactor $redactor = new SensitiveDataRedactor,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        try {
            $context = $this->redactor->redactValue($record->context);
            $extra = $this->redactor->redactValue($record->extra);

            return $record->with(
                message: $this->redactor->redactString($record->message),
                context: is_array($context) ? $context : [],
                extra: is_array($extra) ? $extra : [],
            );
        } catch (\Throwable) {
            return $record->with(
                message: 'log.redaction_failed',
                context: [],
                extra: [],
            );
        }
    }
}
