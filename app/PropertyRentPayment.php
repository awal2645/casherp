<?php
namespace App; use Illuminate\Database\Eloquent\Model;
class PropertyRentPayment extends Model { protected $guarded=['id']; protected $casts=['paid_on'=>'date']; public function due(){return $this->belongsTo(PropertyRentDue::class,'property_rent_due_id');} }
