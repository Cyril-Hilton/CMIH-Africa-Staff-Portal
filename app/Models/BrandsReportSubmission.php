<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BrandsReportSubmission extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['payload'=>'array','reviewed_at'=>'datetime','revision'=>'integer'];}
 public function submitter(){return $this->belongsTo(User::class,'submitted_by');}
 public function reviewer(){return $this->belongsTo(User::class,'reviewed_by');}
}
