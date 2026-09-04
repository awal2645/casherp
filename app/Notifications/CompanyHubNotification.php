<?php

namespace App\Notifications;

use App\CompanyHubPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyHubNotification extends Notification
{
    use Queueable;

    public function __construct(private CompanyHubPost $post, private bool $emailEnabled = false)
    {
    }

    public function via($notifiable): array
    {
        $channels = ['database'];
        if ($this->emailEnabled && ! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray($notifiable): array
    {
        $label = $this->post->type === 'announcement' ? 'New company announcement' : 'New Company Hub post';

        return [
            'msg' => $label.': '.($this->post->title ?: str($this->post->body)->limit(80)),
            'icon_class' => $this->post->priority === 'urgent' ? 'fas fa-bullhorn bg-red' : 'fas fa-comments bg-blue',
            'link' => route('company-hub.index', ['post' => $this->post->uuid]),
            'business_id' => $this->post->business_id,
            'company_hub_post_uuid' => $this->post->uuid,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('CashERP company announcement: '.($this->post->title ?: 'New update'))
            ->greeting('Hello '.($notifiable->first_name ?: 'team member').',')
            ->line('A new company announcement requires your attention in CashERP.')
            ->action('Open Company Hub', route('company-hub.index', ['post' => $this->post->uuid]))
            ->line('Sign in to view the full message and record any required acknowledgement.');
    }
}
