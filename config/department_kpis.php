<?php

// Templates define measurement; management supplies targets and reporting periods.
return [
    'client_relations' => [
        ['name' => 'Revenue delivered', 'unit' => 'GHS', 'direction' => 'higher', 'definition' => 'Sum of recognized revenue for the reporting period; exclude pipeline.', 'source' => 'Approved finance ledger'],
        ['name' => 'Proposals submitted', 'unit' => 'proposals', 'direction' => 'higher', 'definition' => 'Count of proposals actually submitted in the reporting period.', 'source' => 'Proposal register'],
        ['name' => 'Collections received', 'unit' => 'GHS', 'direction' => 'higher', 'definition' => 'Cash received against client balances in the reporting period.', 'source' => 'Receipts ledger'],
        ['name' => 'Client issue resolution time', 'unit' => 'hours', 'direction' => 'lower', 'definition' => 'Mean elapsed hours to resolve client issues closed in the period.', 'source' => 'Client issue log'],
    ],
    'creatives' => [
        ['name' => 'Final artwork delivered', 'unit' => 'jobs', 'direction' => 'higher', 'definition' => 'Count of distinct final outputs delivered in the period.', 'source' => 'Creative job tracker'],
        ['name' => 'Average turnaround time', 'unit' => 'hours', 'direction' => 'lower', 'definition' => 'Mean hours from accepted brief to final delivery for completed jobs.', 'source' => 'Brief acceptance and delivery timestamps'],
        ['name' => 'First-time approval rate', 'unit' => 'percent', 'direction' => 'higher', 'definition' => 'First-submission approvals divided by reviewed first submissions times 100.', 'source' => 'Creative review history'],
        ['name' => 'Revision rounds', 'unit' => 'rounds', 'direction' => 'lower', 'definition' => 'Count of revision rounds requested in the period.', 'source' => 'Creative review history'],
    ],
    'operations_projects' => [
        ['name' => 'Milestones delivered', 'unit' => 'milestones', 'direction' => 'higher', 'definition' => 'Distinct planned milestones delivered in the reporting period.', 'source' => 'Project milestone register'],
        ['name' => 'Actual project cost', 'unit' => 'GHS', 'direction' => 'lower', 'definition' => 'Approved costs incurred against the agreed period budget.', 'source' => 'Approved project cost ledger'],
        ['name' => 'Rework incidents', 'unit' => 'incidents', 'direction' => 'lower', 'definition' => 'Count of rework incidents recorded in the reporting period.', 'source' => 'Quality issue log'],
        ['name' => 'Client satisfaction', 'unit' => 'percent', 'direction' => 'higher', 'definition' => 'Positive responses divided by valid responses times 100; report sample size.', 'source' => 'Client survey'],
    ],
    'finance' => [
        ['name' => 'Invoices issued', 'unit' => 'GHS', 'direction' => 'higher', 'definition' => 'Value of invoices issued in the period, net of credit notes.', 'source' => 'Invoice register'],
        ['name' => 'Collections received', 'unit' => 'GHS', 'direction' => 'higher', 'definition' => 'Actual cash receipts in the period, excluding promises to pay.', 'source' => 'Bank-reconciled receipts'],
        ['name' => 'Overdue receivables', 'unit' => 'GHS', 'direction' => 'lower', 'definition' => 'Unpaid receivables past agreed due date at period end.', 'source' => 'Aged debtor ledger'],
        ['name' => 'Operating expenses', 'unit' => 'GHS', 'direction' => 'lower', 'definition' => 'Approved operating expense actual versus authorized budget.', 'source' => 'Approved general ledger'],
    ],
    'hr_admin' => [
        ['name' => 'Vacancies filled', 'unit' => 'positions', 'direction' => 'higher', 'definition' => 'Count of approved vacancies filled with an accepted start date in the period.', 'source' => 'Recruitment register'],
        ['name' => 'Time to fill', 'unit' => 'days', 'direction' => 'lower', 'definition' => 'Mean calendar days from vacancy approval to accepted offer for filled positions.', 'source' => 'Recruitment register'],
        ['name' => 'Performance reviews completed', 'unit' => 'reviews', 'direction' => 'higher', 'definition' => 'Count of scheduled reviews completed and recorded in the period.', 'source' => 'Review register'],
        ['name' => 'Attendance compliance', 'unit' => 'percent', 'direction' => 'higher', 'definition' => 'Attended eligible workdays divided by eligible workdays times 100; exclude approved leave.', 'source' => 'Attendance and approved leave'],
        ['name' => 'Maintenance completed', 'unit' => 'jobs', 'direction' => 'higher', 'definition' => 'Count of scheduled maintenance jobs completed with evidence.', 'source' => 'Fleet maintenance register'],
        ['name' => 'Fuel cost', 'unit' => 'GHS', 'direction' => 'lower', 'definition' => 'Approved fuel expenditure versus approved allocation for the period.', 'source' => 'Fuel log'],
    ],
    'brands_marketing' => [
        ['name' => 'Campaign content delivered', 'unit' => 'assets', 'direction' => 'higher', 'definition' => 'Count of agreed campaign assets delivered in the period.', 'source' => 'Campaign deliverables register'],
        ['name' => 'Qualified leads', 'unit' => 'leads', 'direction' => 'higher', 'definition' => 'Distinct leads meeting the campaign qualification definition.', 'source' => 'Campaign lead register'],
        ['name' => 'Engagement rate', 'unit' => 'percent', 'direction' => 'higher', 'definition' => 'Interactions divided by reach times 100 for the same campaign and period.', 'source' => 'Platform insights; record denominator'],
        ['name' => 'Campaign spend', 'unit' => 'GHS', 'direction' => 'lower', 'definition' => 'Actual approved campaign cost versus authorized campaign budget.', 'source' => 'Approved campaign cost ledger'],
    ],
];
