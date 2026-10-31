<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AnnouncementStreamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * One stream an announcement went to. A pivot model so it gets school_id (BelongsToSchool).
 *
 * @property int $id
 * @property int $announcement_id
 * @property int $stream_id
 */
class AnnouncementStream extends Pivot
{
    use BelongsToSchool;

    /** @use HasFactory<AnnouncementStreamFactory> */
    use HasFactory;

    public $incrementing = true;

    protected $table = 'announcement_streams';

    /** @var list<string> */
    protected $fillable = ['announcement_id', 'stream_id'];
}
