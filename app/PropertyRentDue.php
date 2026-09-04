<?php
namespace App; use Illuminate\Database\Eloquent\Model;
class PropertyRentDue extends Model { protected $guarded=['id']; protected $casts=['due_date'=>'date']; public function lease(){return $this->belongsTo(PropertyLease::class,'property_lease_id');} public function payments(){return $this->hasMany(PropertyRentPayment::class);} }
