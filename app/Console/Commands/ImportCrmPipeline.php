<?php

namespace App\Console\Commands;

use App\Models\CrmAccountTarget;
use App\Models\CrmPipelineProject;
use App\Models\User;
use App\Services\ClientRelationsPipeline;
use App\Services\PerformanceWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCrmPipeline extends Command
{
    protected $signature = 'crm:import-pipeline {file} {--owner-map= : JSON source-sheet to existing user ID map} {--actor= : Existing authorised importer user ID}';

    protected $description = 'Import workbook project snapshots and dated targets with provenance and duplicate controls';

    public function handle(): int
    {
        $data = json_decode(file_get_contents($this->argument('file')), true, 512, JSON_THROW_ON_ERROR);
        if (($data['source_id'] ?? null) !== '1ydsMLK86iDG5XPp1PcC3_qjh_2CjeJ_-') {
            throw new \RuntimeException('Unexpected workbook');
        }
        $map = json_decode(file_get_contents($this->option('owner-map')), true, 512, JSON_THROW_ON_ERROR);
        $actor = User::findOrFail($this->option('actor'));
        if (! ClientRelationsPipeline::member($actor)) {
            throw new \RuntimeException('Authorised importer required');
        }
        foreach (array_unique(array_column($data['projects'], 'sheet')) as $sheet) {
            if (empty($map[$sheet]) || ! ClientRelationsPipeline::staffMember(User::findOrFail($map[$sheet]))) {
                throw new \RuntimeException('Explicit active Client Relations owner required for '.$sheet);
            }
        }
        $counts = ['projects' => 0, 'targets' => 0, 'undated_targets' => 0];
        DB::transaction(function () use ($data, $map, $actor, &$counts) {
            foreach ($data['projects'] as $row) {
                $key = hash('sha256', $data['source_id'].'|'.$row['key']);
                $existing = CrmPipelineProject::where('import_key', $key)->first();
                // An import rerun must never overwrite subsequent staff edits.
                if ($existing) {
                    continue;
                }
                $p = CrmPipelineProject::create(['import_key' => $key, 'source_sheet' => $row['sheet'], 'source_row' => $row['row'], 'source_period_label' => $row['period_label'], 'source_period_confirmed' => $row['confirmed'], 'source_forecast' => $row['source_forecast'], 'source_values' => $row, 'client_name' => mb_substr($row['client'], 0, 255), 'project_name' => mb_substr($row['project'], 0, 255), 'owner_id' => $map[$row['sheet']], 'stage' => $row['stage'], 'week_start' => $row['period_start'] ?? '1900-01-01', 'proposed_amount' => $row['proposed_amount'], 'po_amount' => $row['po_amount'], 'bottlenecks' => $row['bottlenecks'], 'next_action' => $row['next_action'], 'remarks' => 'Imported source snapshot. PO and weighted forecast are not recognized revenue. '.($row['confirmed'] ? 'Period from the maintained source block.' : 'Reporting date requires reconciliation; excluded from performance totals.'), 'source_reference' => $data['source_id'].' / '.$row['sheet'].' / row '.$row['row'], 'created_by' => $actor->id, 'updated_by' => $actor->id]);
                // Imported monthly/historical snapshots are not presented as a weekly update without a confirmed week.
                PerformanceWorkflow::record('crm_project', $p->id, $actor->id, 'import', [], $p->getAttributes(), 'Source workbook imported; monetary actuals are not inferred from PO or forecast');
                $counts['projects']++;
            }
            foreach ($data['targets'] as $row) {
                if (! isset($map[$row['sheet']])) {
                    throw new \RuntimeException('Target owner mapping required for '.$row['sheet']);
                }
                $dated = $row['confirmed'] && $row['year'] && $row['month'];
                $from = $dated ? \Illuminate\Support\Carbon::create($row['year'], $row['month'], 1) : null;
                $to = $from?->copy()->endOfMonth();
                $needs = ! $dated || $row['amount'] === null;
                if ($needs) {
                    $counts['undated_targets']++;
                }
                $target = CrmAccountTarget::firstOrCreate(['reference' => 'import-'.hash('sha256', $data['source_id'].'|'.$row['sheet'].'|'.$row['row'])], ['client_name' => 'All accounts · '.$row['sheet'], 'owner_id' => $map[$row['sheet']], 'period_start' => $from?->toDateString(), 'period_end' => $to?->toDateString(), 'frequency' => $row['kind'], 'target_amount' => $row['amount'], 'source_reference' => $row['sheet'].' / row '.$row['row'], 'source_period_label' => $row['label'] ?? 'Period not stated', 'needs_reconciliation' => $needs]);
                if ($target->wasRecentlyCreated) {
                    $counts['targets']++;
                }
            }
        });
        $this->info(json_encode($counts));

        return self::SUCCESS;
    }
}
