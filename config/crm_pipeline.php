<?php

return [
    // Workbook stage weights, normalized explicitly; delivery progress is a different measure.
    'stages' => ['idea' => ['label' => 'Idea', 'probability' => 10], 'contacted' => ['label' => 'Contacted', 'probability' => 25], 'proposal_sent' => ['label' => 'Proposal sent', 'probability' => 50], 'terms_negotiated' => ['label' => 'Terms negotiated', 'probability' => 80], 'verbal_yes' => ['label' => 'Verbal yes', 'probability' => 90], 'project_ongoing' => ['label' => 'Project ongoing', 'probability' => 100], 'project_completed' => ['label' => 'Project completed', 'probability' => 100], 'on_hold' => ['label' => 'On hold', 'probability' => 0], 'lost' => ['label' => 'Lost', 'probability' => 0], 'cancelled' => ['label' => 'Cancelled', 'probability' => 0]],
    'public_actions' => ['follow_up' => 'Follow up', 'submit_proposal' => 'Submit proposal', 'client_decision' => 'Await client decision', 'resolve_dependency' => 'Resolve dependency', 'deliver' => 'Complete delivery', 'invoice' => 'Raise invoice', 'collections' => 'Follow up collections'],
];
