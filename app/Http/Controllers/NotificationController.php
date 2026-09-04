<?php

namespace App\Http\Controllers;

use App\Contact;
use App\Notifications\CustomerNotification;
use App\Notifications\SupplierNotification;
use App\NotificationTemplate;
use App\ProcurementDocument;
use App\Restaurant\Booking;
use App\Transaction;
use App\Utils\NotificationUtil;
use App\Utils\TransactionUtil;
use App\Services\TransactionDocumentAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Notification;

class NotificationController extends Controller
{
    protected $notificationUtil;

    protected $transactionUtil;

    /**
     * Constructor
     *
     * @param  NotificationUtil  $notificationUtil, TransactionUtil $transactionUtil
     * @return void
     */
    public function __construct(NotificationUtil $notificationUtil, TransactionUtil $transactionUtil)
    {
        $this->notificationUtil = $notificationUtil;
        $this->transactionUtil = $transactionUtil;
    }

    /**
     * Display a notification view.
     *
     * @return \Illuminate\Http\Response
     */
    public function getTemplate($id, $template_for)
    {
        $business_id = request()->session()->get('user.business_id');

        $notification_template = NotificationTemplate::getTemplate($business_id, $template_for);

        $contact = null;
        $transaction = null;
        if ($template_for == 'new_booking') {
            $transaction = Booking::where('business_id', $business_id)
                            ->with(['customer'])
                            ->find($id);

            $contact = $transaction->customer;
        } elseif ($template_for == 'send_ledger') {
            $contact = Contact::find($id);
        } else {
            $transaction = Transaction::where('business_id', $business_id)
                            ->when($template_for === 'purchase_order', fn ($query) => $query->where('type', 'purchase_order'))
                            ->with(['contact'])
                            ->findOrFail($id);

            if ($template_for === 'purchase_order') {
                $this->authorizePurchaseOrderRelease($transaction, $business_id);
            } elseif (array_key_exists($template_for, NotificationTemplate::customerNotifications())) {
                app(TransactionDocumentAccessService::class)->authorize(
                    $transaction,
                    request()->user(),
                    (int) $business_id,
                    'share'
                );
            }

            $contact = $transaction->contact;
        }

        $customer_notifications = NotificationTemplate::customerNotifications();
        $supplier_notifications = NotificationTemplate::supplierNotifications();
        $general_notifications = NotificationTemplate::generalNotifications();

        $template_name = '';

        $tags = [];
        if (array_key_exists($template_for, $customer_notifications)) {
            $template_name = $customer_notifications[$template_for]['name'];
            $tags = $customer_notifications[$template_for]['extra_tags'];
        } elseif (array_key_exists($template_for, $supplier_notifications)) {
            $template_name = $supplier_notifications[$template_for]['name'];
            $tags = $supplier_notifications[$template_for]['extra_tags'];
        } elseif (array_key_exists($template_for, $general_notifications)) {
            $template_name = $general_notifications[$template_for]['name'];
            $tags = $general_notifications[$template_for]['extra_tags'];
        }

        //for send_ledger notification template
        $start_date = request()->input('start_date');
        $end_date = request()->input('end_date');
        $ledger_format = request()->input('format');
        $location_id = request()->input('location_id');

        return view('notification.show_template')
                ->with(compact('notification_template', 'transaction', 'tags', 'template_name', 'contact', 'start_date', 'end_date', 'ledger_format', 'location_id'));
    }

    /**
     * Sends notifications to customer and supplier
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function send(Request $request)
    {
        // if (!auth()->user()->can('send_notification')) {
        //     abort(403, 'Unauthorized action.');
        // }
        $notAllowed = $this->notificationUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        $request->validate([
            'template_for' => ['required', 'string', 'max:100'],
            'transaction_id' => ['nullable', 'integer'],
            'notification_type' => ['required', 'array', 'min:1'],
            'notification_type.*' => ['required', Rule::in(['email', 'sms', 'whatsapp'])],
            'to_email' => ['nullable', 'string', 'max:2000'],
            'subject' => ['nullable', 'string', 'max:500'],
            'email_body' => ['nullable', 'string', 'max:50000'],
            'mobile_number' => ['nullable', 'string', 'max:50'],
            'sms_body' => ['nullable', 'string', 'max:5000'],
            'whatsapp_text' => ['nullable', 'string', 'max:5000'],
            'cc' => ['nullable', 'string', 'max:2000'],
            'bcc' => ['nullable', 'string', 'max:2000'],
        ]);
        $channels = $request->input('notification_type', []);
        $channelErrors = [];
        if (in_array('email', $channels, true)) {
            $emails = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('to_email')))));
            if (empty($emails) || count($emails) > 20 || collect($emails)->contains(fn ($email) => ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
                $channelErrors['to_email'] = 'Enter up to 20 valid comma-separated email addresses.';
            }
            if (! $request->filled('subject')) {
                $channelErrors['subject'] = 'Enter an email subject.';
            }
            if (! $request->filled('email_body')) {
                $channelErrors['email_body'] = 'Enter an email message.';
            }
        }
        if (in_array('sms', $channels, true) && (! $request->filled('mobile_number') || ! $request->filled('sms_body'))) {
            $channelErrors['sms_body'] = 'Enter a mobile number and SMS message.';
        }
        if (in_array('whatsapp', $channels, true) && (! $request->filled('mobile_number') || ! $request->filled('whatsapp_text'))) {
            $channelErrors['whatsapp_text'] = 'Enter a mobile number and WhatsApp message.';
        }
        if ($channelErrors) {
            throw ValidationException::withMessages($channelErrors);
        }

        $business_id = (int) request()->session()->get('user.business_id');
        if ($request->input('template_for') === 'purchase_order') {
            $purchaseOrder = Transaction::where('business_id', $business_id)
                ->where('type', 'purchase_order')
                ->findOrFail($request->integer('transaction_id'));
            $this->authorizePurchaseOrderRelease($purchaseOrder, $business_id);
        } elseif ($request->input('template_for') !== 'new_booking'
            && array_key_exists($request->input('template_for'), NotificationTemplate::customerNotifications())) {
            $sale = Transaction::where('business_id', $business_id)
                ->findOrFail($request->integer('transaction_id'));
            app(TransactionDocumentAccessService::class)->authorize(
                $sale,
                $request->user(),
                $business_id,
                'share'
            );
        }

        try {
            $customer_notifications = NotificationTemplate::customerNotifications();
            $supplier_notifications = NotificationTemplate::supplierNotifications();

            $data = $request->only(['to_email', 'subject', 'email_body', 'mobile_number', 'sms_body', 'notification_type', 'cc', 'bcc', 'whatsapp_text']);

            $emails_array = array_map('trim', explode(',', $data['to_email']));

            $transaction_id = $request->input('transaction_id');
            $transaction = ! empty($transaction_id)
                ? Transaction::where('business_id', $business_id)->findOrFail($transaction_id)
                : null;

            $orig_data = [
                'email_body' => $data['email_body'],
                'sms_body' => $data['sms_body'],
                'subject' => $data['subject'],
                'whatsapp_text' => $data['whatsapp_text'],
            ];

            if ($request->input('template_for') == 'new_booking') {
                $tag_replaced_data = $this->notificationUtil->replaceBookingTags($business_id, $orig_data, $transaction_id);

                $data['email_body'] = $tag_replaced_data['email_body'];
                $data['sms_body'] = $tag_replaced_data['sms_body'];
                $data['subject'] = $tag_replaced_data['subject'];
                $data['whatsapp_text'] = $tag_replaced_data['whatsapp_text'];
            } else {
                $tag_replaced_data = $this->notificationUtil->replaceTags($business_id, $orig_data, $transaction_id);

                $data['email_body'] = $tag_replaced_data['email_body'];
                $data['sms_body'] = $tag_replaced_data['sms_body'];
                $data['subject'] = $tag_replaced_data['subject'];
                $data['whatsapp_text'] = $tag_replaced_data['whatsapp_text'];
            }

            $data['email_settings'] = request()->session()->get('business.email_settings');

            $data['sms_settings'] = request()->session()->get('business.sms_settings');

            $notification_type = $request->input('notification_type');

            $whatsapp_link = '';
            if (array_key_exists($request->input('template_for'), $customer_notifications)) {
                if (in_array('email', $notification_type)) {
                    if (! empty($request->input('attach_pdf'))) {
                        $data['pdf_name'] = 'INVOICE-'.$transaction->invoice_no.'.pdf';
                        $data['pdf'] = $this->transactionUtil->getEmailAttachmentForGivenTransaction($business_id, $transaction_id, true);
                    }

                    Notification::route('mail', $emails_array)
                                    ->notify(new CustomerNotification($data));

                    if (! empty($transaction)) {
                        $this->notificationUtil->activityLog($transaction, 'email_notification_sent', null, [], false);
                    }
                }
                if (in_array('sms', $notification_type)) {
                    $this->notificationUtil->sendSms($data);

                    if (! empty($transaction)) {
                        $this->notificationUtil->activityLog($transaction, 'sms_notification_sent', null, [], false);
                    }
                }
                if (in_array('whatsapp', $notification_type)) {
                    $whatsapp_link = $this->notificationUtil->getWhatsappNotificationLink($data);
                }
            } elseif (array_key_exists($request->input('template_for'), $supplier_notifications)) {
                if (in_array('email', $notification_type)) {
                    if ($request->input('template_for') == 'purchase_order') {
                        $data['pdf_name'] = 'PO-'.$transaction->ref_no.'.pdf';
                        $data['pdf'] = $this->transactionUtil->getPurchaseOrderPdf($business_id, $transaction_id, true);
                    }
                    Notification::route('mail', $emails_array)
                                    ->notify(new SupplierNotification($data));

                    if (! empty($transaction)) {
                        $this->notificationUtil->activityLog($transaction, 'email_notification_sent', null, [], false);
                    }
                }
                if (in_array('sms', $notification_type)) {
                    $this->notificationUtil->sendSms($data);

                    if (! empty($transaction)) {
                        $this->notificationUtil->activityLog($transaction, 'sms_notification_sent', null, [], false);
                    }
                }
                if (in_array('whatsapp', $notification_type)) {
                    $whatsapp_link = $this->notificationUtil->getWhatsappNotificationLink($data);
                }
            }

            $output = ['success' => 1, 'msg' => __('lang_v1.notification_sent_successfully')];
            if (! empty($whatsapp_link)) {
                $output['whatsapp_link'] = $whatsapp_link;
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    private function authorizePurchaseOrderRelease(Transaction $transaction, int $businessId): void
    {
        $isAdmin = auth()->user()->can('superadmin')
            || auth()->user()->hasRole('Admin#'.$businessId);
        abort_unless(
            $isAdmin || auth()->user()->canForBusiness('purchase.document.share', $businessId),
            403,
            'You are not authorized to share purchase documents.'
        );
        if ($transaction->location_id) {
            $permitted = auth()->user()->permitted_locations($businessId);
            abort_unless(
                $permitted === 'all'
                    || in_array((int) $transaction->location_id, array_map('intval', (array) $permitted), true),
                403,
                'This purchase order is outside your permitted locations.'
            );
        }

        $workflowDocument = ProcurementDocument::where('business_id', $businessId)
            ->where('transaction_id', $transaction->id)
            ->first();
        $hasSubmitAccess = auth()->user()->canForBusiness('purchase_order.create', $businessId)
            && auth()->user()->canForBusiness('procurement.submit', $businessId);
        $ownsOrder = (int) $transaction->created_by === (int) auth()->id()
            || (int) optional($workflowDocument)->requested_by === (int) auth()->id();
        if (! $isAdmin
            && ! auth()->user()->canForBusiness('purchase_order.view_all', $businessId)
            && ((! auth()->user()->canForBusiness('purchase_order.view_own', $businessId) && ! $hasSubmitAccess) || ! $ownsOrder)) {
            abort(403, 'Unauthorized action.');
        }

        $workflowStatus = optional($workflowDocument)->approval_status;
        if ($workflowStatus && $workflowStatus !== 'approved') {
            abort(409, 'This purchase order cannot be sent before final approval.');
        }
    }
}
