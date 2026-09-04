<?php

namespace Modules\Essentials\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\LeaveAccount;
use Modules\Essentials\Entities\LeaveLedgerEntry;
use Modules\Essentials\Entities\EmploymentProfile;
use Modules\Essentials\Entities\WorkCalendar;

class LeaveLedgerService
{
    private const COLUMNS = [
        'opening' => 'opening_balance', 'accrual' => 'accrued', 'carry_forward' => 'carried_forward',
        'adjustment' => 'adjusted', 'reservation' => 'reserved', 'release' => 'reserved',
        'usage' => 'used', 'expiry' => 'expired',
    ];

    private HrmAuditService $audit;

    public function __construct(HrmAuditService $audit)
    {
        $this->audit = $audit;
    }

    public function post(LeaveAccount $account, string $type, float $quantity, string $effectiveDate, string $reason, ?int $actorId, ?string $sourceType = null, ?int $sourceId = null): LeaveLedgerEntry
    {
        if (! isset(self::COLUMNS[$type]) || $quantity == 0.0) {
            throw ValidationException::withMessages(['quantity' => 'A supported, non-zero leave ledger quantity is required.']);
        }
        if ($type !== 'adjustment' && $quantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'Use a positive quantity for this entry type; only adjustments may be signed.']);
        }

        return DB::transaction(function () use ($account, $type, $quantity, $effectiveDate, $reason, $actorId, $sourceType, $sourceId) {
            $account = LeaveAccount::forBusiness($account->business_id)->lockForUpdate()->findOrFail($account->id);
            $column = self::COLUMNS[$type];
            $signed = abs($quantity);
            if (in_array($type, ['release'], true)) $signed *= -1;
            if ($type === 'adjustment') $signed = $quantity;

            $newColumn = round((float) $account->{$column} + $signed, 3);
            if ($column !== 'adjusted' && $newColumn < 0) {
                throw ValidationException::withMessages(['quantity' => 'This entry would make a protected leave total negative.']);
            }
            $before = $account->toArray();
            $account->{$column} = $newColumn;
            $account->version++;
            $available = $account->availableBalance();
            if ($available < 0) {
                throw ValidationException::withMessages(['quantity' => 'This entry would make the available leave balance negative.']);
            }
            $account->save();
            $entry = LeaveLedgerEntry::create([
                'uuid' => (string) Str::uuid(), 'business_id' => $account->business_id, 'leave_account_id' => $account->id,
                'entry_type' => $type, 'quantity' => $signed, 'balance_after' => $available, 'effective_date' => $effectiveDate,
                'source_type' => $sourceType, 'source_id' => $sourceId, 'leave_id' => $sourceType === 'leave' ? $sourceId : null,
                'reason' => $reason, 'created_by' => $actorId,
            ]);
            $this->audit->record($account->business_id, 'leave.ledger_'.$type, LeaveAccount::class, $account->id, $before, $account->toArray(), $reason);

            return $entry;
        });
    }

    /**
     * Count chargeable leave days using the employee's active location
     * calendar and company holidays. Legacy companies without a configured
     * calendar retain inclusive calendar-day behaviour.
     */
    public function workingDaysFor(EmploymentProfile $profile, $startsOn, $endsOn): float
    {
        $start = Carbon::parse($startsOn)->startOfDay();
        $end = Carbon::parse($endsOn)->startOfDay();
        if ($end->lt($start)) throw ValidationException::withMessages(['end_date' => 'The leave end date must not precede its start date.']);

        $calendar = null;
        if ($profile->location_id) {
            $calendar = WorkCalendar::forBusiness($profile->business_id)->where('is_active', true)->where('location_id', $profile->location_id)->first();
        }
        $calendar = $calendar ?: WorkCalendar::forBusiness($profile->business_id)->where('is_active', true)->where('is_default', true)->first();
        if (! $calendar) return (float) ($start->diffInDays($end) + 1);

        $holidays = DB::table('essentials_holidays')->where('business_id', $profile->business_id)
            ->whereDate('start_date', '<=', $end->toDateString())->whereDate('end_date', '>=', $start->toDateString())
            ->where(fn ($query) => $query->whereNull('location_id')->when($profile->location_id, fn ($q) => $q->orWhere('location_id', $profile->location_id)))
            ->get(['start_date', 'end_date']);
        $schedule = (array) $calendar->weekly_schedule;
        $weekends = array_map('strtolower', (array) $calendar->weekend_days);
        $days = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dayName = strtolower($date->format('l'));
            $scheduled = $schedule
                ? array_key_exists($dayName, $schedule) && ! empty($schedule[$dayName])
                : ! in_array($dayName, $weekends, true);
            $holiday = $holidays->contains(fn ($item) => $date->betweenIncluded(Carbon::parse($item->start_date), Carbon::parse($item->end_date)));
            if ($scheduled && ! $holiday) $days++;
        }

        return (float) $days;
    }
}
