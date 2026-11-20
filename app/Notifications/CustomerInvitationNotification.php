<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerInvitationNotification extends Notification
{
    public function __construct(public string $invitationId, public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your TheSoundMine invitation')
            ->line('You have been invited to create a TheSoundMine customer account.')
            ->action('Create your account', route('invitation.accept', ['invitation' => $this->invitationId, 'token' => $this->token]))
            ->line('This single-use invitation expires in 48 hours.');
    }
}
