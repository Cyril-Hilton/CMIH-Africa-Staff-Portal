<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class HrRegisterRecord extends Model {
 use SoftDeletes;
 protected $guarded=['id'];
 protected function casts(): array {return ['data'=>'array','attachment'=>'array'];}
}
