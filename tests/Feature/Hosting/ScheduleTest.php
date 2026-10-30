<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('runs a short-lived queue worker every minute without overlapping', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => str_contains((string) $event->command, 'queue:work'));

    expect($events)->toHaveCount(1);

    $worker = $events->first();
    expect($worker->command)->toContain('--stop-when-empty')->toContain('--max-time=50')
        ->and($worker->expression)->toBe('* * * * *')
        ->and($worker->withoutOverlapping)->toBeTrue();
});

it('uses the database queue, session and cache drivers from .env.example', function () {
    $example = Dotenv\Dotenv::parse(file_get_contents(base_path('.env.example')));

    expect($example['QUEUE_CONNECTION'])->toBe('database')
        ->and($example['SESSION_DRIVER'])->toBe('database')
        ->and($example['CACHE_STORE'])->toBe('database');
});
