<?php

namespace App\Services;

use App\Restaurant\KitchenStation;
use App\Restaurant\KitchenTicket;
use App\Restaurant\KitchenTicketItem;
use App\Restaurant\OrderEvent;
use App\Restaurant\OrderFulfilment;
use App\Restaurant\ProductStation;
use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KitchenRoutingService
{
    public function synchronise(Transaction $transaction): void
    {
        if ($transaction->type !== 'sell' || $transaction->status !== 'final' || ! $transaction->is_kitchen_order) {
            return;
        }

        DB::transaction(function () use ($transaction) {
            $transaction->loadMissing(['sell_lines.product', 'sell_lines.variations', 'sell_lines.modifiers']);
            $fulfilment = $this->fulfilmentFor($transaction);
            $default = KitchenStation::firstOrCreate(
                [
                    'business_id' => $transaction->business_id,
                    'location_id' => $transaction->location_id,
                    'code' => 'MAIN',
                ],
                [
                    'name' => 'Main kitchen',
                    'station_type' => 'main',
                    'service_level_minutes' => 15,
                    'is_default' => true,
                    'is_active' => true,
                ]
            );

            foreach ($transaction->sell_lines->whereNull('parent_sell_line_id') as $line) {
                $mapping = ProductStation::query()
                    ->where('business_id', $transaction->business_id)
                    ->where('product_id', $line->product_id)
                    ->where(function ($query) use ($line) {
                        $query->where('variation_id', $line->variation_id)->orWhereNull('variation_id');
                    })
                    ->whereHas('station', function ($query) use ($transaction) {
                        $query->where('location_id', $transaction->location_id)->where('is_active', true);
                    })
                    ->orderByRaw('variation_id IS NULL')
                    ->orderByDesc('priority')
                    ->first();
                $station = $mapping ? $mapping->station : $default;

                $ticket = KitchenTicket::firstOrCreate(
                    [
                        'transaction_id' => $transaction->id,
                        'station_id' => $station->id,
                        'sequence' => 1,
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        'business_id' => $transaction->business_id,
                        'location_id' => $transaction->location_id,
                        'ticket_number' => ($transaction->invoice_no ?: $transaction->id).'-'.$station->code,
                        'status' => 'queued',
                        'service_channel' => $fulfilment->service_channel,
                        'fired_at' => now(),
                    ]
                );

                KitchenTicketItem::updateOrCreate(
                    [
                        'kitchen_ticket_id' => $ticket->id,
                        'transaction_sell_line_id' => $line->id,
                    ],
                    [
                        'product_id' => $line->product_id,
                        'variation_id' => $line->variation_id,
                        'quantity' => $line->quantity,
                        'item_name' => optional($line->product)->name ?: 'Menu item',
                        'modifier_snapshot' => $line->modifiers->map(fn ($modifier) => [
                            'name' => optional($modifier->product)->name,
                            'quantity' => $modifier->quantity,
                        ])->values()->all(),
                    ]
                );
            }
        });
    }

    public function transition(int $businessId, int $ticketId, string $toStatus, int $actorId): KitchenTicket
    {
        return DB::transaction(function () use ($businessId, $ticketId, $toStatus, $actorId) {
            $ticket = KitchenTicket::where('business_id', $businessId)->whereKey($ticketId)->lockForUpdate()->firstOrFail();
            $allowed = (array) config('restaurant_operations.kitchen_transitions.'.$ticket->status, []);
            if (! in_array($toStatus, $allowed, true)) {
                throw ValidationException::withMessages(['status' => "Kitchen ticket cannot move from {$ticket->status} to {$toStatus}."]);
            }

            $from = $ticket->status;
            $ticket->status = $toStatus;
            $ticket->last_changed_by = $actorId;
            $ticket->lock_version++;
            $timestamp = [
                'accepted' => 'accepted_at',
                'preparing' => 'started_at',
                'ready' => 'ready_at',
                'completed' => 'completed_at',
                'cancelled' => 'cancelled_at',
            ][$toStatus] ?? null;
            if ($timestamp) {
                $ticket->{$timestamp} = now();
            }
            $ticket->save();
            $ticket->items()->whereNotIn('status', ['completed', 'cancelled'])->update(['status' => $toStatus]);

            OrderEvent::create([
                'business_id' => $businessId,
                'transaction_id' => $ticket->transaction_id,
                'event_type' => 'kitchen_ticket_status_changed',
                'from_status' => $from,
                'to_status' => $toStatus,
                'payload' => ['ticket_id' => $ticket->id, 'station_id' => $ticket->station_id],
                'actor_id' => $actorId,
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            ]);

            return $ticket->fresh(['station', 'items']);
        });
    }

    private function fulfilmentFor(Transaction $transaction): OrderFulfilment
    {
        $channel = $transaction->res_table_id ? 'dine_in' : 'counter';

        return OrderFulfilment::firstOrCreate(
            ['transaction_id' => $transaction->id],
            [
                'business_id' => $transaction->business_id,
                'location_id' => $transaction->location_id,
                'service_channel' => $channel,
                'status' => 'received',
                'table_id' => $transaction->res_table_id,
            ]
        );
    }
}
