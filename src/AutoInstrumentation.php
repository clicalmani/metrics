<?php

namespace Clicalmani\Metrics;

use Clicalmani\Core\Http\Request;
use Clicalmani\Core\Support\Facades\Event;
use Clicalmani\Core\Support\Facades\Metrics;
use Clicalmani\Database\Query;

final class AutoInstrumentation
{
    public static function register(array $config): void
    {
        if ($config['http'] ?? false) {
            self::registerHttp();
        }

        if ($config['database'] ?? false) {
            self::registerDatabase();
        }

        if ($config['queue'] ?? false) {
            self::registerQueue();
        }

        if ($config['notifications'] ?? false) {
            self::registerNotifications();
        }
    }

    // ─────────────────────────────────────────────────────────────
    // HTTP
    // ─────────────────────────────────────────────────────────────

    private static function registerHttp(): void
    {
        Event::listen('http.request.started', function (Request $request) {
            $request->__metricsStartedAt = microtime(true);
        });

        Event::listen('http.request.finished', function (Request $request) {
            $duration = isset($request->__metricsStartedAt)
                ? (microtime(true) - $request->__metricsStartedAt) * 1000
                : null;

            $tags = [
                'method' => $request->getMethod(),
                'route'  => $request->route()?->name,
                'status' => app()->response->getStatusCode(),
            ];

            Metrics::increment('http.request.count', tags: $tags);

            if ($duration !== null) {
                Metrics::timing('http.request.duration', $duration, tags: [
                    'method' => $tags['method'],
                    'route'  => $tags['route'],
                ]);
            }

            if ($request->responseSize) {
                Metrics::timing('http.response.size', $request->responseSize, tags: [
                    'method' => $request->getMethod(),
                    'route'  => $request->route()->name,
                ]);
            }

            if ($request->status >= 500) {
                Metrics::increment('http.server_error', tags: $tags);

                logger()->info('🔴 HTTP server error', [
                    'method' => $request->getMethod(),
                    'path'   => $request->getRequestTarget(),
                    'route'  => $request->route()->name,
                    'status' => $request->status,
                ]);
            }

            if ($request->status >= 400) {
                Metrics::increment('http.client_error', tags: $tags);
            }
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Database
    // ─────────────────────────────────────────────────────────────

    private static function registerDatabase(): void
    {
        Event::listen(Query::class, function (Query $query) {
            $type = strtoupper(explode(' ', trim($query->sql))[0] ?? 'OTHER');
            
            Metrics::increment('db.query.count', tags: [
                'connection' => $query->connection,
                'type'       => $type,
            ]);

            Metrics::timing('db.query.duration', $query->duration, tags: [
                'connection' => $query->connection,
                'type'       => $type,
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Queue
    // ─────────────────────────────────────────────────────────────

    private static function registerQueue(): void
    {
        Event::listen(\Clicalmani\Queue\Events\JobPushed::class, function ($e) {
            Metrics::increment('queue.job.pushed', tags: [
                'queue' => $e->queue,
                'job'   => basename(str_replace('\\', '/', $e->notification::class)),
            ]);
        });

        Event::listen(\Clicalmani\Queue\Events\JobProcessed::class, function ($e) {
            Metrics::increment('queue.job.processed', tags: ['queue' => $e->queue]);
        });

        Event::listen(\Clicalmani\Queue\Events\JobFailed::class, function ($e) {
            Metrics::increment('queue.job.failed', tags: [
                'queue' => 'default',
                'error' => $e->exception::class,
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Notifications
    // ─────────────────────────────────────────────────────────────

    private static function registerNotifications(): void
    {
        Event::listen(
            \Clicalmani\Notification\Events\NotificationSending::class,
            function ($e) {
                $e->context->startedAt = microtime(true);

                Metrics::increment('notifications.sending', tags: [
                    'channel' => $e->channel,
                ]);
            }
        );

        Event::listen(
            \Clicalmani\Notification\Events\NotificationSent::class,
            function ($e) {
                Metrics::increment('notifications.sent', tags: [
                    'channel' => $e->channel,
                    'event'   => basename(str_replace('\\', '/', $e->context->event::class)),
                ]);

                if (isset($e->context->startedAt)) {
                    Metrics::timing(
                        'notifications.duration',
                        (microtime(true) - $e->context->startedAt) * 1000,
                        tags: ['channel' => $e->channel]
                    );
                }
            }
        );

        Event::listen(
            \Clicalmani\Notification\Events\NotificationFailed::class,
            function ($e) {
                Metrics::increment('notifications.failed', tags: [
                    'channel' => $e->channel,
                    'error'   => $e->exception::class,
                ]);
            }
        );

        Event::listen(
            \Clicalmani\Notification\Events\NotificationSkipped::class,
            function ($e) {
                Metrics::increment('notifications.skipped', tags: [
                    'channel' => $e->channel,
                ]);
            }
        );
    }
}