<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmClientRecord extends Model
{
    use \App\Models\Concerns\ReportingArchive;

    protected $guarded = ['id'];

    protected $attributes = ['revision' => 1];

    protected function casts(): array
    {
        return ['supporting_staff_ids' => 'array', 'data' => 'array'];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function project()
    {
        return $this->belongsTo(CrmPipelineProject::class, 'project_id');
    }
}
