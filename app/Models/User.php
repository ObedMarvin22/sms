<?php

namespace App\Models;

use App\Enums\PlatformRoleKey;
use App\Enums\RoleKey;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $school_id Null for Orange Tree platform users.
 * @property PlatformRoleKey|null $platform_role Only for platform users.
 * @property CarbonImmutable|null $deactivated_at Set when the user may no longer sign in.
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read School|null $school
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Spatie roles are school roles on the "web" guard, whichever guard is active
     * (e.g. a console super admin assigning a school's first administrator).
     */
    protected string $guard_name = 'web';

    protected static function booted(): void
    {
        // The password itself is never logged, only that it changed.
        static::updated(function (User $user): void {
            if ($user->wasChanged('password')) {
                activity('users')->performedOn($user)->event('password_changed')->log('password changed');
            }
        });
    }

    /**
     * Audit trail: identity and security fields only. Secrets are never logged.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'email', 'platform_role', 'two_factor_confirmed_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'platform_role' => PlatformRoleKey::class,
            'deactivated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Whether any of this user's roles in the current school requires two-factor (SPEC section 5).
     */
    public function requiresTwoFactor(): bool
    {
        return $this->getRoleNames()
            ->contains(fn (string $name) => RoleKey::tryFrom($name)?->requiresTwoFactor() ?? false);
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * The school this user belongs to (null for platform users).
     *
     * school_id and platform_role are deliberately not mass-assignable: set them explicitly.
     *
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
