<?php

use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;

it('documents the required settings in .env.example', function () {
    $example = Dotenv::parse(file_get_contents(base_path('.env.example')));

    expect($example)->toMatchArray([
        'APP_BASE_DOMAIN' => 'localhost',
        'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => 'orange_sms',
        'QUEUE_CONNECTION' => 'database',
        'SESSION_DRIVER' => 'database',
        'CACHE_STORE' => 'database',
        'MAIL_MAILER' => 'smtp',
        'MAIL_PORT' => '1025',
        'SMS_DRIVER' => 'log',
    ])->and($example['DB_PASSWORD'])->toBe('');
});

it('reads the base domain and SMS driver from config', function () {
    expect(config('app.base_domain'))->toBe('localhost')
        ->and(config('sms.driver'))->toBe('log');
});

it('runs tests against the MySQL 8 test database', function () {
    $connection = DB::connection();

    expect($connection->getDriverName())->toBe('mysql')
        ->and($connection->getDatabaseName())->toBe('orange_sms_test')
        ->and($connection->scalar('select version()'))->toStartWith('8.');
});
