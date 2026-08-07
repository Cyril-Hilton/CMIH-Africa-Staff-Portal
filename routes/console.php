<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('autoclock:superusers', function () {
    \App\Services\AutoClockService::runAll();
    $this->info('Developer and super admin accounts successfully clocked in, clocked out, and backfilled for the current month.');
})->purpose('Auto clock in and out developer and super admin accounts');

\Illuminate\Support\Facades\Schedule::command('autoclock:superusers')->dailyAt('00:01');

Artisan::command('awards:backfill-autoclock {period? : Optional period in YYYY-MM or YYYY format}', function () {
    $period = (string) ($this->argument('period') ?: now()->format('Y-m'));

    try {
        if (strlen($period) === 7) {
            $startDate = \Illuminate\Support\Carbon::createFromFormat('Y-m', $period)->startOfMonth();
            $endDate = \Illuminate\Support\Carbon::createFromFormat('Y-m', $period)->endOfMonth();
        } else {
            $startDate = \Illuminate\Support\Carbon::createFromFormat('Y', $period)->startOfYear();
            $endDate = \Illuminate\Support\Carbon::createFromFormat('Y', $period)->endOfYear();
        }
    } catch (\Throwable) {
        $this->error('Invalid period. Use YYYY-MM or YYYY.');
        return 1;
    }

    \App\Services\AutoClockService::backfillAll($startDate, $endDate);
    $this->info("Auto-clock award backfill completed for {$period}.");

    return 0;
})->purpose('Backfill auto-clock staff data for award scoring without mutating standings reads');

\Illuminate\Support\Facades\Schedule::command('awards:backfill-autoclock')
    ->dailyAt('00:05')
    ->timezone('Africa/Accra');

Artisan::command('db:backup-sqlite {--keep=14 : Number of daily SQLite backups to retain}', function () {
    $connection = config('database.default');

    if ($connection !== 'sqlite') {
        $this->info("Database backup skipped: current connection is {$connection}, not sqlite.");
        return 0;
    }

    $databasePath = config("database.connections.{$connection}.database");
    if (! is_string($databasePath) || $databasePath === '' || $databasePath === ':memory:') {
        $this->error('SQLite database path is not a file-backed database.');
        return 1;
    }

    $source = str_starts_with($databasePath, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $databasePath)
        ? $databasePath
        : database_path($databasePath);

    $realSource = realpath($source);
    if (! $realSource || ! is_file($realSource)) {
        $this->error("SQLite database file not found: {$source}");
        return 1;
    }

    $backupDir = storage_path('app/backups/database');
    \Illuminate\Support\Facades\File::ensureDirectoryExists($backupDir);

    $stamp = now()->format('Ymd-His');
    $target = $backupDir . DIRECTORY_SEPARATOR . "database-{$stamp}.sqlite";
    if (! copy($realSource, $target)) {
        $this->error("Failed to copy SQLite database to {$target}");
        return 1;
    }

    foreach (['-wal', '-shm'] as $suffix) {
        $sidecar = $realSource . $suffix;
        if (is_file($sidecar)) {
            copy($sidecar, $target . $suffix);
        }
    }

    $keep = max(1, (int) $this->option('keep'));
    $backups = collect(glob($backupDir . DIRECTORY_SEPARATOR . 'database-*.sqlite') ?: [])
        ->sortDesc()
        ->values();

    $backups->slice($keep)->each(function (string $oldBackup) {
        @unlink($oldBackup);
        @unlink($oldBackup . '-wal');
        @unlink($oldBackup . '-shm');
    });

    $this->info("SQLite database backup created: {$target}");
    return 0;
})->purpose('Create a timestamped SQLite database backup and prune old backups');

\Illuminate\Support\Facades\Schedule::command('db:backup-sqlite --keep=14')
    ->dailyAt('02:10')
    ->timezone('Africa/Accra');

Artisan::command('app:send-birthday-wishes', function () {
    \App\Services\NotificationService::checkAndSendBirthdayWishes();
    $this->info('Birthday wishes checked & dispatched successfully.');
})->purpose('Check active user birthdays and dispatch notifications');

\Illuminate\Support\Facades\Schedule::command('app:send-birthday-wishes')->dailyAt('08:00');

Artisan::command('app:send-workday-reminders {period : morning or evening}', function () {
    $period = strtolower((string) $this->argument('period'));

    $result = match ($period) {
        'morning' => \App\Services\WorkdayReminderService::sendMorningReminder(),
        'evening' => \App\Services\WorkdayReminderService::sendEveningReminder(),
        default => null,
    };

    if (! $result) {
        $this->error('Invalid period. Use "morning" or "evening".');
        return 1;
    }

    if ($result['skipped'] ?? false) {
        $this->info("Workday {$period} reminders were already sent today.");
        return 0;
    }

    $summary = "Sent {$result['sent']} {$period} reminder(s)";
    if (array_key_exists('manager_sent', $result)) {
        $summary .= " and {$result['manager_sent']} manager approval reminder(s)";
    }

    $this->info($summary . '.');
    return 0;
})->purpose('Send daily workday clock-in and clock-out reminders');

\Illuminate\Support\Facades\Schedule::command('app:send-workday-reminders morning')
    ->weekdays()
    ->dailyAt('09:00')
    ->timezone('Africa/Accra');

\Illuminate\Support\Facades\Schedule::command('app:send-workday-reminders evening')
    ->weekdays()
    ->dailyAt('18:00')
    ->timezone('Africa/Accra');

Artisan::command('sku-ai:generate-fixtures {--count=8} {--output=storage/app/sku-ai-fixtures}', function () {
    $result = app(\App\Support\SkuShelfFixtureGenerator::class)->generate(
        (int) $this->option('count'),
        (string) $this->option('output')
    );

    $this->info("Generated {$result['count']} SKU AI shelf fixtures.");
    $this->line("Path: {$result['path']}");
    $this->line("Manifest: {$result['manifest']}");
})->purpose('Generate synthetic shelf image fixtures with expected SKU counts for AI prompt regression');

\Illuminate\Support\Facades\Schedule::command('messenger:archive-old-media --days=60 --limit=250')->twiceMonthly(1, 16, '02:30');
