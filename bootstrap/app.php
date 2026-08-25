<?php

use App\Services\ErrorReporter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        $log = storage_path('logs/scheduler.log');
        $schedule->command('slots:release-locks')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo($log);
        $schedule->command('bookings:complete-expired')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo($log);
        $schedule->command('bookings:expire-unconfirmed')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo($log);
        $schedule->command('backup:database')
            ->dailyAt('02:30')
            ->withoutOverlapping()
            ->appendOutputTo($log);
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \App\Http\Middleware\Cors::class,
        ]);
        $middleware->throttleApi();

        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\AdminAuth::class,
            'player.auth' => \App\Http\Middleware\PlayerAuth::class,
            'owner.auth' => \App\Http\Middleware\OwnerAuth::class,
            'log.activity' => \App\Http\Middleware\LogActivity::class,
            'cors' => \App\Http\Middleware\Cors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->reportable(function (\Throwable $e) {
            app(ErrorReporter::class)->report($e);
        });

        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->respond(function (Response $response, \Throwable $e, Request $request) {
            $origin = \App\Support\CorsOrigins::headerFor($request->headers->get('Origin'));
            if ($origin) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Vary', 'Origin');
            }

            if ($request->is('api/*') && !config('app.debug') && $response->getStatusCode() >= 500) {
                $payload = json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'server_error',
                        'message' => 'Something went wrong. Try again.',
                    ],
                ]);
                $response->setContent($payload);
                $response->headers->set('Content-Type', 'application/json');
            }

            return $response;
        });
    })->create();
