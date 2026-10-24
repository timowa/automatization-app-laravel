<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Context\Events\ContextHydrated;
use Illuminate\Log\Context\Repository;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Throwable;

/**
 * Связывает записи одного HTTP-запроса, задания очереди или консольной команды.
 * Для sync-очереди восстанавливает контекст вызывающего кода после задания.
 */
final class OperationContext
{
    /** @var list<array{data: array<string, mixed>, hidden: array<string, mixed>}> */
    private array $syncSnapshots = [];

    /** @var list<array<string, mixed>> */
    private array $commandSnapshots = [];

    private bool $awaitingJobHydration = false;

    private string $pendingOperation = 'queue';

    public function __construct(private readonly Application $app) {}

    public function registerJobBoundary(): void
    {
        $events = $this->app['events'];
        $events->listen(JobProcessing::class, function (JobProcessing $event): void {
            $this->beforeJob($event);
        });
        $events->listen(JobAttempted::class, function (JobAttempted $event): void {
            $this->afterJob($event);
        });
    }

    public function registerRuntimeListeners(): void
    {
        $events = $this->app['events'];

        $events->listen(function (ContextHydrated $event): void {
            $this->onContextHydrated($event->context);
        });

        $events->listen(function (CommandStarting $event): void {
            $this->beforeCommand($event);
        });

        $events->listen(function (CommandFinished $event): void {
            $this->afterCommand();
        });

        $events->listen(function (RouteMatched $event): void {
            $uri = $event->route->uri();
            Context::add('route', '/'.ltrim($uri, '/'));
            Context::add('http_method', $event->request->getMethod());
            Context::add('component', 'http');
        });
    }

    public function beforeJob(JobProcessing $event): void
    {
        if ($event->connectionName === 'sync') {
            $this->syncSnapshots[] = [
                'data' => [...Context::all()],
                'hidden' => [...Context::allHidden()],
            ];
        }

        try {
            $name = $event->job->resolveName();
        } catch (Throwable) {
            $name = 'queue';
        }

        $this->pendingOperation = $name !== '' ? $name : 'queue';
        $this->awaitingJobHydration = true;
    }

    public function onContextHydrated(Repository $context): void
    {
        if (! $this->awaitingJobHydration) {
            return;
        }

        $this->awaitingJobHydration = false;
        $context->add('operation_id', (string) Str::uuid());
        $context->add('component', 'queue');
        $context->add('operation', $this->pendingOperation);
    }

    public function afterJob(JobAttempted $event): void
    {
        if ($event->connectionName !== 'sync') {
            Context::flush();

            return;
        }

        $snapshot = array_pop($this->syncSnapshots);
        Context::flush();
        if (! is_array($snapshot)) {
            return;
        }

        if ($snapshot['data'] !== []) {
            Context::add($snapshot['data']);
        }
        if ($snapshot['hidden'] !== []) {
            Context::addHidden($snapshot['hidden']);
        }
    }

    public function beforeCommand(CommandStarting $event): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commandSnapshots[] = Context::only(['operation_id', 'component', 'operation']);
        $name = $event->command !== '' ? $event->command : 'console';
        Context::add([
            'operation_id' => (string) Str::uuid(),
            'component' => 'console',
            'operation' => $name,
        ]);
    }

    public function afterCommand(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $previous = array_pop($this->commandSnapshots);
        Context::forget(['operation_id', 'component', 'operation']);
        if (is_array($previous) && $previous !== []) {
            Context::add($previous);
        }
    }
}
