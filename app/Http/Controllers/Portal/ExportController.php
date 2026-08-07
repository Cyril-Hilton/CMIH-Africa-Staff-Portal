<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Map clean model names to model classes
     */
    protected function getModelClass(string $table): ?string
    {
        $map = [
            'tasks' => \App\Models\Task::class,
            'petty_cash_claims' => \App\Models\PettyCashClaim::class,
            'freelance_promoters' => \App\Models\FreelancePromoter::class,
            'assets' => \App\Models\Asset::class,
            'asset_logs' => \App\Models\AssetLog::class,
            'attendance' => \App\Models\Attendance::class,
            'appraisal_metrics' => \App\Models\AppraisalMetric::class,
            'campaigns' => \App\Models\Campaign::class,
        ];

        return $map[strtolower($table)] ?? null;
    }

    /**
     * Export designated model table to downloadable CSV
     */
    public function export(Request $request, string $table): StreamedResponse
    {
        $modelClass = $this->getModelClass($table);
        if (!$modelClass) {
            abort(404, 'Invalid table name specified for export.');
        }

        $user = $request->user();
        $this->authorizeExport($user, $table);
        $query = $this->scopedExportQuery($modelClass, $user, $table);

        $tableName = (new $modelClass)->getTable();
        $columns = Schema::getColumnListing($tableName);
        
        // Exclude system sensitive columns
        $columns = array_filter($columns, function ($col) {
            return !in_array($col, ['password', 'remember_token', 'email_verified_at'], true);
        });

        $fileName = $table . '_export_' . date('Y-m-d_H-i-s') . '.csv';

        $response = new StreamedResponse(function () use ($query, $columns) {
            $handle = fopen('php://output', 'w');

            // Write BOM for Excel UTF-8 compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write CSV headers
            fputcsv($handle, array_map('ucfirst', $columns));

            // Write CSV rows
            $query->chunk(500, function ($records) use ($handle, $columns) {
                foreach ($records as $record) {
                    $row = [];
                    foreach ($columns as $column) {
                        $value = $record->{$column};
                        $row[] = is_array($value) || is_object($value)
                            ? json_encode($value, JSON_UNESCAPED_UNICODE)
                            : $value;
                    }
                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    private function authorizeExport(?User $user, string $table): void
    {
        if (!$user) {
            abort(403, 'You must be signed in to export portal data.');
        }

        if (!$this->canExportTable($user, $table)) {
            abort(403, 'You are not authorised to export this table.');
        }
    }

    private function canExportTable(User $user, string $table): bool
    {
        if ($this->canManageAllData($user)) {
            return true;
        }

        $department = $this->department($user);

        return match (strtolower($table)) {
            'tasks', 'petty_cash_claims', 'asset_logs', 'attendance' => true,
            'assets' => true,
            'freelance_promoters' => in_array($department, ['operations_projects', 'operations', 'client_relations', 'brands_marketing', 'hr_admin', 'admin'], true),
            'campaigns' => $this->isOperationsUser($user),
            'appraisal_metrics' => $user->isLineManager(),
            default => false,
        };
    }

    private function scopedExportQuery(string $modelClass, User $user, string $table): Builder
    {
        $query = $modelClass::query();

        if ($this->canManageAllData($user)) {
            return $query;
        }

        $subordinateIds = $user->subordinates()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $visibleUserIds = array_values(array_unique(array_merge([(int) $user->id], $subordinateIds)));

        return match (strtolower($table)) {
            'tasks' => $query->where(function (Builder $taskQuery) use ($user, $visibleUserIds) {
                $taskQuery->whereIn('assigned_to', $visibleUserIds)
                    ->orWhere('assigned_by', $user->id)
                    ->orWhereJsonContains('supporting_staff_ids', $user->id)
                    ->orWhereJsonContains('supporting_staff_ids', (string) $user->id)
                    ->orWhereJsonContains('copied_manager_ids', $user->id)
                    ->orWhereJsonContains('copied_manager_ids', (string) $user->id);
            }),
            'petty_cash_claims' => $this->isFinanceUser($user)
                ? $query
                : $query->whereIn('user_id', $visibleUserIds),
            'asset_logs' => $this->isOperationsUser($user)
                ? $query
                : $query->whereIn('user_id', $visibleUserIds),
            'assets' => $this->isAssetAdminUser($user)
                ? $query
                : $query->where(function (Builder $assetQuery) use ($user, $visibleUserIds) {
                    $assetQuery->whereIn('assigned_to', $visibleUserIds)
                        ->orWhere('added_by', $user->id);
                }),
            'campaigns' => $this->isOperationsUser($user)
                ? $query
                : $query->whereIn('created_by', $visibleUserIds),
            'attendance' => $query->whereIn('user_id', $visibleUserIds),
            default => $query,
        };
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

    private function isAssetAdminUser(User $user): bool
    {
        return $this->canManageAllData($user)
            || in_array($this->department($user), ['operations_projects', 'operations', 'hr_admin', 'admin'], true);
    }

    private function department(User $user): string
    {
        return strtolower(trim((string) $user->department));
    }
}
