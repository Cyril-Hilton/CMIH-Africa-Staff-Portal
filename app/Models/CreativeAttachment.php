<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CreativeAttachment extends Model {protected $guarded=['id']; public function request(){return $this->belongsTo(CreativeRequest::class,'creative_request_id');}}
