<?php

return [
    'client_relations' => [
        'client' => ['revenue_target' => 'money', 'recognized_revenue' => 'money', 'prior_revenue' => 'money', 'new_business' => 'money', 'cross_sell_value' => 'money', 'retained' => 'number', 'renewal_due' => 'date', 'satisfaction_score' => 'number', 'meetings' => 'number', 'engagements' => 'number'],
        'proposal' => ['value' => 'money', 'submitted_on' => 'date', 'decision_on' => 'date'],
        'client_issue' => ['opened_on' => 'date', 'resolved_on' => 'date', 'sla_due' => 'date', 'impact' => 'text'],
    ],
    'creatives' => [
        'brief' => ['received_on' => 'date', 'accepted_on' => 'date', 'concept_submitted_on' => 'date', 'concept_approved_on' => 'date', 'final_delivered_on' => 'date', 'revision_rounds' => 'number', 'first_time_approved' => 'number', 'quality_score' => 'number', 'planned_hours' => 'number', 'actual_hours' => 'number', 'capacity_hours' => 'number'],
    ],
    'operations_projects' => array_merge((require __DIR__.'/operations_tracker.php')['tables'], [
        'project' => ['awarded_on' => 'date', 'started_on' => 'date', 'completed_on' => 'date', 'budget' => 'money', 'actual_cost' => 'money', 'revenue' => 'money', 'cost_to_completion' => 'money', 'rework_count' => 'number', 'complaints' => 'number', 'quality_issues' => 'number', 'satisfaction_score' => 'number'] + (require __DIR__.'/operations_tracker.php')['project_fields'],
        'milestone' => ['planned_on' => 'date', 'delivered_on' => 'date', 'impact' => 'text'],
        'risk' => ['probability' => 'number', 'exposure' => 'money', 'mitigation' => 'text'],
    ]),
    'finance' => [
        'profitability' => ['revenue' => 'money', 'direct_cost' => 'money', 'variable_cost' => 'money', 'gross_profit_target' => 'money'],
        'revenue' => ['target' => 'money', 'actual' => 'money', 'client' => 'text', 'function' => 'text'],
        'invoice' => ['value' => 'money', 'raised_on' => 'date', 'submitted_on' => 'date', 'approved_on' => 'date', 'due_on' => 'date', 'paid_on' => 'date', 'paid_amount' => 'money', 'credited_amount' => 'money'],
        'payable' => ['value' => 'money', 'due_on' => 'date', 'paid_amount' => 'money', 'credited_amount' => 'money', 'supplier' => 'text'],
        'cash' => ['account_ref' => 'text', 'closing_balance' => 'money', 'inflow' => 'money', 'outflow' => 'money', 'forecast' => 'money'],
        'expense' => ['budget' => 'money', 'actual' => 'money', 'cost_center' => 'text'],
    ],
    'hr_admin' => [
        'workforce' => ['approved_headcount' => 'number', 'actual_headcount' => 'number', 'new_hires' => 'number', 'departures' => 'number', 'average_headcount' => 'number', 'eligible_days' => 'number', 'attended_days' => 'number', 'late_days' => 'number', 'leave_days' => 'number', 'reviews_due' => 'number', 'reviews_completed' => 'number'],
        'vacancy' => ['approved_on' => 'date', 'target_fill_on' => 'date', 'offer_accepted_on' => 'date', 'candidates' => 'number', 'interviews' => 'number', 'offers' => 'number'],
        'employee_case' => ['case_type' => 'text', 'opened_on' => 'date', 'resolved_on' => 'date', 'due_on' => 'date', 'action' => 'text'],
        'improvement_plan' => ['review_due' => 'date', 'resolved_on' => 'date', 'action' => 'text'],
    ],
    'admin_drivers' => [
        'vehicle' => ['available_hours' => 'number', 'scheduled_hours' => 'number', 'used_hours' => 'number', 'downtime_hours' => 'number'],
        'fuel' => ['litres' => 'number', 'cost' => 'money', 'budget' => 'money', 'distance_km' => 'number'],
        'maintenance' => ['scheduled_on' => 'date', 'completed_on' => 'date', 'repair_cost' => 'money'],
        'trip' => ['due_on' => 'date', 'delivered_on' => 'date', 'distance_km' => 'number', 'fuel_litres' => 'number'],
        'asset' => ['asset_ref' => 'text', 'condition' => 'text', 'allocated_to' => 'text', 'missing_count' => 'number', 'damaged_count' => 'number'],
        'facility_request' => ['opened_on' => 'date', 'due_on' => 'date', 'resolved_on' => 'date', 'impact' => 'text'],
    ],
    'brands_marketing' => [
        // Keep the campaign key and legacy measures so existing records remain editable.
        'campaign' => [
            'workstream' => 'text', 'brand' => 'text', 'channel' => 'text', 'region' => 'text',
            'outlets_planned' => 'number', 'outlets_visited' => 'number', 'retailers_supported' => 'number',
            'merchandisers_deployed' => 'number', 'promoters_deployed' => 'number', 'training_completed' => 'number',
            'coverage_target_percent' => 'number', 'coverage_actual_percent' => 'number', 'coverage_previous_percent' => 'number',
            'osa_target_percent' => 'number', 'osa_actual_percent' => 'number', 'osa_previous_percent' => 'number',
            'npd_target_percent' => 'number', 'npd_actual_percent' => 'number',
            'must_have_target_percent' => 'number', 'must_have_actual_percent' => 'number',
            'perfect_store_target_percent' => 'number', 'perfect_store_actual_percent' => 'number',
            'report_due_on' => 'date', 'report_shared_on' => 'date', 'dashboard_refreshed_on' => 'date',
            'insights' => 'text', 'bottleneck' => 'text', 'next_action' => 'text', 'action_due_on' => 'date',
            'assets_planned' => 'number', 'assets_delivered' => 'number', 'reach' => 'number',
            'interactions' => 'number', 'qualified_leads' => 'number', 'budget' => 'money',
            'spend' => 'money', 'impressions' => 'number',
        ],
    ],
];
