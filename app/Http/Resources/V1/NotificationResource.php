<?php

namespace App\Http\Resources\V1;

use App\Models\Notification;
use App\Notifications\AnnouncementPosted;
use App\Notifications\PaymentReceived;
use App\Notifications\StudentAbsent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An in-app notice. "kind" is a short name the app can choose an icon by.
 *
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->data;

        return [
            'id' => $this->id,
            'kind' => match ($this->type) {
                StudentAbsent::class => 'absence',
                PaymentReceived::class => 'payment',
                AnnouncementPosted::class => 'announcement',
                default => 'notice',
            },
            'message' => $data['message'] ?? '',
            'student_id' => $data['student_id'] ?? null,
            'announcement_id' => $data['announcement_id'] ?? null,
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
