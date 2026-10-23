<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CrmAccountRelationship extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['starts_on'=>'date','ends_on'=>'date'];}
 public function owner(){return $this->belongsTo(User::class,'owner_id');}
}
