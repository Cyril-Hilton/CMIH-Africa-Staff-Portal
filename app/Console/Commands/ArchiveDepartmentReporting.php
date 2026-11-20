<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ReportingReset;
use Illuminate\Console\Command;

class ArchiveDepartmentReporting extends Command
{
    protected $signature = 'reporting:archive {--execute : Apply the reviewed archive plan} {--actor= : CVO or Super Admin ID} {--label=Department dashboard reporting reset} {--backup= : Private verified backup reference}';

    protected $description = 'Plan or apply a reversible reporting reset while preserving core operational records and account names';

    public function handle(ReportingReset $reset): int
    {
        if (! $this->option('execute')) {
            $this->line(json_encode($reset->plan(), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }
        $actor = User::findOrFail($this->option('actor'));
        if (! $actor->isActive() || ! $actor->isCvoOrSuperAdmin()) {
            $this->error('An active CVO or Super Admin must authorize this archive.');

            return self::FAILURE;
        }
        if (! $this->option('backup')) {
            $this->error('A verified private backup reference is required.');

            return self::FAILURE;
        }
        $this->line(json_encode($reset->archive($actor, $this->option('label'), $this->option('backup')), JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
