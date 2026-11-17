<?php

namespace App\Console\Commands;

use App\Models\{PerformanceRegister, PosmLedger, User, WarehouseAssetSuggestion};
use App\Services\WarehouseAssetIntake;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueWarehouseAssetSources extends Command
{
    protected $signature = 'warehouse:queue-existing-assets';

    protected $description = 'Queue existing reported Operations and POSM assets for Warehouse review';

    public function handle(WarehouseAssetIntake $intake): int
    {
        $result = DB::transaction(function () use ($intake) {
            $queued = 0;
            $registers = PerformanceRegister::whereIn('department', User::departmentAliases('operations_projects'))
                ->whereNotNull('approved_at')->whereIn('kind', ['activation', 'project', 'weekly_update', 'posm_deployment', 'fabrication']);
            $registers->chunkById(100, function ($rows) use ($intake, &$queued) {
                foreach ($rows as $row) {
                    $queued += $intake->fromOperations($row);
                }
            });
            PosmLedger::whereNull('asset_id')->chunkById(100, function ($rows) use ($intake, &$queued) {
                foreach ($rows as $row) {
                    $queued += $intake->fromPosmLedger($row);
                }
            });
            return ['new_suggestions' => $queued, 'pending' => WarehouseAssetSuggestion::where('status', 'pending')->count(),
                'source_counts' => WarehouseAssetSuggestion::where('status', 'pending')->selectRaw('source_type, COUNT(*) AS total')
                    ->groupBy('source_type')->pluck('total', 'source_type')];
        });
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
