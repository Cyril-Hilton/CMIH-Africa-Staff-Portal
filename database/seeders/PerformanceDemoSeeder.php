<?php

namespace Database\Seeders;

use App\Models\CrmAccountTarget;
use App\Models\CrmPipelineProject;
use App\Models\PerformanceKpi;
use App\Models\PerformanceObjective;
use App\Models\PerformanceObservation;
use App\Models\PerformanceRegister;
use App\Models\PerformanceTargetPlan;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use App\Services\ClientRelationsPipeline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerformanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || config('database.default') !== 'sqlite' || basename(config('database.connections.sqlite.database')) !== 'cmih-upgrade-preview.sqlite') {
            throw new \RuntimeException('Demo seeder is restricted to the isolated preview database.');
        }
        DB::transaction(function () {
            $start = now()->startOfWeek();
            $end = now()->endOfWeek();
            $month = now()->startOfMonth();
            $names = ['brands_marketing' => ['Campaign assets delivered', 'assets', 12, 10], 'creatives' => ['Final artworks delivered', 'jobs', 10, 8], 'operations_projects' => ['Activation milestones delivered', 'milestones', 8, 6], 'finance' => ['Collections against plan', 'GHS', 200000, 140000], 'client_relations' => ['Recognised revenue against plan', 'GHS', 100000, 50000], 'hr_admin' => ['Training completions', 'people', 20, 18]];
            foreach ($names as $department => [$name,$unit,$target,$actual]) {
                $slug = str_replace('_', '-', $department);
                $manager = User::where('email', $slug.'.preview@example.test')->firstOrFail();
                $staff = User::where('email', $slug.'.staff@example.test')->firstOrFail();
                $reviewer = User::where('email', $slug.'.reviewer@example.test')->firstOrFail();
                $objective = PerformanceObjective::firstOrCreate(['department' => $department, 'title' => 'DEMO · Source-backed departmental results'], ['outcome' => 'Demonstrate performance and accountability with synthetic records', 'owner_id' => $manager->id, 'starts_on' => $month->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);
                foreach (['department', 'individual'] as $scope) {
                    $owner = $scope === 'department' ? $manager : $staff;
                    $kpi = PerformanceKpi::firstOrCreate(['department' => $department, 'name' => 'DEMO · '.$name.' · '.$scope], ['objective_id' => $objective->id, 'definition' => 'Synthetic result for visual and workflow testing only', 'formula' => 'Actual / target × 100', 'unit' => $unit, 'direction' => 'higher', 'aggregation' => 'sum', 'frequency' => 'weekly', 'source' => 'DEMO source register', 'owner_id' => $owner->id, 'reviewer_id' => $reviewer->id, 'green_threshold' => 100, 'amber_threshold' => 80, 'escalation_days' => 3, 'required_action' => 'Review the delivery gap and corrective action', 'dimension' => 'target_achievement', 'weight' => 1, 'evidence_required' => false]);
                    $plan = PerformanceTargetPlan::updateOrCreate(['scope_key' => 'demo-plan-'.$department.'-'.$scope], ['kpi_id' => $kpi->id, 'department' => $department, 'scope_type' => $scope, 'owner_id' => $owner->id, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(), 'target' => $target, 'weight' => 1, 'client' => 'DEMO client · '.$department, 'project' => 'DEMO weekly programme', 'rationale' => 'Synthetic preview only', 'submitted_by' => $manager->id, 'reviewer_id' => $reviewer->id, 'approved_by' => $reviewer->id, 'approved_at' => now()]);
                    PerformanceObservation::updateOrCreate(['target_plan_id' => $plan->id], ['scope_key' => 'demo-observation-'.$department.'-'.$scope, 'kpi_id' => $kpi->id, 'owner_id' => $owner->id, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(), 'target' => $target, 'actual' => $actual, 'source_reference' => 'DEMO evidence · not real company performance', 'submitted_by' => $manager->id, 'approved_by' => $reviewer->id, 'approved_at' => now()]);
                }
                foreach (['verified', 'executing', 'review'] as $index => $state) {
                    $due = $index === 1 ? today()->subDay() : $end->copy()->subDays(2);
                    $demoItem = WeeklyConsolidatedItem::updateOrCreate(['department' => $department, 'campaign_name' => 'DEMO · '.$department.' · '.($index + 1)], ['week_start' => $start->toDateString(), 'week_end' => $due->toDateString(), 'brief_received_on' => $start->copy()->subDays(3)->toDateString(), 'client_name' => 'DEMO client '.($index + 1), 'lead_staff_id' => $staff->id, 'reviewer_id' => $reviewer->id, 'deliverables' => ['Deliver the approved campaign output', 'Complete the delayed client deliverable', 'Submit evidence for independent review'][$index], 'target_breakdown' => 'Agreed delivery milestones', 'achieved_breakdown' => 'Progress recorded in governed review', 'gap_breakdown' => 'See approved KPI results', 'status' => $state === 'verified' ? 'Done' : 'In Progress', 'workflow_state' => $state, 'record_type' => 'deliverable', 'progress_percent' => [100, 45, 100][$index], 'quality_score' => $state === 'verified' ? 92 : null, 'completed_at' => $state === 'verified' ? now() : null, 'verified_at' => $state === 'verified' ? now() : null, 'root_cause' => $index === 1 ? 'client_dependency' : null, 'risk_level' => $index === 1 ? 'high' : 'low', 'created_by' => $manager->id, 'updated_by' => $manager->id]);
                    if ($state === 'verified' && ! \App\Models\PerformanceEvent::where('entity_type', 'commitment')->where('entity_id', $demoItem->id)->where('action', 'verify')->exists()) {
                        \App\Services\PerformanceWorkflow::record('commitment', $demoItem->id, $manager->id, 'verify', [], $demoItem->getAttributes(), 'Synthetic demo manager sign-off; not real staff performance');
                    }
                }
                if (in_array($department, ['brands_marketing', 'creatives'])) {
                    foreach (['Launch artwork', 'Retail content pack'] as $index => $title) {
                        WeeklyConsolidatedItem::updateOrCreate(['department' => $department, 'campaign_name' => 'DEMO WEEKLY · '.$title], ['week_start' => $start->toDateString(), 'week_end' => $end->copy()->subDays(2)->toDateString(), 'brief_received_on' => $start->copy()->subDays(3)->toDateString(), 'client_name' => 'DEMO · '.$title, 'lead_staff_id' => $staff->id, 'deliverables' => 'DEMO brief: produce approved launch assets and publish the final content pack.', 'target_breakdown' => 'Deliver '.($index + 3).' approved assets', 'achieved_breakdown' => 'Delivered '.($index + 2).' assets', 'gap_breakdown' => 'One asset awaiting review', 'status' => 'In Progress', 'workflow_state' => 'legacy', 'progress_percent' => 75, 'created_by' => $manager->id, 'updated_by' => $manager->id]);
                    }
                }
                foreach (config('department_reporting.'.$department, []) as $kind => $fields) {
                    $data = [];
                    foreach ($fields as $field => $type) {
                        $data[$field] = match ($type) {
                            'date' => $start->toDateString(),'number' => 10,'money' => 10000,default => 'DEMO · '.str_replace('_', ' ', $field)
                        };
                    }
                    if ($kind === 'campaign') {
                        $data = array_replace($data, ['assets_planned' => 12, 'assets_delivered' => 10, 'reach' => 25000, 'interactions' => 2500, 'qualified_leads' => 150, 'impressions' => 40000]);
                    }
                    if ($kind === 'invoice') {
                        $data = array_replace($data, ['value' => 10000, 'paid_amount' => 5000, 'due_on' => today()->subDays(8)->toDateString(), 'paid_on' => null]);
                    }
                    if ($kind === 'project') {
                        $data = array_replace($data, ['budget' => 10000, 'actual_cost' => 11500, 'revenue' => 15000, 'progress_percent' => 65, 'due_on' => $end->toDateString()]);
                    }
                    PerformanceRegister::updateOrCreate(['department' => $department, 'kind' => $kind, 'reference' => 'DEMO-'.$department.'-'.$kind], ['title' => 'DEMO · '.ucwords(str_replace('_', ' ', $kind)), 'owner_id' => $staff->id, 'submitted_by' => $manager->id, 'client' => 'DEMO client', 'project' => 'DEMO weekly programme', 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(), 'status' => 'in_progress', 'data' => $data, 'evidence_url' => 'https://example.com/demo-evidence', 'approved_by' => $reviewer->id, 'approved_at' => now()]);
                }
            }
            $hr = User::where('email', 'hr-admin.preview@example.test')->firstOrFail();
            $hrReviewer = User::where('email', 'hr-admin.reviewer@example.test')->firstOrFail();
            $hrReport = \App\Models\HrPeriodicReport::firstOrCreate(['prepared_by' => $hr->id, 'frequency' => 'weekly', 'period_start' => $start->copy()->startOfDay(), 'period_end' => $end->copy()->subDays(2)->startOfDay()], ['prepared_on' => today(), 'status' => 'approved', 'approved_by' => $hrReviewer->id, 'approved_at' => now(), 'section_states' => array_fill_keys(array_keys(config('hr_reporting')), 'reported')]);
            foreach (config('hr_reporting') as $section => $spec) {
                $fields = [];
                foreach ($spec['fields'] as $field => $definition) {
                    $fields[$field] = match ($definition['type']) {
                        'number' => 5,'date' => $start->toDateString(),'owner' => $hr->id,'select' => $definition['options'][0],default => 'DEMO · '.str_replace('_', ' ', $field)
                    };
                }
                if ($section === 'metrics') {
                    $fields = ['metric' => 'Training completion rate', 'numerator' => 18, 'denominator' => 20, 'actual' => 90, 'notes' => 'DEMO · 18 of 20 staff completed training'];
                }
                if ($section === 'training') {
                    $fields['completed'] = 18;
                    $fields['required_completions'] = 20;
                    $fields['compliance_percent'] = 90;
                }
                \App\Models\HrReportEntry::firstOrCreate(['report_id' => $hrReport->id, 'section' => $section], ['data' => $fields]);
            }
            $admin = \App\Models\AdminWeeklyReport::firstOrCreate(['prepared_by' => $hr->id, 'period_start' => $start->copy()->startOfDay(), 'period_end' => $end->copy()->subDays(2)->startOfDay()], ['status' => 'approved', 'approved_by' => $hrReviewer->id, 'approved_at' => now()]);
            $numbers = ['total_vehicles' => 3, 'vehicles_available' => 2, 'total_drivers' => 3, 'attendance_rate' => 90, 'total_assets' => 33, 'assigned_assets' => 20, 'damaged_assets' => 2, 'missing_assets' => 0, 'fuel_litres' => 120, 'fuel_cost' => 2200, 'distance_km' => 600, 'consumption_per_100km' => 20];
            foreach (config('admin_weekly') as $section => $spec) {
                foreach ($spec['rows'] as $code => $field) {
                    \App\Models\AdminWeeklyReportLine::firstOrCreate(['report_id' => $admin->id, 'section' => $section, 'code' => $code], ['value_state' => 'reported', 'numeric_value' => $field['type'] === 'number' ? ($numbers[$code] ?? 1) : null, 'text_value' => $field['type'] === 'number' ? null : 'DEMO · '.$field['label'], 'status' => 'DEMO · In progress', 'issue' => 'DEMO · One repair awaiting supplier', 'action' => 'DEMO · Confirm repair and verify completion', 'owner_id' => $hr->id, 'due_date' => $end->toDateString()]);
                }
            }
            $owner = User::where('email', 'client-relations.preview@example.test')->firstOrFail();
            foreach ([['Campus product launch', 100000, 50000], ['Retail sampling', 80000, 88000], ['Beauty creator activation', 60000, 36000]] as $i => [$project,$target,$actual]) {
                $p = CrmPipelineProject::updateOrCreate(['source_reference' => 'DEMO-CRM-'.($i + 1)], ['client_name' => 'DEMO client '.($i + 1), 'brand' => 'DEMO brand', 'project_name' => 'DEMO · '.$project, 'owner_id' => $owner->id, 'stage' => $i === 1 ? 'project_completed' : 'project_ongoing', 'target_amount' => $target, 'actual_amount' => $actual, 'proposed_amount' => $target, 'po_amount' => $target, 'invoiced_amount' => $actual, 'collected_amount' => $actual * .8, 'po_state' => 'received', 'week_start' => $start->toDateString(), 'public_action' => 'deliver', 'created_by' => $owner->id, 'updated_by' => $owner->id]);
                app(ClientRelationsPipeline::class)->publish($p);
            }
            foreach (['weekly' => [$start, $end, 100000], 'monthly' => [$month, now()->endOfMonth(), 400000], 'yearly' => [now()->startOfYear(), now()->endOfYear(), 4800000]] as $frequency => [$from,$to,$amount]) {
                CrmAccountTarget::updateOrCreate(['reference' => 'DEMO-CRM-TARGET-'.$frequency], ['client_name' => 'DEMO all allocated accounts', 'owner_id' => $owner->id, 'frequency' => $frequency, 'period_start' => $from->toDateString(), 'period_end' => $to->toDateString(), 'target_amount' => $amount, 'source_reference' => 'Synthetic demo target']);
            }
        });
    }
}
