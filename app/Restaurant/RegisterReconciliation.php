<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class RegisterReconciliation extends Model
{
    protected $table = 'restaurant_register_reconciliations';

    protected $guarded = ['id'];

    protected $casts = [
        'expected_cash' => 'decimal:4',
        'counted_cash' => 'decimal:4',
        'variance' => 'decimal:4',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function counts()
    {
        return $this->hasMany(RegisterCount::class, 'reconciliation_id');
    }
}
