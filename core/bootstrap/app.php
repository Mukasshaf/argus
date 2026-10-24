<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyChannelSignature;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'channel.signature' => VerifyChannelSignature::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('argus:dispatch-sync')->everyMinute()->withoutOverlapping();
        $schedule->command('argus:poll-channels')->everyMinute()->withoutOverlapping();
        $schedule->command('argus:extend-inventory --days=540')->dailyAt('03:00');
        $schedule->command('argus:reconcile')->hourly();
        $schedule->command('model:prune')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
