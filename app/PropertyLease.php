<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
class PropertyLease extends Model { protected $guarded = ['id']; protected $casts = ['start_date' => 'date', 'end_date' => 'date']; public function unit() { return $this->belongsTo(PropertyUnit::class, 'property_unit_id'); } public function tenant() { return $this->belongsTo(Contact::class, 'contact_id'); } public function securityDeposit() { return $this->hasOne(SecurityDeposit::class, 'context_id')->where('context_type', 'property_lease'); } public function paymentDeposits() { return $this->hasMany(PropertyPaymentDeposit::class); } }
