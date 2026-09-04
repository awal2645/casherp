<?php

namespace App\Notifications;

use App\ProcurementDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProcurementStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected ProcurementDocument $document,
        protected string $event,
        protected ?string $message = null
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $label = $this->document->document_type === 'requisition' ? 'Purchase requisition' : 'Purchase order';
        $reference = optional($this->document->transaction)->ref_no;
        $message = $this->message;
        if (! $message) {
            $message = $label.' '.$reference.' requires '.ucfirst((string) $this->document->current_stage).' approval.';
        }

        return [
            'msg' => $message,
            'icon_class' => 'fas fa-file-signature bg-blue',
            'link' => route('procurement.workflow.show', $this->document->transaction_id),
            'event' => $this->event,
            'business_id' => $this->document->business_id,
            'procurement_document_id' => $this->document->id,
        ];
    }
}
