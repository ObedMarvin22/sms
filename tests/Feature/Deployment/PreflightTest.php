<?php

use App\Models\School;
use App\Support\Deployment\Preflight;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;

/*
| Pilot preparation step 1: `app:preflight` stops a release when production or staging settings
| are wrong. Each test starts from a correct production setup and breaks one thing.
*/

function asEnvironment(string $env): void
{
    app()->detectEnvironment(fn () => $env);
}

function goodProduction(): void
{
    asEnvironment('production');
    config([
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'app.url' => 'https://orangetree.example',
        'app.base_domain' => 'orangetree.example',
        'queue.default' => 'database',
        'session.driver' => 'database',
        'cache.default' => 'database',
        'session.secure' => true,
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'smtp-relay.brevo.com',
        'services.paystack.secret_key' => 'sk_live_example',
        'sms.driver' => 'arkesel',
        'backup.backup.destination.disks' => ['r2'],
        'backup.backup.password' => 'long-secret',
        'filesystems.disks.r2.key' => 'key',
        'filesystems.disks.r2.bucket' => 'orange-tree-backups',
        'filesystems.disks.r2.endpoint' => 'https://account.r2.cloudflarestorage.com',
    ]);
}

afterEach(fn () => asEnvironment('testing'));

it('passes a correct production setup with nothing to say', function () {
    goodProduction();

    expect(Preflight::check())->toBe(['errors' => [], 'warnings' => []]);
    $this->artisan('app:preflight')->assertSuccessful()->expectsOutputToContain('Pre-flight checks passed for production');
});

it('refuses to run outside production and staging', function () {
    asEnvironment('local');

    $this->artisan('app:preflight')->assertFailed()->expectsOutputToContain('releases are for production or staging');
});

it('stops the release for each wrong production setting', function (array $config, string $message) {
    goodProduction();
    config($config);

    $errors = Preflight::check()['errors'];
    expect($errors)->toHaveCount(1)->and($errors[0])->toContain($message);
    $this->artisan('app:preflight')->assertFailed();
})->with([
    'debug on' => [['app.debug' => true], 'APP_DEBUG is on'],
    'no key' => [['app.key' => ''], 'APP_KEY is empty'],
    'plain http' => [['app.url' => 'http://orangetree.example'], 'must start with https://'],
    'base domain mismatch' => [['app.base_domain' => 'orange.example'], 'must be the host of APP_URL'],
    'redis queue' => [['queue.default' => 'redis'], 'QUEUE_CONNECTION is "redis"'],
    'file sessions' => [['session.driver' => 'file'], 'SESSION_DRIVER'],
    'insecure cookie' => [['session.secure' => false], 'SESSION_SECURE_COOKIE'],
    'mail to log' => [['mail.default' => 'log'], 'test inbox'],
    'mail to Mailtrap' => [['mail.mailers.smtp.host' => 'sandbox.smtp.mailtrap.io'], 'test inbox'],
    'test Paystack key' => [['services.paystack.secret_key' => 'sk_test_example'], 'not a live key'],
    'R2 half set up' => [['filesystems.disks.r2.endpoint' => ''], 'R2_* settings are incomplete'],
]);

it('warns but lets the release go on for things Obed switches on later', function (array $config, string $message) {
    goodProduction();
    config($config);

    $result = Preflight::check();
    expect($result['errors'])->toBe([])
        ->and($result['warnings'])->toHaveCount(1)
        ->and($result['warnings'][0])->toContain($message);
    $this->artisan('app:preflight')->assertSuccessful();
})->with([
    'no Paystack yet' => [['services.paystack.secret_key' => ''], 'cannot pay online yet'],
    'SMS still logged' => [['sms.driver' => 'log'], 'SMS_DRIVER is "log"'],
    'backups only local' => [['backup.backup.destination.disks' => ['local']], 'BACKUP_DISKS=r2'],
    'unencrypted backups' => [['backup.backup.password' => null], 'unencrypted'],
]);

it('never lets staging reach real parents', function (array $config, string $message) {
    goodProduction();
    asEnvironment('staging');
    config(['services.paystack.secret_key' => 'sk_test_example', 'sms.driver' => 'log', ...$config]);

    $errors = Preflight::check()['errors'];
    expect($errors)->toHaveCount(1)->and($errors[0])->toContain($message);
})->with([
    'live Paystack key' => [['services.paystack.secret_key' => 'sk_live_example'], 'LIVE key on staging'],
    'real SMS' => [['sms.driver' => 'arkesel'], 'on staging'],
]);

it('lets staging use a test inbox, test keys and local backups', function () {
    goodProduction();
    asEnvironment('staging');
    config([
        'services.paystack.secret_key' => 'sk_test_example', 'sms.driver' => 'log',
        'mail.mailers.smtp.host' => 'sandbox.smtp.mailtrap.io', 'backup.backup.destination.disks' => ['local'],
    ]);

    expect(Preflight::check())->toBe(['errors' => [], 'warnings' => []]);
});

describe('security headers', function () {
    it('sends HSTS and friends in production and staging', function (string $env) {
        asEnvironment($env);
        School::factory()->active()->create(['subdomain' => 'greenhill']);

        $this->get('http://greenhill.localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=15552000; includeSubDomains')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    })->with(['production', 'staging']);

    it('sends none locally, where the app runs on plain http', function () {
        School::factory()->active()->create(['subdomain' => 'greenhill']);

        $this->get('http://greenhill.localhost/login')->assertHeaderMissing('Strict-Transport-Security');
    });
});

describe('backups', function () {
    it("backs up the database and the schools' private files, not the backups themselves", function () {
        expect(config('backup.backup.source.databases'))->toContain('mysql')
            ->and(config('backup.backup.source.files.include'))->toBe([storage_path('app/private')])
            ->and(config('backup.backup.source.files.exclude'))->toBe([storage_path('app/private/orange-tree-sms')]);
    });

    it('keeps 7 daily, 4 weekly and 6 monthly copies and only emails problems', function () {
        expect(config('backup.cleanup.default_strategy'))->toMatchArray([
            'keep_all_backups_for_days' => 7, 'keep_daily_backups_for_days' => 7,
            'keep_weekly_backups_for_weeks' => 4, 'keep_monthly_backups_for_months' => 6,
        ])->and(config('backup.notifications.notifications.'.BackupHasFailedNotification::class))->toBe(['mail'])
            ->and(config('backup.notifications.notifications.'.BackupWasSuccessfulNotification::class))->toBe([]);
    });

    it('runs nightly, then cleans up and checks', function () {
        $commands = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn ($event) => [trim(preg_replace('/^.*artisan.? /', '', (string) $event->command) ?? '', "'\"") => $event->expression]);

        expect($commands['backup:run'] ?? null)->toBe('30 2 * * *')
            ->and($commands['backup:clean'] ?? null)->toBe('15 3 * * *')
            ->and($commands['backup:monitor'] ?? null)->toBe('30 3 * * *');
    });

    it('has an R2 disk that fails loudly', function () {
        expect(config('filesystems.disks.r2'))->toMatchArray(['driver' => 's3', 'region' => 'auto', 'use_path_style_endpoint' => true, 'throw' => true]);
    });
});
