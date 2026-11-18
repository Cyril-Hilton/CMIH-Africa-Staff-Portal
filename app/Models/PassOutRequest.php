<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PassOutRequest extends Model {
    protected $fillable = ['user_id','supervisor_id','hod_id','request_date','departure_time','reason','status','reservation_key','history','hr_notes','recorded_by','recorded_at'];
    protected function casts(): array { return ['request_date'=>'date','history'=>'array','recorded_at'=>'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function supervisor(): BelongsTo { return $this->belongsTo(User::class,'supervisor_id'); }
    public function hod(): BelongsTo { return $this->belongsTo(User::class,'hod_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class,'recorded_by'); }
    public function reviewerId(): ?int { return match($this->status) {'pending_supervisor'=>(int)$this->supervisor_id,'pending_hod'=>(int)$this->hod_id, default=>null}; }
    public function statusLabel(): string { return match($this->status) {'pending_supervisor'=>'Awaiting supervisor','pending_hod'=>'Awaiting HOD','pending_hr'=>'Awaiting HR record','approved'=>'Approved & recorded','rejected'=>'Rejected','cancelled'=>'Cancelled',default=>'Review needed'}; }
}
