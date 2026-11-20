<?php

return [
    // Reporting/workflow records only. Operational registers and KPI definitions are deliberately separate.
    'models' => [
        App\Models\Task::class, App\Models\WeeklyConsolidatedItem::class,
        App\Models\PerformanceRegister::class, App\Models\PerformanceObservation::class,
        App\Models\CrmPipelineProject::class, App\Models\CrmClientRecord::class,
        App\Models\CrmWeeklyProgress::class, App\Models\CrmAccountTarget::class,
        App\Models\CrmAccountAllocation::class, App\Models\CrmAccountRelationship::class,
        App\Models\CrmFinancialRegister::class, App\Models\CrmSourceHistory::class,
        App\Models\CrmReportSubmission::class, App\Models\CreativeRequest::class,
        App\Models\OperationsHandover::class, App\Models\OperationsReportSubmission::class,
        App\Models\BrandsReportSubmission::class, App\Models\ProjectExecutionControl::class,
        App\Models\HrDashboardActivity::class, App\Models\HrPeriodicReport::class,
        App\Models\HrReportEntry::class, App\Models\AdminWeeklyReport::class,
        App\Models\AdminWeeklyReportLine::class, App\Models\PerformanceReportPack::class,
        App\Models\PerformanceDependency::class, App\Models\PerformanceDependencyRequest::class,
    ],
    'account_models' => [App\Models\CrmPipelineProject::class, App\Models\CrmClientRecord::class, App\Models\CrmAccountAllocation::class, App\Models\CrmAccountRelationship::class, App\Models\CrmFinancialRegister::class],
    'protected_tables' => ['users', 'assets', 'asset_logs', 'asset_warehouse_requests', 'warehouse_asset_suggestions', 'posm_ledgers', 'third_party_vendors', 'phone_directory', 'leave_applications', 'pass_out_requests', 'fleet_requests', 'boardroom_requests', 'attendance', 'salary_advances', 'supplier_invoices', 'petty_cash_claims', 'project_budgets', 'campaigns', 'hr_register_records', 'payroll_runs', 'payroll_entries', 'payslips', 'performance_kpis', 'performance_objectives', 'performance_target_plans', 'performance_policies'],
];
