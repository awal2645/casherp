<?php

namespace Modules\Hms\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsOperationalEvent;
use Modules\Hms\Entities\HmsOperationalTask;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsTransactionClass;

class OperationalTaskService
{
    public function create(array $input, int $actorId): HmsOperationalTask
    {
        return DB::transaction(function () use ($input, $actorId) {
            $task = HmsOperationalTask::create($input + [
                'status' => 'open',
                'occurred_at' => now(),
                'created_by' => $actorId,
            ]);

            if (in_array($task->task_type, ['maintenance', 'out_of_order'], true) && $task->hms_room_id) {
                $this->tenantRoomQuery($task)->update(['housekeeping_status' => 'out_of_order']);
            }

            $this->event($task, 'created', null, 'open', $actorId, $task->description);

            return $task;
        });
    }

    public function transition(int $businessId, int $taskId, string $target, int $actorId, ?string $notes = null): HmsOperationalTask
    {
        return DB::transaction(function () use ($businessId, $taskId, $target, $actorId, $notes) {
            $task = HmsOperationalTask::where('business_id', $businessId)->lockForUpdate()->findOrFail($taskId);
            $allowed = [
                'open' => ['assigned', 'in_progress', 'resolved', 'cancelled'],
                'assigned' => ['in_progress', 'resolved', 'cancelled'],
                'in_progress' => ['resolved', 'cancelled'],
                'resolved' => [],
                'cancelled' => [],
            ];
            if (! in_array($target, $allowed[$task->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => __('hms::lang.invalid_task_transition')]);
            }

            $from = $task->status;
            $task->status = $target;
            $task->resolution = $notes;
            if ($target === 'resolved') {
                $task->completed_at = now();
                $task->completed_by = $actorId;
            }
            $task->save();

            if (in_array($target, ['resolved', 'cancelled'], true)
                && in_array($task->task_type, ['maintenance', 'out_of_order'], true)
                && $task->hms_room_id) {
                $otherOpenIssue = HmsOperationalTask::where('business_id', $businessId)
                    ->where('hms_property_id', $task->hms_property_id)
                    ->where('hms_room_id', $task->hms_room_id)
                    ->where('id', '!=', $task->id)
                    ->whereIn('task_type', ['maintenance', 'out_of_order'])
                    ->whereIn('status', ['open', 'assigned', 'in_progress'])
                    ->exists();
                if (! $otherOpenIssue) {
                    $this->tenantRoomQuery($task)->update(['housekeeping_status' => 'dirty']);
                }
            }
            if ($target === 'resolved' && (float) $task->charge_amount > 0 && $task->transaction_id) {
                $booking = HmsTransactionClass::where('business_id', $businessId)
                    ->where('hms_property_id', $task->hms_property_id)
                    ->where('type', 'hms_booking')
                    ->findOrFail($task->transaction_id);
                $folio = app(FolioService::class)->ensureForBooking($booking, $actorId);
                app(FolioService::class)->post($folio, [
                    'entry_type' => 'charge',
                    'category' => $task->task_type,
                    'direction' => 'debit',
                    'amount' => $task->charge_amount,
                    'description' => $task->item_name ?: $task->description,
                    'idempotency_key' => 'operational-task:' . $task->id,
                ], $actorId);
            }

            $this->event($task, $target, $from, $target, $actorId, $notes);

            return $task->fresh();
        });
    }

    private function event(HmsOperationalTask $task, string $type, ?string $from, ?string $to, int $actorId, ?string $notes): void
    {
        HmsOperationalEvent::create([
            'business_id' => $task->business_id,
            'hms_operational_task_id' => $task->id,
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actorId,
            'notes' => $notes,
            'occurred_at' => now(),
        ]);
    }

    private function tenantRoomQuery(HmsOperationalTask $task)
    {
        return HmsRoom::whereKey($task->hms_room_id)
            ->where('hms_property_id', $task->hms_property_id)
            ->whereHas('type', fn ($query) => $query->where('business_id', $task->business_id));
    }
}
