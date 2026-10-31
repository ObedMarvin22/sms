<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A one-way notice from staff to parents (whole school or chosen streams). Never edited once sent.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property AnnouncementAudience $audience
 * @property int|null $sent_by
 * @property CarbonImmutable $sent_at
 * @property CarbonImmutable|null $withdrawn_at
 * @property int|null $withdrawn_by
 * @property-read User|null $sender
 * @property-read Collection<int, Stream> $streams
 */
class Announcement extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    use LogsActivity;

    /** @var list<string> */
    protected $fillable = ['title', 'body', 'audience'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Through the school-owned AnnouncementStream pivot, so links get school_id.
     *
     * @return BelongsToMany<Stream, $this, AnnouncementStream>
     */
    public function streams(): BelongsToMany
    {
        return $this->belongsToMany(Stream::class, 'announcement_streams')->using(AnnouncementStream::class)->withTimestamps();
    }

    /**
     * Not withdrawn.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('withdrawn_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('announcements')
            ->logOnly(['title', 'audience', 'withdrawn_at'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'sent_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }
}
