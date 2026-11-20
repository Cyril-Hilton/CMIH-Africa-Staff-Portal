<?php

// Every key below must be backed by its department's entry schema.
return [
    'registers' => [
        'finance' => [
            'commercial_risk' => ['due_on' => ['actual' => 'resolved_on']],
            '*' => ['due_on' => ['actual' => 'paid_on', 'mode' => 'settlement'], 'expected_on' => ['actual' => 'paid_on', 'mode' => 'settlement']],
        ],
        'operations_projects' => [
            'project_report' => ['due_on' => ['actual' => 'shared_on']],
            'risk_issue' => ['due_on' => ['actual' => 'resolved_on']],
            'team_allocation' => ['setup_on' => ['actual' => 'setup_completed_on']],
            '*' => ['due_on' => ['actual' => 'delivered_on'], 'required_on' => ['actual' => 'delivered_on'], 'planned_on' => ['actual' => 'delivered_on'], 'setup_on' => [], 'expected_return_on' => ['actual' => 'returned_on']],
        ],
        'admin_drivers' => [
            '*' => ['due_on' => ['actual' => 'delivered_on'], 'scheduled_on' => ['actual' => 'completed_on']],
        ],
        'hr_admin' => [
            'vacancy' => ['target_fill_on' => ['actual' => 'offer_accepted_on']],
            'employee_case' => ['due_on' => ['actual' => 'resolved_on']],
            'improvement_plan' => ['review_due' => ['actual' => 'resolved_on']],
        ],
        'client_relations' => ['client_issue' => ['sla_due' => ['actual' => 'resolved_on']]],
        'brands_marketing' => ['campaign' => ['report_due_on' => ['actual' => 'report_shared_on'], 'action_due_on' => []]],
    ],
    'hr_live' => [
        'vacancy' => ['target_fill_on' => ['actual' => 'filled_on']],
        'probation' => ['confirmation_due' => ['actual' => 'confirmed_on']],
        'appraisal_record' => ['due_on' => ['actual' => 'completed_on']],
        'improvement_plan' => ['due_on' => ['actual' => 'resolved_on']],
        'employee_case' => ['due_on' => ['actual' => 'resolved_on']],
        'welfare_case' => ['due_on' => ['actual' => 'resolved_on']],
        'policy' => ['review_on' => []],
        'exit' => ['last_day' => ['actual' => 'completed_on']],
        'facility' => ['service_due' => ['actual' => 'last_serviced', 'mode' => 'maintenance']],
        'vehicle' => ['insurance_expiry' => [], 'roadworthy_expiry' => [], 'service_due' => ['actual' => 'last_serviced', 'mode' => 'maintenance']],
        'driver' => ['licence_expiry' => []],
        'utility' => ['due_on' => ['actual' => 'paid_on']],
    ],
];
