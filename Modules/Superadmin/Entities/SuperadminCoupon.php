<?php

namespace Modules\Superadmin\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SuperadminCoupon extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'applied_on_packages' => 'array',
        'applied_on_business' => 'array',
        'discount' => 'decimal:4',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
    ];
}
