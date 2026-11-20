<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectExecutionControl extends Model
{
    use \App\Models\Concerns\ReportingArchive;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'revision' => 'integer'];
    }

    public function project()
    {
        return $this->belongsTo(CrmPipelineProject::class, 'crm_project_id');
    }

    public function activation()
    {
        return $this->belongsTo(PerformanceRegister::class, 'operations_activation_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
