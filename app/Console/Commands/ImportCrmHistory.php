<?php

namespace App\Console\Commands;

use App\Models\CrmSourceHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCrmHistory extends Command
{
    protected $signature = 'crm:import-history {file : Extracted source JSON}';

    protected $description = 'Import traceable CRM workbook history without converting forecasts into earned revenue';

    public function handle(): int
    {
        $data = json_decode(file_get_contents($this->argument('file')), true, 512, JSON_THROW_ON_ERROR);
        if (($data['source_id'] ?? null) !== '1ydsMLK86iDG5XPp1PcC3_qjh_2CjeJ_-') {
            $this->error('Unexpected source workbook');

            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            foreach ($data['rows'] as $row) {
                CrmSourceHistory::updateOrCreate(['source_id' => $data['source_id'], 'sheet' => $row['sheet'], 'source_row' => $row['source_row']], collect($row)->only(['hidden_sheet', 'cells', 'formulas', 'checksum'])->all() + ['imported_at' => now()]);
            }
        });
        $this->info(count($data['rows']).' source rows imported; repeat imports update matching source rows.');

        return self::SUCCESS;
    }
}
