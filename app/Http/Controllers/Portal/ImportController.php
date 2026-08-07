<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ImportController extends Controller
{
    /**
     * Map clean model names to model classes
     */
    protected function getModelClass(string $table): ?string
    {
        $map = [
            'appraisal_metrics' => \App\Models\AppraisalMetric::class,
            'freelance_promoters' => \App\Models\FreelancePromoter::class,
            'visitor_logs' => \App\Models\VisitorLog::class,
            'petty_cash_claims' => \App\Models\PettyCashClaim::class,
            'tasks' => \App\Models\Task::class,
            'campaigns' => \App\Models\Campaign::class,
        ];

        return $map[strtolower($table)] ?? null;
    }

    /**
     * Show upload file view
     */
    public function showUploadForm(Request $request, string $table): View
    {
        $modelClass = $this->getModelClass($table);
        if (!$modelClass) {
            abort(404, 'Invalid table name specified for import.');
        }

        $this->authorizeImport($request->user(), $table);

        $tableName = (new $modelClass)->getTable();
        $columns = Schema::getColumnListing($tableName);

        // Exclude system columns
        $columns = array_filter($columns, function ($col) {
            return !in_array($col, ['id', 'created_at', 'updated_at', 'deleted_at', 'email_verified_at', 'remember_token', 'password'], true);
        });

        return view('portal.import', compact('table', 'columns'));
    }

    /**
     * Handle initial CSV upload and parse headers
     */
    public function processUpload(Request $request, string $table): View|RedirectResponse
    {
        $this->authorizeImport($request->user(), $table);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $modelClass = $this->getModelClass($table);
        if (!$modelClass) {
            abort(404);
        }

        $file = $request->file('file');
        $filePath = $file->getRealPath();

        // Parse CSV headers
        $headers = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $headers = fgetcsv($handle, 1000, ',');
            fclose($handle);
        }

        if (empty($headers)) {
            return back()->withErrors(['file' => 'The uploaded file does not contain a valid header row.']);
        }

        // Clean headers
        $headers = array_map(function($h) {
            return trim($h, "\xEF\xBB\xBF\r\n\t "); // Clean BOM and whitespace
        }, $headers);

        // Save temporary file in local cache or storage to read during mapping step
        $storedName = Str::random(40) . '.csv';
        $file->storeAs('temp_imports', $storedName, 'local');

        $tableName = (new $modelClass)->getTable();
        $columns = Schema::getColumnListing($tableName);
        $columns = array_filter($columns, function ($col) {
            return !in_array($col, ['id', 'created_at', 'updated_at', 'deleted_at', 'email_verified_at', 'remember_token', 'password'], true);
        });

        return view('portal.import', [
            'table' => $table,
            'columns' => $columns,
            'headers' => $headers,
            'temp_file' => $storedName
        ]);
    }

    /**
     * Execute the import with user-mapped columns
     */
    public function executeImport(Request $request, string $table): RedirectResponse
    {
        $this->authorizeImport($request->user(), $table);

        $request->validate([
            'temp_file' => ['required', 'string'],
            'mappings' => ['required', 'array'],
        ]);

        $modelClass = $this->getModelClass($table);
        if (!$modelClass) {
            abort(404);
        }

        $user = $request->user();
        $tempFile = $request->input('temp_file');
        $mappings = $request->input('mappings'); // maps database_column => csv_header_index
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $relativePath = 'temp_imports/' . $tempFile;

        if (!$disk->exists($relativePath)) {
            return redirect()->route('dashboard')->withErrors(['error' => 'Temporary import file expired or missing.']);
        }

        $csvContent = $disk->get($relativePath);
        $lines = explode("\n", str_replace("\r", "", $csvContent));
        
        // Skip header row
        array_shift($lines);

        $importedCount = 0;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = str_getcsv($line, ',');
            $data = [];
            $hasValidData = false;

            foreach ($mappings as $dbCol => $csvIndex) {
                if ($csvIndex !== '' && isset($row[$csvIndex])) {
                    $val = trim($row[$csvIndex]);
                    $data[$dbCol] = $val === '' ? null : $val;
                    $hasValidData = true;
                }
            }

            if ($hasValidData) {
                $data = $this->scopeImportedData($table, $data, $user);

                // Populate default required fields if empty
                if ($table === 'tasks') {
                    $data['assigned_to'] = $data['assigned_to'] ?? $user->id;
                    $data['assigned_by'] = $data['assigned_by'] ?? $user->id;
                    $data['department'] = $data['department'] ?? $user->department ?? 'operations';
                    $data['status'] = $data['status'] ?? 'Open';
                    $data['priority'] = $data['priority'] ?? 'Medium';
                    $data['progress'] = $data['progress'] ?? 0;
                } elseif ($table === 'visitor_logs') {
                    $data['status'] = $data['status'] ?? 'checked_in';
                    $data['host_id'] = $data['host_id'] ?? $user->id;
                } elseif ($table === 'petty_cash_claims') {
                    $data['user_id'] = $data['user_id'] ?? $user->id;
                    $data['status'] = $data['status'] ?? 'Pending';
                } elseif ($table === 'campaigns') {
                    $data['created_by'] = $data['created_by'] ?? $user->id;
                    $data['status'] = $data['status'] ?? 'active';
                }

                $modelClass::create($data);
                $importedCount++;
            }
        }

        $disk->delete($relativePath); // cleanup

        return redirect()->route('portal.import.show', ['table' => $table])
            ->with('status', "Imported {$importedCount} records successfully into the " . ucfirst(str_replace('_', ' ', $table)) . " database.");
    }

    private function authorizeImport(?User $user, string $table): void
    {
        if (!$user || !$this->canImportTable($user, $table)) {
            abort(403, 'You are not authorised to import this table.');
        }
    }

    private function canImportTable(User $user, string $table): bool
    {
        if ($this->canManageAllData($user)) {
            return true;
        }

        $table = strtolower($table);
        $department = $this->department($user);

        return match ($table) {
            'tasks', 'petty_cash_claims', 'visitor_logs' => true,
            'freelance_promoters' => in_array($department, ['operations_projects', 'operations', 'client_relations', 'brands_marketing', 'hr_admin', 'admin'], true),
            'campaigns' => $this->isOperationsUser($user),
            default => false,
        };
    }

    private function scopeImportedData(string $table, array $data, User $user): array
    {
        if ($this->canManageAllData($user)) {
            return $data;
        }

        $table = strtolower($table);

        if ($table === 'tasks') {
            $data['assigned_by'] = $user->id;

            if (!isset($data['assigned_to']) || !is_numeric($data['assigned_to'])) {
                $data['assigned_to'] = $user->id;
            }

            $data['department'] = User::query()->whereKey((int) $data['assigned_to'])->value('department')
                ?: $user->department
                ?: 'operations';

            if ((int) $data['assigned_to'] !== (int) $user->id) {
                $supportingStaffIds = $this->normalizedIdList($data['supporting_staff_ids'] ?? []);
                $supportingStaffIds[] = (int) $user->id;
                $data['supporting_staff_ids'] = array_values(array_unique($supportingStaffIds));
            }
        }

        if ($table === 'petty_cash_claims' && !$this->isFinanceUser($user)) {
            $data['user_id'] = $user->id;
        }

        if ($table === 'visitor_logs') {
            $data['host_id'] = $user->id;
        }

        if ($table === 'campaigns') {
            $data['created_by'] = $user->id;
        }

        return $data;
    }

    private function canManageAllData(User $user): bool
    {
        return $user->isCvoOrSuperAdmin()
            || $user->hasFullHrAccess()
            || $user->hasRole('admin');
    }

    private function isFinanceUser(User $user): bool
    {
        return $this->department($user) === 'finance';
    }

    private function isOperationsUser(User $user): bool
    {
        return in_array($this->department($user), ['operations_projects', 'operations'], true);
    }

    private function department(User $user): string
    {
        return strtolower(trim((string) $user->department));
    }

    private function normalizedIdList(mixed $ids): array
    {
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : preg_split('/[,|;]/', $ids);
        }

        if (!is_array($ids)) {
            $ids = $ids ? [$ids] : [];
        }

        return collect($ids)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
