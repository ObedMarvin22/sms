<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\AnnouncementStream;
use App\Models\SchoolClass;
use App\Models\Stream;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the announcement and stream are created in that school.
 *
 * @extends Factory<AnnouncementStream>
 */
class AnnouncementStreamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'announcement_id' => fn (array $a) => Announcement::factory()->create(['school_id' => $a['school_id']])->id,
            'stream_id' => fn (array $a) => Stream::factory()->create([
                'school_id' => $a['school_id'],
                'school_class_id' => SchoolClass::factory()->create(['school_id' => $a['school_id']])->id,
            ])->id,
        ];
    }
}
