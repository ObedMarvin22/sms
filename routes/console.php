<?php

use Illuminate\Support\Facades\Schedule;

/*
| Shared hosting has no long-running processes (SPEC section 3). Cron runs `schedule:run` every
| minute; it starts a queue worker that stops when the queue is empty or after 50 seconds,
| so runs never pile up. withoutOverlapping() skips a minute if the previous worker is still busy.
*/
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

// Absence notices go out once each school's registers lock (NotifyTodaysAbsences).
Schedule::command('attendance:notify-absences')
    ->everyMinute()
    ->weekdays()
    ->withoutOverlapping();

// Nightly Paystack check: records payments a webhook missed, flags mismatches (ReconcilePaystack).
Schedule::command('paystack:reconcile')
    ->dailyAt('02:00')
    ->withoutOverlapping();
