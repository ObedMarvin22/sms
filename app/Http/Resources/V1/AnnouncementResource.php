<?php

namespace App\Http\Resources\V1;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An announcement for the parent app. "concerns" names the parent's children it was sent for
 * (empty for whole-school announcements). The sender is not shown: it comes from the school.
 *
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'whole_school' => $this->audience->value === 'school',
            'concerns' => $this->getAttribute('concerns') ?? [],
            'sent_at' => $this->sent_at->toIso8601String(),
        ];
    }
}
