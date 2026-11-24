<?php

namespace App\Notifications;

use App\Models\ReportCard;
use Illuminate\Notifications\Notification;

/**
 * The parent app's notice that a child's report card has been published (in-app).
 */
class ReportCardPublished extends Notification
{
    public function __construct(public ReportCard $card) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->card->loadMissing(['student', 'term']);

        return [
            'student_id' => $this->card->student_id,
            'report_card_id' => $this->card->id,
            'message' => "{$this->card->student->first_name}'s report card for {$this->card->term->name} is ready.",
        ];
    }
}
