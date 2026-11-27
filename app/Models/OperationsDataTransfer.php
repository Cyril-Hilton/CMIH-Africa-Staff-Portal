<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OperationsDataTransfer extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['payload'=>'array','revision'=>'integer','reviewed_at'=>'datetime'];}
}
