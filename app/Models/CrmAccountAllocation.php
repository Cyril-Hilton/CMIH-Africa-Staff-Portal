<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class CrmAccountAllocation extends Model {protected $guarded=['id'];protected $hidden=['source_values'];protected function casts():array{return ['source_values'=>'array','ytd_target'=>'decimal:2','monthly_target'=>'decimal:2','budget_target'=>'decimal:2','reported_target'=>'decimal:2'];}public function owner(){return $this->belongsTo(User::class,'owner_id');}}
