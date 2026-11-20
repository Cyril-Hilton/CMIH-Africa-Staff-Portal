<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait ReportingArchive
{
    public static function bootReportingArchive(): void
    {
        static::addGlobalScope('active_reporting', function (Builder $query) {
            static $ready = [];
            $model = $query->getModel();
            if ($model->getTable() === 'performance_report_packs') {
                if (Schema::hasTable('reporting_archive_records')) {
                    $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('reporting_archive_records')->where('source_table', 'performance_report_packs')->whereColumn('source_id', 'performance_report_packs.id'));
                }

                return;
            }
            $key = $model->getConnection()->getDatabaseName().':'.$model->getTable();
            if (isset($ready[$key]) || Schema::hasColumn($model->getTable(), 'reporting_archive_batch_id')) {
                $ready[$key] = true;
                $query->whereNull($model->qualifyColumn('reporting_archive_batch_id'));
            }
        });
    }

    public function scopeWithReportingArchive(Builder $query): Builder
    {
        return $query->withoutGlobalScope('active_reporting');
    }
}
