<?php

namespace Modules\Hms\Services;

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsBookingLine;
use Modules\Hms\Entities\HmsHousekeepingTask;
use Modules\Hms\Entities\HmsRoom;

class HousekeepingService
{
    public function createCheckoutTasks(Transaction $booking, int $actorId): int
    {
        $roomIds = HmsBookingLine::join(
            'hms_rooms',
            'hms_rooms.id',
            '=',
            'hms_booking_lines.hms_room_id'
        )
            ->join(
                'hms_room_types',
                'hms_room_types.id',
                '=',
                'hms_rooms.hms_room_type_id'
            )
            ->where('hms_booking_lines.transaction_id', $booking->id)
            ->where('hms_room_types.business_id', $booking->business_id)
            ->distinct()
            ->pluck('hms_booking_lines.hms_room_id');

        $created = 0;
        foreach ($roomIds as $roomId) {
            $task = HmsHousekeepingTask::firstOrCreate(
                [
                    'business_id' => $booking->business_id,
                    'hms_room_id' => $roomId,
                    'transaction_id' => $booking->id,
                    'task_type' => 'checkout_cleaning',
                ],
                [
                    'priority' => 'high',
                    'status' => 'pending',
                    'scheduled_for' => $booking->check_out ?: now(),
                    'created_by' => $actorId,
                    'notes' => 'Automatically created when booking #'.$booking->id.' checked out.',
                ]
            );

            if ($task->wasRecentlyCreated) {
                $created++;
                HmsRoom::whereKey($roomId)->update(['housekeeping_status' => 'dirty']);
            }
        }

        return $created;
    }

    public function createTask(
        int $businessId,
        int $roomId,
        array $data,
        int $actorId
    ): HmsHousekeepingTask {
        return DB::transaction(function () use ($businessId, $roomId, $data, $actorId) {
            $room = $this->roomForBusiness($businessId, $roomId, true);
            $this->assertRoomServiceable($room);
            $activeTask = HmsHousekeepingTask::where('business_id', $businessId)
                ->where('hms_room_id', $room->id)
                ->whereIn('status', ['pending', 'in_progress', 'cleaned'])
                ->first();

            if ($activeTask !== null) {
                throw ValidationException::withMessages([
                    'hms_room_id' => 'This room already has an active housekeeping task.',
                ]);
            }

            $task = HmsHousekeepingTask::create([
                'business_id' => $businessId,
                'hms_room_id' => $room->id,
                'transaction_id' => $data['transaction_id'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'task_type' => $data['task_type'],
                'priority' => $data['priority'],
                'status' => 'pending',
                'scheduled_for' => $data['scheduled_for'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            $room->update(['housekeeping_status' => 'dirty']);

            return $task;
        });
    }

    public function assign(
        HmsHousekeepingTask $task,
        ?int $userId
    ): HmsHousekeepingTask {
        return DB::transaction(function () use ($task, $userId) {
            $lockedTask = $this->lockTask($task);
            $this->ensureActive($lockedTask);
            $lockedTask->update(['assigned_to' => $userId]);

            return $lockedTask->fresh();
        });
    }

    public function start(
        HmsHousekeepingTask $task,
        int $actorId
    ): HmsHousekeepingTask {
        return DB::transaction(function () use ($task, $actorId) {
            $lockedTask = $this->lockTask($task);
            $this->assertRoomServiceable($this->roomForBusiness((int) $lockedTask->business_id, (int) $lockedTask->hms_room_id, true));
            if ($lockedTask->status !== 'pending') {
                throw ValidationException::withMessages([
                    'housekeeping' => 'Only a pending task can be started.',
                ]);
            }

            $lockedTask->update([
                'status' => 'in_progress',
                'assigned_to' => $lockedTask->assigned_to ?: $actorId,
                'started_at' => now(),
            ]);
            HmsRoom::whereKey($lockedTask->hms_room_id)
                ->update(['housekeeping_status' => 'cleaning']);

            return $lockedTask->fresh();
        });
    }

    public function markCleaned(
        HmsHousekeepingTask $task,
        int $actorId,
        ?string $notes = null
    ): HmsHousekeepingTask {
        return DB::transaction(function () use ($task, $actorId, $notes) {
            $lockedTask = $this->lockTask($task);
            $this->assertRoomServiceable($this->roomForBusiness((int) $lockedTask->business_id, (int) $lockedTask->hms_room_id, true));
            if (! in_array($lockedTask->status, ['pending', 'in_progress'], true)) {
                throw ValidationException::withMessages([
                    'housekeeping' => 'Only a pending or in-progress task can be marked cleaned.',
                ]);
            }

            $cleanedAt = now();
            $lockedTask->update([
                'status' => 'cleaned',
                'assigned_to' => $lockedTask->assigned_to ?: $actorId,
                'started_at' => $lockedTask->started_at ?: $cleanedAt,
                'cleaned_at' => $cleanedAt,
                'completed_by' => $actorId,
                'completion_notes' => $notes,
            ]);
            HmsRoom::whereKey($lockedTask->hms_room_id)->update([
                'housekeeping_status' => 'cleaned',
                'last_cleaned_at' => $cleanedAt,
            ]);

            return $lockedTask->fresh();
        });
    }

    public function inspect(
        HmsHousekeepingTask $task,
        int $actorId,
        bool $passed,
        ?string $notes = null
    ): HmsHousekeepingTask {
        return DB::transaction(function () use ($task, $actorId, $passed, $notes) {
            $lockedTask = $this->lockTask($task);
            $this->assertRoomServiceable($this->roomForBusiness((int) $lockedTask->business_id, (int) $lockedTask->hms_room_id, true));
            if ($lockedTask->status !== 'cleaned') {
                throw ValidationException::withMessages([
                    'housekeeping' => 'Only a cleaned room can be inspected.',
                ]);
            }

            $inspectedAt = now();
            $lockedTask->update([
                'status' => $passed ? 'ready' : 'in_progress',
                'inspected_at' => $inspectedAt,
                'inspected_by' => $actorId,
                'inspection_notes' => $notes,
            ]);
            HmsRoom::whereKey($lockedTask->hms_room_id)->update([
                'housekeeping_status' => $passed ? 'ready' : 'dirty',
                'last_inspected_at' => $passed ? $inspectedAt : null,
            ]);

            return $lockedTask->fresh();
        });
    }

    private function lockTask(HmsHousekeepingTask $task): HmsHousekeepingTask
    {
        return HmsHousekeepingTask::where('business_id', $task->business_id)
            ->whereKey($task->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function roomForBusiness(
        int $businessId,
        int $roomId,
        bool $lock = false
    ): HmsRoom {
        $query = HmsRoom::whereHas('type', function ($roomType) use ($businessId) {
            $roomType->where('business_id', $businessId);
        });
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($roomId);
    }

    private function ensureActive(HmsHousekeepingTask $task): void
    {
        if ($task->status === 'ready') {
            throw ValidationException::withMessages([
                'housekeeping' => 'A completed housekeeping task cannot be changed.',
            ]);
        }
    }

    private function assertRoomServiceable(HmsRoom $room): void
    {
        if ($room->housekeeping_status === 'out_of_order') {
            throw ValidationException::withMessages([
                'housekeeping' => __('hms::lang.room_out_of_order_housekeeping_blocked'),
            ]);
        }
    }
}
