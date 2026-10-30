<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A parent or guardian in one school, identified by their phone.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $phone
 * @property string|null $alt_phone
 * @property string|null $email
 * @property string|null $occupation
 * @property string|null $address
 */
#[Fillable(['name', 'phone', 'alt_phone', 'email', 'occupation', 'address'])]
class Guardian extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GuardianFactory> */
    use HasFactory;

    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    /**
     * Links to this guardian's children.
     *
     * @return HasMany<GuardianStudent, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(GuardianStudent::class);
    }

    /**
     * The parent-app login, once activated (Sprint 4).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * In-app notifications, kept on the guardian so the parent app (Sprint 4) can show them
     * whether or not the guardian has a login yet. Uses the school-owned Notification model.
     *
     * @return MorphMany<Notification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    /**
     * The number SMS notices go to (SmsChannel).
     */
    public function routeNotificationForSms(): string
    {
        return $this->phone;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('guardians')
            ->logOnly(['name', 'phone', 'alt_phone', 'email', 'occupation', 'address', 'user_id'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
