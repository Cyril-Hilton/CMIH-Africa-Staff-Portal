<?php

return [
    'issue' => ['title' => 'Client issue', 'statuses' => ['open', 'in_progress', 'resolved', 'cancelled'], 'fields' => ['title' => 'text', 'opened_on' => 'date', 'sla_due' => 'date', 'resolved_on' => 'date', 'impact' => 'textarea', 'corrective_action' => 'textarea']],
    'meeting' => ['title' => 'Client meeting', 'statuses' => ['upcoming', 'completed', 'cancelled'], 'fields' => ['title' => 'text', 'scheduled_at' => 'datetime-local', 'completed_at' => 'datetime-local', 'outcome' => 'textarea']],
    'satisfaction' => ['title' => 'Client satisfaction response', 'statuses' => ['recorded', 'withdrawn'], 'fields' => ['respondent' => 'text', 'responded_on' => 'date', 'score' => 'number', 'maximum_score' => 'number', 'feedback' => 'textarea']],
    'invoice' => ['title' => 'Client invoice', 'statuses' => ['draft', 'issued', 'part_paid', 'paid', 'cancelled'], 'fields' => ['invoice_date' => 'date', 'due_on' => 'date', 'amount' => 'number', 'paid_amount' => 'number', 'source_reference' => 'text']],
];
