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

// Parent-app tokens end after 90 days (IssueParentToken); clear the dead rows nightly.
Schedule::command('sanctum:prune-expired --hours=24')
    ->dailyAt('03:00');

// Orange Tree's billing: invoices schools whose current term's census date has passed (RunSaasCensus).
Schedule::command('saas:census')
    ->dailyAt('01:00')
    ->withoutOverlapping();

// Backups (config/backup.php): nightly, then old copies removed and the newest checked.
// A failure or an unhealthy backup is emailed to BACKUP_NOTIFY_EMAIL.
Schedule::command('backup:run')
    ->dailyAt('02:30')
    ->withoutOverlapping();
Schedule::command('backup:clean')
    ->dailyAt('03:15')
    ->withoutOverlapping();
Schedule::command('backup:monitor')
    ->dailyAt('03:30');
