<?php

namespace App\Notifications;

use App\Utils\NotificationUtil;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerNotification extends Notification
{
    use Queueable;

    protected $notificationInfo;

    protected $cc;

    protected $bcc;

    protected $attachment;

    protected $attachment_name;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($notificationInfo)
    {
        $this->notificationInfo = $notificationInfo;
        $this->cc = ! empty($notificationInfo['cc']) ? $notificationInfo['cc'] : null;
        $this->bcc = ! empty($notificationInfo['bcc']) ? $notificationInfo['bcc'] : null;
        $this->attachment = ! empty($notificationInfo['attachment']) ? $notificationInfo['attachment'] : null;
        $this->attachment_name = ! empty($notificationInfo['attachment_name']) ? $notificationInfo['attachment_name'] : null;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        // Configure at delivery time so queued notifications use the correct
        // tenant even when another company was processed by this worker first.
        (new NotificationUtil())->configureEmail($this->notificationInfo);
        $data = $this->notificationInfo;

        $mail = (new MailMessage)
                    ->subject($data['subject'])
                    ->view(
                        'emails.plain_html',
                        ['content' => $data['email_body']]
                    );
        if (! empty($this->cc)) {
            $mail->cc($this->cc);
        }
        if (! empty($this->bcc)) {
            $mail->bcc($this->bcc);
        }
        if (! empty($this->attachment)) {
            $mail->attach($this->attachment, ['as' => $this->attachment_name]);
        }

        if (! empty($data['pdf']) && ! empty($data['pdf_name'])) {
            $mail->attachData($data['pdf']->Output($data['pdf_name'], 'S'), $data['pdf_name'], [
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
