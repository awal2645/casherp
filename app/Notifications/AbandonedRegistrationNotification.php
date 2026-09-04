<?php

namespace App\Notifications;

use App\RegistrationIntent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class AbandonedRegistrationNotification extends Notification
{
    use Queueable;

    public function __construct(private RegistrationIntent $intent)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'landing.registration.resume',
            now()->addDays(30),
            ['intent' => $this->intent->uuid]
        );
        $stopUrl = URL::temporarySignedRoute(
            'landing.registration-reminders.stop',
            now()->addDays(30),
            ['intent' => $this->intent->uuid]
        );

        return (new MailMessage())
            ->subject('Continue setting up your CashERP company')
            ->greeting('Hello,')
            ->line('You started setting up '.($this->intent->business_name ?: 'a company').' in CashERP but did not finish registration.')
            ->line('Your saved company profile can be reviewed before you choose a package and create the account.')
            ->action('Continue registration', $url)
            ->line('CashERP sends no more than two setup reminders for this registration.')
            ->line('[Stop registration reminders]('.$stopUrl.')');
    }
}
