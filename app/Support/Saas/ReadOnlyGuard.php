<?php

namespace App\Support\Saas;

use App\Exceptions\SchoolIsReadOnly;
use App\Models\Concerns\BelongsToSchool;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Refuses every insert, update and delete on a school-owned table while it is on (Pilot
 * preparation step 4). It is switched on only for staff requests to a read-only school
 * (EnforceSaasReadOnly middleware), so the parent API, webhooks, queued jobs, the scheduler and
 * the console are never affected.
 *
 * Guarding the database rather than each screen means a new screen, or a query-builder write,
 * cannot slip through. Tables that are not school-owned (sessions, cache, jobs, users, the
 * activity log) are left alone, so signing in and out still works.
 *
 * Registered as a scoped singleton: it starts off for every request and every job.
 */
final class ReadOnlyGuard
{
    private bool $on = false;

    private int $permitted = 0;

    /** @var list<string>|null */
    private static ?array $tables = null;

    public function switchOn(): void
    {
        $this->on = true;
    }

    public function isOn(): bool
    {
        return $this->on;
    }

    /**
     * Runs something that stays allowed while read-only (recording fee payments: the money is
     * the school's and parents need receipts).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function permit(Closure $callback): mixed
    {
        $this->permitted++;
        try {
            return $callback();
        } finally {
            $this->permitted--;
        }
    }

    /**
     * Called before every SQL statement (AppServiceProvider: DB::beforeExecuting).
     */
    public function check(string $sql): void
    {
        if (! $this->on || $this->permitted > 0) {
            return;
        }

        if (preg_match('/^\s*(?:insert(?:\s+ignore)?\s+into|replace\s+into|update|delete\s+from)\s+[`"]?(\w+)[`"]?/i', $sql, $m) !== 1) {
            return;
        }

        $table = strtolower($m[1]);
        if (in_array($table, self::schoolOwnedTables(), true)) {
            throw SchoolIsReadOnly::make($table);
        }
    }

    /**
     * The tables of every model using BelongsToSchool (the same list the architecture test
     * checks), so a new school-owned table is guarded without anyone remembering to add it.
     *
     * @return list<string>
     */
    public static function schoolOwnedTables(): array
    {
        if (self::$tables !== null) {
            return self::$tables;
        }

        $tables = [];
        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $class = 'App\\Models\\'.Str::before(basename($file), '.php');
            if (class_exists($class) && is_subclass_of($class, Model::class)
                && in_array(BelongsToSchool::class, class_uses_recursive($class), true)) {
                $tables[] = (new $class)->getTable();
            }
        }
        // Also the school's own row (profile, logo, settings) and its roles and permissions
        // (Spatie's tables, keyed by school). Users are left alone: staff still change their own
        // password and two-factor while the school is read-only.
        array_push($tables, 'schools', 'roles', 'model_has_roles', 'model_has_permissions', 'role_has_permissions');

        return self::$tables = array_values(array_unique($tables));
    }
}
