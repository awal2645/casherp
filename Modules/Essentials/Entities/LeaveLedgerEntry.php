<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class LeaveLedgerEntry extends Model
{
    protected $table = 'hrm_leave_ledger_entries';
    protected $guarded = ['id'];
    protected $casts = ['effective_date' => 'date', 'quantity' => 'decimal:3', 'balance_after' => 'decimal:3'];

    public function account() { return $this->belongsTo(LeaveAccount::class, 'leave_account_id'); }
    protected static function booted() { static::updating(fn () => throw new \LogicException('Leave ledger entries are immutable.')); static::deleting(fn () => throw new \LogicException('Leave ledger entries are immutable.')); }
}
