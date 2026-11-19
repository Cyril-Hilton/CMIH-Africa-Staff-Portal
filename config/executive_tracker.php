<?php

return [
    'tabs' => ['overview' => 'Overview', 'projects' => 'Projects', 'calendar' => 'Calendar', 'resources' => 'Resources & Assets', 'compliance' => 'Compliance', 'accountability' => 'Accountability', 'reports' => 'Department Reports'],
    'process' => ['brief' => 'Brief review', 'proposal' => 'Proposal submission', 'presentation' => 'Client presentation', 'budget' => 'Budget approval'],
    'checks' => ['contract' => 'Signed client contract / PO', 'budget' => 'Client budget approval', 'venue' => 'Written venue confirmation', 'assembly' => 'Local authority permit', 'police' => 'Police notification / permit', 'fire' => 'Fire clearance', 'insurance' => 'Public liability insurance', 'safety' => 'Health & safety risk assessment', 'vendors' => 'Vendor & supplier contracts', 'staff' => 'Staff contracts & briefing', 'consent' => 'Consumer data consent', 'product' => 'Product regulatory clearance, where applicable', 'creative' => 'Brand guidelines & creative sign-off'],
    'reminders' => ['enabled' => true, 'mode' => 'preview', 'first_days' => 15, 'second_days' => 10, 'daily_days' => 5, 'overdue_enabled' => true, 'escalation_enabled' => true, 'subject_prefix' => 'Project commitments', 'signature' => 'CMIH Africa · Traffic & Operations', 'rules' => ['brief', 'proposal', 'presentation', 'budget', 'task', 'assets', 'resources', 'compliance', 'activation', 'project_window', 'recap_due_at', 'setup_at', 'teardown_at', 'operations', 'finance', 'client', 'department']],
];
