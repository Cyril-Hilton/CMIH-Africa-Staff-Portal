<?php

namespace App\Services;

use App\Http\Controllers\Portal\PerformanceHubController;
use App\Models\PerformanceReportPack;
use App\Models\User;
use Illuminate\Http\Request;

class PerformanceReportPacks
{
    public function capture(User $user, string $frequency, string $from, string $to): PerformanceReportPack
    {
        $scope = ['user_id' => $user->id, 'frequency' => $frequency, 'period_start' => $from.' 00:00:00', 'period_end' => $to.' 00:00:00'];
        $existing = PerformanceReportPack::where($scope)->first();
        if ($existing) {
            return $existing;
        }
        $request = Request::create('/portal/performance/export/json', 'GET', ['from' => $from, 'to' => $to, 'view' => 'meeting']);
        $request->setUserResolver(fn () => $user);
        $payload = app(PerformanceHubController::class)->export($request, 'json')->getData(true);
        $data = app(PerformanceHubController::class)->index($request)->getData();
        $payload['snapshots'] = [
            'definitions' => $data['kpis']->map(fn ($row) => $row->getAttributes())->values()->all(),
            'target_plans' => $data['targetPlans']->map(fn ($row) => $row->getAttributes())->values()->all(),
            'approved_results' => $data['observations']->whereNotNull('approved_at')->map(fn ($row) => $row->getAttributes())->values()->all(),
            'approved_sources' => $data['registers']->whereNotNull('approved_at')->map(fn ($row) => $row->getAttributes())->values()->all(),
            'evidence' => \App\Models\PerformanceEvidence::whereIn('commitment_id', $data['items']->pluck('id'))->get(['id', 'commitment_id', 'original_name', 'mime', 'bytes', 'sha256', 'created_at'])->toArray(),
        ];
        $payload['captured_at'] = now()->toIso8601String();
        $payload['scope'] = ['user_id' => $user->id, 'department' => PerformanceAccess::executive($user) ? 'enterprise' : PerformanceAccess::department($user)];
        $payload['methodology'] = 'Frozen recorded state at capture time. Later corrections appear in later packs; historic periods are not reconstructed from future knowledge.';
        $payload['definition_versions'] = \App\Models\PerformanceKpi::query()->when(! PerformanceAccess::executive($user), fn ($q) => $q->where('department', PerformanceAccess::department($user)))->pluck('version', 'id')->all();

        return PerformanceReportPack::firstOrCreate($scope, ['payload' => $payload, 'sha256' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 'created_at' => now()]);
    }
}
