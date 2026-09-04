<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessDataImport extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'validated_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'business_location_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rows()
    {
        return $this->hasMany(BusinessDataImportRow::class);
    }

    public function events()
    {
        return $this->hasMany(BusinessDataImportEvent::class);
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where($this->qualifyColumn('business_id'), $businessId);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['uploaded', 'ready'], true);
    }
}
