<?php

namespace App\Support\Deployment;

/**
 * Checks the server's settings before a release (scripts/deploy.sh runs `app:preflight` before the
 * site goes down). Errors stop the release; warnings are printed and the release goes on. Only
 * reads settings, never changes one. Rules differ for production and staging: staging must never
 * reach real parents (no live Paystack keys, no real SMS).
 */
final class Preflight
{
    /**
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public static function check(): array
    {
        $env = (string) app()->environment();
        $errors = [];
        $warnings = [];

        if (! in_array($env, ['production', 'staging'], true)) {
            $errors[] = "APP_ENV is \"{$env}\": releases are for production or staging.";

            return ['errors' => $errors, 'warnings' => $warnings];
        }
        $production = $env === 'production';

        // The basics.
        if (config('app.debug')) {
            $errors[] = 'APP_DEBUG is on: error pages would show settings and data. Set APP_DEBUG=false.';
        }
        if (blank(config('app.key'))) {
            $errors[] = 'APP_KEY is empty. Run php artisan key:generate --force once.';
        }

        $url = (string) config('app.url');
        $base = (string) config('app.base_domain');
        if (! str_starts_with($url, 'https://')) {
            $errors[] = "APP_URL ({$url}) must start with https://.";
        }
        if (parse_url($url, PHP_URL_HOST) !== $base) {
            $errors[] = "APP_BASE_DOMAIN ({$base}) must be the host of APP_URL ({$url}), or schools' subdomains will not resolve.";
        }

        // Shared hosting: no Redis or long-running workers (SPEC section 3).
        foreach (['queue.default' => 'QUEUE_CONNECTION', 'session.driver' => 'SESSION_DRIVER', 'cache.default' => 'CACHE_STORE'] as $key => $name) {
            if (config($key) !== 'database') {
                $errors[] = "{$name} is \"".config($key).'": it must be "database" on shared hosting.';
            }
        }
        if (! config('session.secure')) {
            $errors[] = 'SESSION_SECURE_COOKIE must be true: sign-in cookies only over HTTPS.';
        }

        // Mail.
        $mailer = (string) config('mail.default');
        $host = (string) config("mail.mailers.{$mailer}.host");
        $testInbox = in_array($mailer, ['log', 'array'], true)
            || str_contains($host, 'mailtrap') || str_contains($host, 'sandbox')
            || in_array($host, ['127.0.0.1', 'localhost'], true);
        if ($production && $testInbox) {
            $errors[] = "Mail goes to a test inbox ({$mailer} {$host}): invitations and resets would never arrive. Use the real provider (Brevo or Mailgun).";
        }

        // Paystack: live keys only in production, never on staging.
        $secret = (string) config('services.paystack.secret_key');
        if ($production && $secret === '') {
            $warnings[] = 'PAYSTACK_SECRET_KEY is empty: parents cannot pay online yet.';
        } elseif ($production && ! str_starts_with($secret, 'sk_live_')) {
            $errors[] = 'PAYSTACK_SECRET_KEY is not a live key (sk_live_…) in production.';
        } elseif (! $production && str_starts_with($secret, 'sk_live_')) {
            $errors[] = 'PAYSTACK_SECRET_KEY is a LIVE key on staging. Use test keys (sk_test_…) only.';
        }

        // SMS: real sending only in production.
        $sms = (string) config('sms.driver');
        if ($production && $sms === 'log') {
            $warnings[] = 'SMS_DRIVER is "log": no SMS reaches parents (codes, absences, payments) until it is "arkesel".';
        } elseif (! $production && $sms !== 'log') {
            $errors[] = "SMS_DRIVER is \"{$sms}\" on staging: real parents could be texted. Use \"log\".";
        }

        // Backups.
        if ($production) {
            $disks = (array) config('backup.backup.destination.disks');
            if (! in_array('r2', $disks, true)) {
                $warnings[] = 'Backups are not going off-site: set BACKUP_DISKS=r2.';
            } elseif (blank(config('filesystems.disks.r2.key')) || blank(config('filesystems.disks.r2.bucket')) || blank(config('filesystems.disks.r2.endpoint'))) {
                $errors[] = 'BACKUP_DISKS=r2 but the R2_* settings are incomplete: backups would fail.';
            }
            if (blank(config('backup.backup.password'))) {
                $warnings[] = 'BACKUP_ARCHIVE_PASSWORD is empty: backups (children\'s data) are stored unencrypted.';
            }
        }

        // Folders the app writes to.
        foreach ([storage_path(), storage_path('framework/cache'), storage_path('logs'), base_path('bootstrap/cache')] as $folder) {
            if (! is_dir($folder) || ! is_writable($folder)) {
                $errors[] = "{$folder} is not writable.";
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}
