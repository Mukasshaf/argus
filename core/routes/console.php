<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('staybridge:dispatch-sync')->everyMinute()->withoutOverlapping();
Schedule::command('staybridge:poll-channels')->everyMinute()->withoutOverlapping();
Schedule::command('staybridge:extend-inventory --days=540')->dailyAt('03:00');
Schedule::command('staybridge:reconcile')->hourly();
Schedule::command('model:prune')->daily();
