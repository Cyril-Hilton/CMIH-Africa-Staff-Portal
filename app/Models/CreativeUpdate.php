<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CreativeUpdate extends Model {protected $guarded=['id'];protected $casts=['metadata'=>'array'];public function author(){return $this->belongsTo(User::class,'author_id');}protected static function booted():void{static::updating(fn()=>throw new \LogicException('Creative feedback is append-only.'));static::deleting(fn()=>throw new \LogicException('Creative feedback is append-only.'));}}
