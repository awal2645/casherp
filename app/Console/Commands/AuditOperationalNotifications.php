<?php

namespace App\Console\Commands;

use App\NotificationDelivery;
use App\NotificationEvent;
use App\Notifications\AbandonedRegistrationNotification;
use App\RegistrationIntent;
use App\Services\NotificationOrchestratorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AuditOperationalNotifications extends Command
{
    protected $signature = 'casherp:audit-notifications {--dry-run : Inspect due records without recording or delivering events}';

    protected $description = 'Create deduplicated CashERP alerts for overdue, expiring and abandoned workflows';

    private int $examined = 0;
    private int $emitted = 0;

    private int $pruned = 0;

    private int $resolved = 0;

    /** @var array<string, bool> */
    private array $activeSubjects = [];

    /** @var array<int, string> */
    private array $coveredEventKeys = [];

    public function __construct(private NotificationOrchestratorService $notifications)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('notification_events')) {
            $this->warn('Notification governance migration is not installed; no alerts were processed.');

            return self::SUCCESS;
        }

        $scanners = [
            'registration recovery' => fn () => $this->scanAbandonedRegistrations(),
            'subscriptions and trials' => fn () => $this->scanSubscriptions(),
            'sales and purchases' => fn () => $this->scanFinancialDocuments(),
            'inventory' => fn () => $this->scanInventory(),
            'procurement' => fn () => $this->scanProcurement(),
            'restaurant operations' => fn () => $this->scanRestaurant(),
            'hospitality' => fn () => $this->scanHospitality(),
            'property management' => fn () => $this->scanProperty(),
            'security deposits' => fn () => $this->scanSecurityDeposits(),
            'human resources' => fn () => $this->scanHumanResources(),
            'CRM, projects and intranet' => fn () => $this->scanSharedWork(),
            'imports and background work' => fn () => $this->scanSystemWork(),
        ];

        $coverage = [
            'subscriptions and trials' => ['trial.expiring', 'trial.expired', 'subscription.expiring', 'subscription.expired', 'subscription.payment_failed'],
            'sales and purchases' => ['sales.invoice_overdue', 'purchases.payment_overdue'],
            'inventory' => ['inventory.low_stock'],
            'procurement' => ['procurement.approval_overdue', 'procurement.delivery_overdue'],
            'restaurant operations' => ['restaurant.order_delayed', 'restaurant.waiter_request_overdue', 'restaurant.cash_reconciliation_pending', 'restaurant.reservation_attention'],
            'hospitality' => ['hospitality.checkin_upcoming', 'hospitality.arrival_overdue', 'hospitality.checkout_overdue', 'hospitality.housekeeping_overdue', 'hospitality.group_hold_expiring'],
            'property management' => ['property.rent_overdue', 'property.lease_expiring', 'property.lease_expired', 'property.maintenance_overdue', 'property.viewing_upcoming'],
            'security deposits' => ['deposit.security_pending', 'deposit.refund_overdue'],
            'human resources' => ['hr.contract_expiring', 'hr.leave_pending', 'hr.payroll_action_required'],
            'CRM, projects and intranet' => ['crm.activity_overdue', 'project.task_overdue', 'company_hub.acknowledgement_overdue'],
            'imports and background work' => ['data_import.failed'],
        ];

        foreach ($scanners as $label => $scanner) {
            try {
                $scanner();
                $this->coveredEventKeys = array_values(array_unique(array_merge(
                    $this->coveredEventKeys,
                    $coverage[$label] ?? []
                )));
            } catch (\Throwable $exception) {
                Log::error('CashERP notification audit scanner failed.', [
                    'scanner' => $label,
                    'exception' => $exception->getMessage(),
                ]);
                $this->error($label.': '.$exception->getMessage());
            }
        }

        $this->reconcileResolvedEvents();
        $this->cleanupRetainedData();

        $verb = $this->option('dry-run') ? 'would emit' : 'emitted or deduplicated';
        $this->info("Notification audit examined {$this->examined} actionable records, {$verb} {$this->emitted} events, resolved {$this->resolved} cleared alerts and pruned {$this->pruned} expired governance records.");

        return self::SUCCESS;
    }

    private function scanAbandonedRegistrations(): void
    {
        if (! Schema::hasTable('registration_intents')) {
            return;
        }

        RegistrationIntent::query()
            ->where('reminder_consent', true)
            ->whereNull('completed_at')
            ->whereNotNull('next_reminder_at')
            ->where('next_reminder_at', '<=', now())
            ->where('reminder_count', '<', 2)
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->each(function (RegistrationIntent $intent) {
                $this->examined++;
                if ($this->option('dry-run')) {
                    $this->emitted++;
                    return;
                }

                $event = $this->notifications->dispatch('registration.abandoned', [
                    'subject_type' => RegistrationIntent::class,
                    'subject_id' => $intent->id,
                    'title' => 'Registration not completed',
                    'message' => 'A consented company registration was started but not completed.',
                    'action_url' => URL::temporarySignedRoute(
                        'landing.registration.resume',
                        now()->addDays(30),
                        ['intent' => $intent->uuid]
                    ),
                    'bucket' => 'reminder-'.($intent->reminder_count + 1),
                    'metadata' => ['last_step' => $intent->last_step],
                ]);

                if (! $event) {
                    return;
                }

                $existing = NotificationDelivery::where('notification_event_id', $event->id)
                    ->whereNull('user_id')
                    ->where('recipient_email', $intent->email)
                    ->where('channel', 'mail')
                    ->first();
                if ($existing?->status === 'sent' || ($existing?->status === 'failed' && (int) $existing->attempts >= 5)) {
                    return;
                }

                $delivery = $existing ?: NotificationDelivery::create([
                    'notification_event_id' => $event->id,
                    'recipient_email' => $intent->email,
                    'channel' => 'mail',
                    'status' => 'pending',
                ]);
                $delivery->increment('attempts');

                try {
                    Notification::route('mail', $intent->email)->notify(new AbandonedRegistrationNotification($intent));
                    $delivery->update(['status' => 'sent', 'sent_at' => now(), 'failed_at' => null, 'next_retry_at' => null, 'last_error' => null]);
                    $intent->increment('reminder_count');
                    $intent->forceFill([
                        'reminder_sent_at' => now(),
                        'next_reminder_at' => $intent->reminder_count < 2 ? now()->addDays(3) : null,
                    ])->save();
                    $this->emitted++;
                } catch (\Throwable $exception) {
                    $attempts = (int) $delivery->fresh()->attempts;
                    $delivery->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                        'next_retry_at' => $attempts < 5 ? now()->addMinutes(min(720, 15 * (2 ** max(0, $attempts - 1)))) : null,
                        'last_error' => Str::limit($exception->getMessage(), 2000, ''),
                    ]);
                    $intent->update(['next_reminder_at' => $attempts < 5 ? now()->addHours(6) : null]);
                    Log::error('Abandoned registration reminder failed.', [
                        'registration_intent_uuid' => $intent->uuid,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });
    }

    private function scanSubscriptions(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        $today = now()->startOfDay();
        DB::table('subscriptions')
            ->where('status', 'approved')
            ->whereNull('covered_by_subscription_id')
            ->whereNull('deleted_at')
            ->whereNotNull('trial_end_date')
            ->whereBetween('trial_end_date', [$today->copy()->subDay()->toDateString(), $today->copy()->addDays(3)->toDateString()])
            ->orderBy('id')->limit(500)->get()
            ->each(function ($subscription) use ($today) {
                $trialEnd = Carbon::parse($subscription->trial_end_date)->startOfDay();
                $expired = $trialEnd->lt($today);
                $this->emit($expired ? 'trial.expired' : 'trial.expiring', [
                    'business_id' => $subscription->business_id,
                    'subject_type' => 'subscription',
                    'subject_id' => $subscription->id,
                    'title' => $expired ? 'Trial expired' : 'Trial ends soon',
                    'message' => $expired
                        ? 'The company trial has ended. Review a published package to restore subscribed access.'
                        : 'The company trial ends '.$trialEnd->diffForHumans().'. Review the package and billing details before access changes.',
                    'action_url' => url('/subscription'),
                    'due_at' => $trialEnd,
                    'bucket' => $expired ? 'expired-'.$trialEnd->toDateString() : 'expiring-'.$trialEnd->toDateString(),
                ]);
            });

        DB::table('subscriptions as current')
            ->where('current.status', 'approved')
            ->whereNull('current.covered_by_subscription_id')
            ->whereNull('current.deleted_at')
            ->whereBetween('current.end_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
            ->where(function ($query) use ($today) {
                $query->whereNull('current.trial_end_date')
                    ->orWhereDate('current.trial_end_date', '<', $today);
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))->from('subscriptions as renewal')
                    ->whereColumn('renewal.business_id', 'current.business_id')
                    ->where('renewal.status', 'approved')
                    ->whereNull('renewal.covered_by_subscription_id')
                    ->whereNull('renewal.deleted_at')
                    ->whereColumn('renewal.end_date', '>', 'current.end_date');
            })
            ->orderBy('current.id')->limit(500)->get(['current.*'])
            ->each(function ($subscription) {
                $end = Carbon::parse($subscription->end_date);
                $this->emit('subscription.expiring', [
                    'business_id' => $subscription->business_id,
                    'subject_type' => 'subscription',
                    'subject_id' => $subscription->id,
                    'title' => 'Subscription renewal due soon',
                    'message' => 'The company subscription ends '.$end->diffForHumans().'. Review the package, payment method and renewal details before access changes.',
                    'action_url' => url('/subscription'),
                    'due_at' => $end,
                    'bucket' => $end->toDateString(),
                ]);
            });

        DB::table('subscriptions as current')
            ->where('current.status', 'approved')
            ->whereNull('current.covered_by_subscription_id')
            ->whereNull('current.deleted_at')
            ->whereDate('current.end_date', '<', $today)
            ->whereDate('current.end_date', '>=', $today->copy()->subDays(30))
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))->from('subscriptions as renewal')
                    ->whereColumn('renewal.business_id', 'current.business_id')
                    ->where('renewal.status', 'approved')
                    ->whereNull('renewal.covered_by_subscription_id')
                    ->whereNull('renewal.deleted_at')
                    ->whereDate('renewal.end_date', '>=', now()->toDateString());
            })
            ->orderBy('current.id')->limit(500)->get(['current.*'])
            ->each(function ($subscription) {
                $end = Carbon::parse($subscription->end_date);
                $this->emit('subscription.expired', [
                    'business_id' => $subscription->business_id,
                    'subject_type' => 'subscription',
                    'subject_id' => $subscription->id,
                    'title' => 'Subscription expired',
                    'message' => 'The company subscription expired on '.$end->toFormattedDateString().'. Review billing before continuing subscribed features.',
                    'action_url' => url('/subscription'),
                    'due_at' => $end,
                    'bucket' => $end->toDateString(),
                ]);
            });

        if (Schema::hasTable('subscription_payment_attempts')) {
            DB::table('subscription_payment_attempts')
                ->where('status', 'failed')
                ->where('updated_at', '>=', now()->subDays(30))
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))->from('subscription_payment_attempts as paid')
                        ->whereColumn('paid.business_id', 'subscription_payment_attempts.business_id')
                        ->whereColumn('paid.created_at', '>=', 'subscription_payment_attempts.created_at')
                        ->where('paid.status', 'paid');
                })
                ->orderBy('id')->limit(500)->get()
                ->each(function ($attempt) {
                    $this->emit('subscription.payment_failed', [
                        'business_id' => $attempt->business_id,
                        'subject_type' => 'subscription_payment_attempt',
                        'subject_id' => $attempt->id,
                        'title' => 'Subscription payment needs attention',
                        'message' => 'Payment attempt '.$attempt->reference.' was not completed. Review the payment result before retrying or changing the company subscription.',
                        'action_url' => url('/subscription'),
                        'due_at' => $attempt->updated_at,
                        'bucket' => 'failed-'.Carbon::parse($attempt->updated_at)->toDateString(),
                        'metadata' => ['recipient_user_ids' => array_values(array_filter([(int) ($attempt->user_id ?? 0)]))],
                    ]);
                });
        }
    }

    private function scanFinancialDocuments(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        DB::table('transactions')
            ->whereIn('type', ['sell', 'purchase'])
            ->where('status', 'final')
            ->whereIn('payment_status', ['due', 'partial'])
            ->whereNull('deleted_at')
            ->whereDate('transaction_date', '<=', now()->subDays(7))
            ->orderBy('id')->limit(1000)
            ->get(['id', 'business_id', 'type', 'invoice_no', 'ref_no', 'transaction_date', 'invoice_term', 'invoice_term_type'])
            ->each(function ($transaction) {
                $due = Carbon::parse($transaction->transaction_date);
                $term = max(0, (int) ($transaction->invoice_term ?? 0));
                $due = ($transaction->invoice_term_type ?? 'days') === 'months' ? $due->addMonths($term) : $due->addDays($term);
                if ($term === 0) {
                    $due = Carbon::parse($transaction->transaction_date)->addDays(7);
                }
                if ($due->isFuture()) {
                    return;
                }

                $sale = $transaction->type === 'sell';
                $reference = $sale ? ($transaction->invoice_no ?: '#'.$transaction->id) : ($transaction->ref_no ?: '#'.$transaction->id);
                $this->emit($sale ? 'sales.invoice_overdue' : 'purchases.payment_overdue', [
                    'business_id' => $transaction->business_id,
                    'subject_type' => 'transaction',
                    'subject_id' => $transaction->id,
                    'title' => $sale ? 'Customer invoice overdue' : 'Supplier payment overdue',
                    'message' => ($sale ? 'Invoice ' : 'Purchase ').$reference.' remains unpaid after its due date.',
                    'action_url' => url($sale ? '/sells?payment_status=due' : '/purchases?payment_status=due'),
                    'due_at' => $due,
                    'bucket' => $due->toDateString(),
                ]);
            });
    }

    private function scanProcurement(): void
    {
        if (! Schema::hasTable('procurement_documents')) {
            return;
        }

        DB::table('procurement_documents')
            ->whereIn('approval_status', ['pending_manager', 'pending_finance', 'pending_admin'])
            ->where('updated_at', '<=', now()->subDay())
            ->orderBy('id')->limit(500)->get()
            ->each(function ($document) {
                $this->emit('procurement.approval_overdue', [
                    'business_id' => $document->business_id,
                    'subject_type' => 'procurement_document',
                    'subject_id' => $document->id,
                    'title' => 'Procurement approval overdue',
                    'message' => Str::headline($document->document_type).' has waited more than 24 hours at the '.Str::headline((string) $document->current_stage).' approval stage.',
                    'action_url' => url('/procurement/workflows/'.$document->transaction_id),
                    'due_at' => Carbon::parse($document->updated_at)->addDay(),
                    'bucket' => $document->approval_status.'-'.Carbon::parse($document->updated_at)->toDateString(),
                ]);
            });

        if (Schema::hasTable('procurement_supplier_quotes')) {
            DB::table('procurement_supplier_quotes as quote')
                ->join('procurement_documents as document', 'document.transaction_id', '=', 'quote.requisition_transaction_id')
                ->where('quote.status', 'selected')
                ->whereNotNull('quote.delivery_date')
                ->whereDate('quote.delivery_date', '<', now())
                ->whereNotIn('document.approval_status', ['cancelled', 'rejected'])
                ->limit(500)->get(['quote.id', 'quote.business_id', 'quote.delivery_date', 'document.transaction_id'])
                ->each(function ($quote) {
                    $this->emit('procurement.delivery_overdue', [
                        'business_id' => $quote->business_id,
                        'subject_type' => 'procurement_supplier_quote',
                        'subject_id' => $quote->id,
                        'title' => 'Supplier delivery overdue',
                        'message' => 'The selected supplier quotation has passed its expected delivery date and needs receipt or follow-up.',
                        'action_url' => url('/procurement/workflows/'.$quote->transaction_id),
                        'due_at' => $quote->delivery_date,
                        'bucket' => (string) $quote->delivery_date,
                    ]);
                });
        }
    }

    private function scanInventory(): void
    {
        if (! Schema::hasTable('variation_location_details') || ! Schema::hasTable('products')) {
            return;
        }

        DB::table('variation_location_details as stock')
            ->join('products as product', 'product.id', '=', 'stock.product_id')
            ->where('product.enable_stock', 1)
            ->where('product.is_inactive', 0)
            ->whereNotNull('product.alert_quantity')
            ->whereColumn('stock.qty_available', '<=', 'product.alert_quantity')
            ->groupBy('product.business_id')
            ->orderBy('product.business_id')
            ->limit(500)
            ->get([
                'product.business_id',
                DB::raw('COUNT(*) as low_stock_count'),
                DB::raw('MIN(stock.qty_available) as lowest_quantity'),
            ])
            ->each(function ($summary) {
                $count = (int) $summary->low_stock_count;
                $this->emit('inventory.low_stock', [
                    'business_id' => $summary->business_id,
                    'subject_type' => 'business_inventory',
                    'subject_id' => $summary->business_id,
                    'title' => $count === 1 ? 'One stock item needs attention' : $count.' stock items need attention',
                    'message' => 'Available quantity is at or below the configured reorder level. Review stock by location before accepting or fulfilling new orders.',
                    'action_url' => url('/reports/stock-report'),
                    'bucket' => now()->startOfWeek()->toDateString(),
                    'metadata' => ['low_stock_count' => $count],
                ]);
            });
    }

    private function scanRestaurant(): void
    {
        if (Schema::hasTable('restaurant_kitchen_tickets')) {
            DB::table('restaurant_kitchen_tickets as ticket')
                ->leftJoin('restaurant_kitchen_stations as station', 'station.id', '=', 'ticket.station_id')
                ->whereIn('ticket.status', ['queued', 'accepted', 'started'])
                ->where('ticket.created_at', '<=', now()->subMinutes(10))
                ->limit(500)->get(['ticket.id', 'ticket.business_id', 'ticket.ticket_number', 'ticket.created_at', 'ticket.started_at', 'station.service_level_minutes'])
                ->each(function ($ticket) {
                    $started = Carbon::parse($ticket->started_at ?: $ticket->created_at);
                    $limit = max(5, (int) ($ticket->service_level_minutes ?: 15));
                    if ($started->copy()->addMinutes($limit)->isFuture()) {
                        return;
                    }
                    $this->emit('restaurant.order_delayed', [
                        'business_id' => $ticket->business_id,
                        'subject_type' => 'restaurant_kitchen_ticket',
                        'subject_id' => $ticket->id,
                        'title' => 'Kitchen order delayed',
                        'message' => 'Kitchen ticket '.$ticket->ticket_number.' has exceeded its '.$limit.'-minute service target.',
                        'action_url' => url('/restaurant-operations/kitchen-board'),
                        'due_at' => $started->addMinutes($limit),
                        'bucket' => 'service-delay',
                    ]);
                });
        }

        if (Schema::hasTable('restaurant_waiter_requests')) {
            DB::table('restaurant_waiter_requests')->where('status', 'open')->where('created_at', '<=', now()->subMinutes(10))
                ->limit(500)->get()->each(function ($request) {
                    $this->emit('restaurant.waiter_request_overdue', [
                        'business_id' => $request->business_id,
                        'subject_type' => 'restaurant_waiter_request',
                        'subject_id' => $request->id,
                        'title' => 'Guest request awaiting attention',
                        'message' => 'An open '.Str::headline($request->request_type).' request has waited more than 10 minutes.',
                        'action_url' => url('/restaurant-operations/waiter-requests'),
                        'due_at' => Carbon::parse($request->created_at)->addMinutes(10),
                        'bucket' => 'open-request',
                    ]);
                });
        }

        if (Schema::hasTable('restaurant_register_reconciliations')) {
            DB::table('restaurant_register_reconciliations')->where('status', 'submitted')->where('submitted_at', '<=', now()->subHour())
                ->limit(200)->get()->each(function ($reconciliation) {
                    $this->emit('restaurant.cash_reconciliation_pending', [
                        'business_id' => $reconciliation->business_id,
                        'subject_type' => 'restaurant_register_reconciliation',
                        'subject_id' => $reconciliation->id,
                        'title' => 'Cash reconciliation needs review',
                        'message' => 'A submitted restaurant cash reconciliation has waited more than one hour for review.',
                        'action_url' => url('/restaurant-operations/register-control'),
                        'due_at' => Carbon::parse($reconciliation->submitted_at)->addHour(),
                        'bucket' => 'submitted',
                    ]);
                });
        }

        if (Schema::hasTable('restaurant_booking_details') && Schema::hasTable('bookings')) {
            DB::table('restaurant_booking_details as detail')
                ->join('bookings as booking', 'booking.id', '=', 'detail.booking_id')
                ->whereIn('detail.status', ['confirmed', 'waitlisted'])
                ->whereNull('detail.arrived_at')
                ->whereBetween('booking.booking_start', [now()->subHours(12), now()->subMinutes(30)])
                ->orderBy('detail.id')->limit(500)
                ->get(['detail.id', 'detail.business_id', 'detail.status', 'detail.created_at', 'booking.booking_start'])
                ->each(function ($reservation) {
                    $this->emit('restaurant.reservation_attention', [
                        'business_id' => $reservation->business_id,
                        'subject_type' => 'restaurant_booking_detail',
                        'subject_id' => $reservation->id,
                        'title' => 'Restaurant reservation needs a status update',
                        'message' => 'A confirmed or waitlisted reservation is more than 30 minutes past its booked time without an arrival. Confirm arrival, completion, cancellation or no-show status.',
                        'action_url' => url('/bookings'),
                        'due_at' => $reservation->booking_start,
                        'bucket' => Carbon::parse($reservation->booking_start)->toDateString(),
                    ]);
                });
        }
    }

    private function scanHospitality(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'hms_booking_status')) {
            return;
        }

        DB::table('transactions')->where('type', 'hms_booking')->whereNull('deleted_at')
            ->whereIn('hms_booking_status', ['reserved', 'checked_in'])
            ->where(function ($query) {
                $query->whereBetween('hms_booking_arrival_date_time', [now(), now()->addHours(24)])
                    ->orWhere('hms_booking_departure_date_time', '<', now());
            })->limit(500)->get()
            ->each(function ($booking) {
                if ($booking->hms_booking_status === 'reserved' && $booking->hms_booking_arrival_date_time) {
                    $arrival = Carbon::parse($booking->hms_booking_arrival_date_time);
                    $this->emit('hospitality.checkin_upcoming', [
                        'business_id' => $booking->business_id,
                        'subject_type' => 'hms_booking',
                        'subject_id' => $booking->id,
                        'title' => 'Guest arrival within 24 hours',
                        'message' => 'Booking '.($booking->ref_no ?: '#'.$booking->id).' is due to arrive within 24 hours. Review room readiness and payment status.',
                        'action_url' => url('/hms/front-desk'),
                        'due_at' => $arrival,
                        'bucket' => $arrival->toDateString(),
                    ]);
                }
                if ($booking->hms_booking_status === 'checked_in' && $booking->hms_booking_departure_date_time && Carbon::parse($booking->hms_booking_departure_date_time)->isPast()) {
                    $departure = Carbon::parse($booking->hms_booking_departure_date_time);
                    $this->emit('hospitality.checkout_overdue', [
                        'business_id' => $booking->business_id,
                        'subject_type' => 'hms_booking',
                        'subject_id' => $booking->id,
                        'title' => 'Guest checkout overdue',
                        'message' => 'Booking '.($booking->ref_no ?: '#'.$booking->id).' remains checked in after the planned departure time.',
                        'action_url' => url('/hms/front-desk'),
                        'due_at' => $departure,
                        'bucket' => $departure->toDateString(),
                    ]);
                }
            });

        DB::table('transactions')->where('type', 'hms_booking')->whereNull('deleted_at')
            ->where('hms_booking_status', 'reserved')
            ->whereNotNull('hms_booking_arrival_date_time')
            ->whereBetween('hms_booking_arrival_date_time', [now()->subHours(24), now()->subHours(2)])
            ->orderBy('id')->limit(500)->get()
            ->each(function ($booking) {
                $arrival = Carbon::parse($booking->hms_booking_arrival_date_time);
                $this->emit('hospitality.arrival_overdue', [
                    'business_id' => $booking->business_id,
                    'subject_type' => 'hms_booking',
                    'subject_id' => $booking->id,
                    'title' => 'Guest arrival needs a status decision',
                    'message' => 'Booking '.($booking->ref_no ?: '#'.$booking->id).' is still reserved more than two hours after its arrival time. Confirm check-in, cancellation or no-show status.',
                    'action_url' => url('/hms/front-desk'),
                    'due_at' => $arrival,
                    'bucket' => $arrival->toDateString(),
                ]);
            });

        if (Schema::hasTable('hms_operational_tasks')) {
            DB::table('hms_operational_tasks')->whereIn('status', ['open', 'assigned', 'in_progress'])
                ->whereNotNull('due_at')->where('due_at', '<', now())->limit(500)->get()
                ->each(function ($task) {
                    $this->emit('hospitality.housekeeping_overdue', [
                        'business_id' => $task->business_id,
                        'subject_type' => 'hms_operational_task',
                        'subject_id' => $task->id,
                        'title' => 'Hotel service task overdue',
                        'message' => Str::headline($task->task_type ?? 'Hotel').' task has passed its due time.',
                        'action_url' => url('/hms/hotel-services'),
                        'due_at' => $task->due_at,
                        'bucket' => Carbon::parse($task->due_at)->toDateString(),
                        'metadata' => ['recipient_user_ids' => array_values(array_filter([(int) ($task->assigned_to ?? 0)]))],
                    ]);
                });
        }

        if (Schema::hasTable('hms_group_bookings')) {
            DB::table('hms_group_bookings')->where('status', 'tentative')->whereNotNull('release_at')
                ->whereBetween('release_at', [now(), now()->addDay()])->limit(200)->get()
                ->each(function ($group) {
                    $this->emit('hospitality.group_hold_expiring', [
                        'business_id' => $group->business_id,
                        'subject_type' => 'hms_group_booking',
                        'subject_id' => $group->id,
                        'title' => 'Group room hold expires soon',
                        'message' => 'Group booking '.$group->code.' will release held inventory within 24 hours unless it is confirmed.',
                        'action_url' => url('/hms/groups'),
                        'due_at' => $group->release_at,
                        'bucket' => Carbon::parse($group->release_at)->toDateString(),
                    ]);
                });
        }
    }

    private function scanProperty(): void
    {
        if (Schema::hasTable('property_rent_dues')) {
            DB::table('property_rent_dues')->whereIn('status', ['due', 'partial'])->whereDate('due_date', '<', now())
                ->limit(1000)->get()->each(function ($due) {
                    $this->emit('property.rent_overdue', [
                        'business_id' => $due->business_id,
                        'subject_type' => 'property_rent_due',
                        'subject_id' => $due->id,
                        'title' => 'Rent payment overdue',
                        'message' => 'A rent schedule remains unpaid or partially paid after its due date.',
                        'action_url' => url('/property-management/rent-ledger'),
                        'due_at' => $due->due_date,
                        'bucket' => (string) $due->due_date,
                    ]);
                });
        }

        if (Schema::hasTable('property_leases')) {
            DB::table('property_leases')->where('status', 'active')->whereNotNull('end_date')
                ->whereBetween('end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->limit(500)->get()->each(function ($lease) {
                    $this->emit('property.lease_expiring', [
                        'business_id' => $lease->business_id,
                        'subject_type' => 'property_lease',
                        'subject_id' => $lease->id,
                        'title' => 'Lease expires within 30 days',
                        'message' => 'An active lease is approaching its end date. Review renewal, handover, balances and deposit settlement.',
                        'action_url' => url('/property-management/leases'),
                        'due_at' => $lease->end_date,
                        'bucket' => (string) $lease->end_date,
                    ]);
                });

            DB::table('property_leases')->where('status', 'active')->whereNotNull('end_date')
                ->whereDate('end_date', '<', now()->toDateString())
                ->whereDate('end_date', '>=', now()->subDays(90)->toDateString())
                ->limit(500)->get()->each(function ($lease) {
                    $this->emit('property.lease_expired', [
                        'business_id' => $lease->business_id,
                        'subject_type' => 'property_lease',
                        'subject_id' => $lease->id,
                        'title' => 'Lease status requires closure or renewal',
                        'message' => 'A lease remains active after its end date. Record renewal, termination, handover, outstanding balances and security-deposit settlement.',
                        'action_url' => url('/property-management/leases'),
                        'due_at' => $lease->end_date,
                        'bucket' => (string) $lease->end_date,
                    ]);
                });
        }

        if (Schema::hasTable('property_maintenance_tickets')) {
            DB::table('property_maintenance_tickets')->whereNotIn('status', ['resolved', 'closed', 'cancelled'])
                ->where(function ($query) {
                    $query->where(function ($critical) {
                        $critical->where('priority', 'critical')->whereDate('reported_on', '<=', now()->subDay());
                    })->orWhereDate('reported_on', '<=', now()->subDays(7));
                })->limit(500)->get()->each(function ($ticket) {
                    $this->emit('property.maintenance_overdue', [
                        'business_id' => $ticket->business_id,
                        'subject_type' => 'property_maintenance_ticket',
                        'subject_id' => $ticket->id,
                        'title' => 'Property maintenance action overdue',
                        'message' => ($ticket->priority === 'critical' ? 'Critical' : 'Open').' maintenance ticket '.$ticket->title.' needs progress or resolution.',
                        'action_url' => url('/property-management/maintenance'),
                        'due_at' => Carbon::parse($ticket->reported_on)->addDays($ticket->priority === 'critical' ? 1 : 7),
                        'bucket' => $ticket->status.'-'.$ticket->reported_on,
                    ]);
                });
        }

        if (Schema::hasTable('property_viewing_requests')) {
            DB::table('property_viewing_requests')->where('status', 'approved')
                ->whereBetween('requested_start_at', [now(), now()->addHours(24)])->limit(500)->get()
                ->each(function ($viewing) {
                    $this->emit('property.viewing_upcoming', [
                        'business_id' => $viewing->business_id,
                        'subject_type' => 'property_viewing_request',
                        'subject_id' => $viewing->id,
                        'title' => 'Property viewing within 24 hours',
                        'message' => 'A confirmed property viewing needs preparation and assigned-user follow-up.',
                        'action_url' => url('/property-management/viewings'),
                        'due_at' => $viewing->requested_start_at,
                        'bucket' => Carbon::parse($viewing->requested_start_at)->toDateString(),
                        'metadata' => ['recipient_user_ids' => array_values(array_filter([(int) ($viewing->assigned_to ?? 0)]))],
                    ]);
                });
        }
    }

    private function scanSecurityDeposits(): void
    {
        if (! Schema::hasTable('security_deposits')) {
            return;
        }

        DB::table('security_deposits')->whereNotIn('status', ['settled', 'waived'])
            ->whereNotNull('due_date')->whereDate('due_date', '<=', now())->limit(1000)->get()
            ->each(function ($deposit) {
                $refundState = in_array($deposit->status, ['refund_pending', 'partially_refunded'], true);
                $this->emit($refundState ? 'deposit.refund_overdue' : 'deposit.security_pending', [
                    'business_id' => $deposit->business_id,
                    'subject_type' => 'security_deposit',
                    'subject_id' => $deposit->id,
                    'title' => $refundState ? 'Security deposit refund unresolved' : 'Security deposit needs attention',
                    'message' => 'A refundable security deposit linked to '.Str::headline($deposit->context_type).' is due and remains uncleared. It stays outside revenue accounting.',
                    'action_url' => url($deposit->context_type === 'property_lease' ? '/property-management/deposits' : '/hms/folios'),
                    'due_at' => $deposit->due_date,
                    'bucket' => $deposit->status.'-'.$deposit->due_date,
                ]);
            });
    }

    private function scanHumanResources(): void
    {
        if (Schema::hasTable('hrm_employment_assignments')) {
            DB::table('hrm_employment_assignments')->whereNotNull('effective_to')
                ->whereBetween('effective_to', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->limit(500)->get()->each(function ($assignment) {
                    $profile = Schema::hasTable('hrm_employment_profiles')
                        ? DB::table('hrm_employment_profiles')->where('id', $assignment->employment_profile_id)->first()
                        : null;
                    if (! $profile) {
                        return;
                    }
                    $this->emit('hr.contract_expiring', [
                        'business_id' => $assignment->business_id,
                        'subject_type' => 'hrm_employment_assignment',
                        'subject_id' => $assignment->id,
                        'title' => 'Employment assignment expires within 30 days',
                        'message' => 'An active employment assignment is approaching its effective end date and needs an HR decision.',
                        'action_url' => url('/hrm/people'),
                        'due_at' => $assignment->effective_to,
                        'bucket' => (string) $assignment->effective_to,
                        'metadata' => ['recipient_user_ids' => [(int) $profile->user_id]],
                    ]);
                });
        }

        if (Schema::hasTable('essentials_leaves')) {
            DB::table('essentials_leaves')->where('status', 'pending')->where('created_at', '<=', now()->subDays(2))
                ->limit(500)->get()->each(function ($leave) {
                    $this->emit('hr.leave_pending', [
                        'business_id' => $leave->business_id,
                        'subject_type' => 'essentials_leave',
                        'subject_id' => $leave->id,
                        'title' => 'Leave request awaiting decision',
                        'message' => 'A leave request has waited more than two days for review.',
                        'action_url' => url('/hrm/leave'),
                        'due_at' => Carbon::parse($leave->created_at)->addDays(2),
                        'bucket' => 'pending-'.$leave->created_at,
                    ]);
                });
        }

        if (Schema::hasTable('hrm_payroll_runs')) {
            DB::table('hrm_payroll_runs')->whereIn('status', ['draft', 'calculated', 'review_pending'])
                ->where('updated_at', '<=', now()->subDays(2))->limit(300)->get()
                ->each(function ($run) {
                    $this->emit('hr.payroll_action_required', [
                        'business_id' => $run->business_id,
                        'subject_type' => 'hrm_payroll_run',
                        'subject_id' => $run->id,
                        'title' => 'Payroll run needs action',
                        'message' => 'A payroll run remains at '.Str::headline($run->status).' for more than two days. Review validation, approvals and posting.',
                        'action_url' => url('/hrm/payroll-runs'),
                        'due_at' => Carbon::parse($run->updated_at)->addDays(2),
                        'bucket' => $run->status.'-'.$run->updated_at,
                    ]);
                });
        }
    }

    private function scanSharedWork(): void
    {
        if (Schema::hasTable('crm_activities')) {
            DB::table('crm_activities')->where('status', 'planned')->whereNotNull('due_at')->where('due_at', '<', now())
                ->limit(500)->get()->each(function ($activity) {
                    $this->emit('crm.activity_overdue', [
                        'business_id' => $activity->business_id,
                        'subject_type' => 'crm_activity',
                        'subject_id' => $activity->id,
                        'title' => 'CRM activity overdue',
                        'message' => 'CRM activity '.$activity->subject.' has passed its due time and remains planned.',
                        'action_url' => url('/crm/dashboard'),
                        'due_at' => $activity->due_at,
                        'bucket' => Carbon::parse($activity->due_at)->toDateString(),
                        'metadata' => ['recipient_user_ids' => array_values(array_filter([(int) ($activity->owner_id ?? 0)]))],
                    ]);
                });
        }

        if (Schema::hasTable('pjt_project_tasks')) {
            $dueColumn = Schema::hasColumn('pjt_project_tasks', 'due_date') ? 'due_date' : (Schema::hasColumn('pjt_project_tasks', 'end_date') ? 'end_date' : null);
            if ($dueColumn) {
                DB::table('pjt_project_tasks')->whereNotIn('status', ['completed', 'cancelled'])->whereNotNull($dueColumn)->where($dueColumn, '<', now())
                    ->limit(500)->get()->each(function ($task) use ($dueColumn) {
                        if (! isset($task->business_id)) {
                            return;
                        }
                        $this->emit('project.task_overdue', [
                            'business_id' => $task->business_id,
                            'subject_type' => 'project_task',
                            'subject_id' => $task->id,
                            'title' => 'Project task overdue',
                            'message' => 'A project task remains open after its due date.',
                            'action_url' => url('/project/project-task'),
                            'due_at' => $task->{$dueColumn},
                            'bucket' => Carbon::parse($task->{$dueColumn})->toDateString(),
                        ]);
                    });
            }
        }

        if (Schema::hasTable('company_hub_posts') && Schema::hasTable('company_hub_post_acknowledgements')) {
            DB::table('company_hub_posts as post')
                ->where('post.acknowledgement_required', true)
                ->where('post.priority', 'urgent')
                ->whereNotNull('post.published_at')
                ->where('post.published_at', '<=', now()->subDay())
                ->whereNull('post.deleted_at')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))->from('company_hub_post_acknowledgements as ack')
                        ->whereColumn('ack.company_hub_post_id', 'post.id')->whereNull('ack.acknowledged_at');
                })->limit(300)->get(['post.*'])
                ->each(function ($post) {
                    $this->emit('company_hub.acknowledgement_overdue', [
                        'business_id' => $post->business_id,
                        'subject_type' => 'company_hub_post',
                        'subject_id' => $post->id,
                        'title' => 'Urgent announcement acknowledgement overdue',
                        'message' => 'An urgent company announcement still has outstanding acknowledgements after 24 hours.',
                        'action_url' => url('/company-hub?post='.$post->uuid),
                        'due_at' => Carbon::parse($post->published_at)->addDay(),
                        'bucket' => 'urgent-ack',
                    ]);
                });
        }
    }

    private function emit(string $eventKey, array $payload): void
    {
        $this->examined++;
        $this->activeSubjects[$this->subjectKey(
            $eventKey,
            isset($payload['business_id']) ? (int) $payload['business_id'] : null,
            (string) ($payload['subject_type'] ?? 'system'),
            (string) ($payload['subject_id'] ?? 'global')
        )] = true;

        if ($this->option('dry-run')) {
            $this->emitted++;
            return;
        }

        if ($this->notifications->dispatch($eventKey, $payload)) {
            $this->emitted++;
        }
    }

    private function reconcileResolvedEvents(): void
    {
        if ($this->option('dry-run') || empty($this->coveredEventKeys)) {
            return;
        }

        NotificationEvent::query()
            ->whereIn('event_key', $this->coveredEventKeys)
            ->whereNull('resolved_at')
            ->orderBy('id')
            ->chunkById(500, function ($events) {
                foreach ($events as $event) {
                    $key = $this->subjectKey(
                        $event->event_key,
                        $event->business_id ? (int) $event->business_id : null,
                        (string) ($event->subject_type ?: 'system'),
                        (string) ($event->subject_id ?: 'global')
                    );
                    if (isset($this->activeSubjects[$key])) {
                        continue;
                    }

                    $event->forceFill(['resolved_at' => now()])->save();
                    $this->resolved++;

                    if (Schema::hasTable('notifications')) {
                        DB::table('notifications')
                            ->whereNull('read_at')
                            ->where('data->event_uuid', $event->uuid)
                            ->update(['read_at' => now(), 'updated_at' => now()]);
                    }
                }
            });
    }

    private function subjectKey(string $eventKey, ?int $businessId, string $subjectType, string $subjectId): string
    {
        return implode('|', [$eventKey, $businessId ?: 'platform', $subjectType, $subjectId]);
    }

    private function scanSystemWork(): void
    {
        if (! Schema::hasTable('business_data_imports')) {
            return;
        }

        DB::table('business_data_imports')
            ->whereIn('status', ['failed', 'rollback_failed'])
            ->where('updated_at', '>=', now()->subDays(30))
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->each(function ($import) {
                $this->emit('data_import.failed', [
                    'business_id' => $import->business_id,
                    'subject_type' => 'business_data_import',
                    'subject_id' => $import->id,
                    'title' => $import->status === 'rollback_failed' ? 'Data import rollback failed' : 'Data import failed',
                    'message' => 'Import '.($import->original_name ?: $import->uuid).' needs review. No failed import should be assumed to have changed all submitted records.',
                    'action_url' => url('/data-imports/'.$import->uuid),
                    'due_at' => $import->updated_at,
                    'bucket' => $import->status.'-'.$import->updated_at,
                    'metadata' => ['recipient_user_ids' => array_values(array_filter([(int) ($import->uploaded_by ?? 0)]))],
                ]);
            });
    }

    private function cleanupRetainedData(): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        if (Schema::hasTable('registration_intents')) {
            $this->pruned += RegistrationIntent::whereNotNull('completed_at')->where('completed_at', '<', now()->subDays(30))->delete();
            $this->pruned += RegistrationIntent::whereNull('completed_at')->where('reminder_consent', false)->where('last_activity_at', '<', now()->subDays(30))->delete();
            $this->pruned += RegistrationIntent::whereNull('completed_at')->where('last_activity_at', '<', now()->subDays(90))->delete();
        }

        if (Schema::hasTable('notification_events')) {
            $days = max(90, (int) config('notification_events.retention_days', 400));
            $this->pruned += DB::table('notification_events')->whereNotNull('resolved_at')->where('resolved_at', '<', now()->subDays($days))->delete();
        }
    }
}
