<?php

namespace App\Notifications;

use App\PropertyViewingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PropertyViewingStatusNotification extends Notification
{
    use Queueable;

    public function __construct(private PropertyViewingRequest $viewing, private string $message)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'msg' => $this->message.' '.$this->viewing->requester_name.' — '.optional($this->viewing->property)->name,
            'icon_class' => 'fas fa-calendar-check bg-blue',
            'link' => route('property.viewings.index', ['status' => $this->viewing->status]),
            'business_id' => $this->viewing->business_id,
            'viewing_uuid' => $this->viewing->uuid,
        ];
    }
}
