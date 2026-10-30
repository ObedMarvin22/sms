<?php

namespace App\Notifications;

use App\Models\School;
use App\Models\User;
use App\Support\Invitations\InvitationLink;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites a staff member to set their password on their school's subdomain.
 */
class SchoolInvitation extends Notification
{
    public function __construct(public School $school) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You're invited to {$this->school->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been given an account for {$this->school->name} on Orange Tree.")
            ->action('Set your password', InvitationLink::url($notifiable, $this->school))
            ->line('This link works once and expires in '.InvitationLink::VALID_DAYS.' days.');
    }
}
