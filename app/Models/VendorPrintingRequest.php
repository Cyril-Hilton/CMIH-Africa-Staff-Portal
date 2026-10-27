<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorPrintingRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['information' => 'array', 'items' => 'array', 'vendor' => 'array', 'checklist' => 'array', 'revision' => 'integer', 'reviewed_at'=>'datetime', 'reviewed_revision'=>'integer'];
    }

    public function downloadable(): bool
    {
        return in_array($this->review_status,['legacy','lead_created'],true) ||
            ($this->review_status==='approved' && $this->reviewed_revision===$this->revision && $this->reviewed_by && $this->reviewed_at);
    }

    public function reviewLabel(): string
    {
        return match($this->review_status) {
            'pending'=>'Awaiting HOD Review', 'approved'=>'Approved', 'needs_changes'=>'Corrections Required',
            'draft'=>'Draft', 'lead_created'=>'Created by Department Leadership', default=>'Existing Request',
        };
    }
    public function reviewer() {return $this->belongsTo(User::class,'reviewed_by');}

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
