<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PropertyAccountingPosting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function debitAccount()
    {
        return $this->belongsTo(
            \Modules\Accounting\Entities\AccountingAccount::class,
            'debit_account_id'
        );
    }

    public function creditAccount()
    {
        return $this->belongsTo(
            \Modules\Accounting\Entities\AccountingAccount::class,
            'credit_account_id'
        );
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }
}
