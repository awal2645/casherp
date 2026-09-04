<?php

namespace App\Notifications;

use App\BusinessDataImport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DataImportStatusNotification extends Notification
{
    use Queueable;

    public function __construct(private BusinessDataImport $import, private string $message)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'msg' => $this->message.' '.$this->import->original_name,
            'icon_class' => in_array($this->import->status, ['failed', 'rollback_failed'], true) ? 'fas fa-exclamation-triangle bg-red' : 'fas fa-file-import bg-blue',
            'link' => route('data-imports.show', $this->import),
            'business_id' => $this->import->business_id,
            'import_uuid' => $this->import->uuid,
            'status' => $this->import->status,
        ];
    }
}
