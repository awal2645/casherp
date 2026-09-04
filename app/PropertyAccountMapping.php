<?php
namespace App;use Illuminate\Database\Eloquent\Model;
class PropertyAccountMapping extends Model{protected $guarded=['id'];protected $casts=['auto_post_enabled'=>'boolean'];}
