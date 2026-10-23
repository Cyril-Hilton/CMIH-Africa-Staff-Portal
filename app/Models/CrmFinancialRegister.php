<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CrmFinancialRegister extends Model {protected $table='crm_financial_register';protected $guarded=['id'];protected $hidden=['source_values'];protected function casts():array{return ['source_values'=>'array','as_of'=>'date','po_amount'=>'decimal:2','po_balance'=>'decimal:2','invoiced_amount'=>'decimal:2'];}public function owner(){return $this->belongsTo(User::class,'owner_id');}}
