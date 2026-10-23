<?php
// Approved fields explicitly suitable for the staff-wide weekly presentation.
// Private costs, supplier contacts, evidence URLs and source notes are not projected.
return [
 'operations_projects'=>[
  'project'=>['brand','supporting_team','location','started_on','completed_on','due_on','project_stage','project_status','progress_percent','key_issue','action_required','action_due_on','is_activation','outlet','primary_requirement','team_handyman_requirement','travel_required','asset_readiness'],
  'activation'=>['brand','location','outlet','starts_on','ends_on','report_shared_on'],
  'posm_deployment'=>['brand','category','asset_name','asset_code','quantity','returned_quantity','location','outlet','deployed_on','expected_return_on','returned_on','condition_before','condition_after','deployment_status','return_status','current_location'],
  'procurement'=>['item_service','quantity','supplier','requested_on','required_on','po_status','procurement_status','delivery_status'],
  'supplier'=>['supplier_name','delivery_on','performance','payment_status','resolution_action'],
  'production'=>['production_item','responsible_person','due_on','delivered_on','production_status','quality_check','action_required'],
  'client_approval'=>['item_for_approval','submitted_on','approval_status','decided_on','follow_up_action','next_follow_up_on'],
  'project_report'=>['activity_on','due_on','report_status','shared_on','outstanding_actions'],
  'risk_issue'=>['category','description','impact','priority','action_required','due_on','issue_status','resolved_on','resolution'],
  'milestone'=>['planned_on','delivered_on','impact'],
  'risk'=>['probability','mitigation'],
 ],
 'brands_marketing'=>['campaign'=>array_values(array_diff(array_keys((require __DIR__.'/department_reporting.php')['brands_marketing']['campaign']), ['budget','spend']))],
];
