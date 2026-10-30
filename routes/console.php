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
