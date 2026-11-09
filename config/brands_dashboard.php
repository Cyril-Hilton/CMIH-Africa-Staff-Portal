<?php
$number=fn($label)=>['label'=>$label,'type'=>'number'];
$text=fn($label)=>['label'=>$label,'type'=>'text'];
$date=fn($label)=>['label'=>$label,'type'=>'date'];
$rich=fn($label)=>['label'=>$label,'type'=>'rich'];
$select=fn($label,$options)=>['label'=>$label,'type'=>'select','options'=>$options];
$partner=$select('Reporting Partner',['castel'=>'Castel','unilever'=>'Unilever']);
$priority=$select('Priority',['Low'=>'Low','Medium'=>'Medium','High'=>'High','Urgent'=>'Urgent']);
$status=$select('Work Status',['Planned'=>'Planned','In Progress'=>'In Progress','Done'=>'Reported Complete','Blocked'=>'Blocked','Deferred'=>'Deferred']);
$perfect=['partner'=>$partner,'breakdown'=>$select('Breakdown',['overall'=>'Overall','outlet'=>'Outlet','region'=>'Region','distributor'=>'Distributor']),'location'=>$text('Outlet / Region / Distributor')];
foreach(['coverage'=>'Coverage','osa'=>'On-shelf Availability (OSA)','innovation'=>'Innovation','must_have'=>'Must Have (MH)','npd'=>'New Product Development (NPD)','perfect_store'=>'Perfect Store'] as $key=>$label)foreach(['target'=>'Target','previous'=>'Previous Week','actual'=>'Current Week'] as $part=>$description)$perfect[$key.'_'.$part.'_percent']=$number($label.' · '.$description.' (%)');
return [
 'tabs'=>['productivity'=>'Productivity','people'=>'People','perfect_store'=>'Perfect Store','activation'=>'Activations & Volume Depletion Initiatives','visibility'=>'Visibility Deployment','workstream'=>'Weekly Work Stream'],
 'people_scopes'=>['internal'=>['internal'=>'Internal'],'unilever'=>['unilever'=>'Unilever'],'off_trade'=>['ggbl'=>'GGBL'],'activations'=>['vaseline'=>'Vaseline','heineken'=>'Heineken']],
 'schemas'=>[
  'people'=>['cohort'=>$select('People Group',['internal'=>'Internal Staff','unilever'=>'Unilever Merchandising','off_trade'=>'Off-Trade Merchandising','activations'=>'Activations & Campaigns']),'scope'=>$select('Scope',['internal'=>'Internal','unilever'=>'Unilever','ggbl'=>'GGBL','vaseline'=>'Vaseline','heineken'=>'Heineken']),'planned_count'=>$number('Planned Headcount'),'active_count'=>$number('Active Headcount'),'attrition_count'=>$number('Attrition'),'leave_count'=>$number('People on Leave'),'absent_count'=>$number('People Absent'),'notes'=>$rich('People Updates & Actions')],
  'perfect_store'=>$perfect+['notes'=>$rich('Insights & Next Actions')],
  'activation'=>['partner'=>$partner,'client'=>$text('Client Contact / Requester'),'brief_date'=>$date('Brief Date'),'revert_date'=>$date('Response / Revert Date'),'closed_on'=>$date('Closed Date'),'brief_status'=>$select('Brief Status',['received'=>'Received','responded'=>'Responded','pending'=>'Pending','closed'=>'Closed']),'outcome'=>$select('Outcome',['ongoing'=>'Ongoing','won'=>'Won','lost'=>'Lost']),'mechanic'=>$rich('Brief / Mechanic'),'target_sales'=>$number('Target Sales'),'total_sales'=>$number('Total Sales'),'sales_unit'=>$text('Sales Unit (e.g. cases, units or GH₵)'),'target_reach'=>$number('Target Reach'),'total_reach'=>$number('Total Reach'),'conversions'=>$number('Conversions'),'conversion_base'=>$number('People / Interactions Eligible for Conversion'),'remarks'=>$rich('Remarks & Next Actions')],
  'visibility'=>['posm_type'=>$text('POSM Type'),'partner'=>$partner,'planned_scope'=>$number('Planned Scope'),'active_scope'=>$number('Active Scope'),'scheduled'=>$number('Total Scheduled'),'deployed'=>$number('Total Deployed'),'status'=>$status,'notes'=>$rich('Deployment Update')],
  'workstream'=>['project'=>$text('Project / Workstream'),'brand'=>$text('Brand'),'task_in_week'=>$rich('Task in Week / Progress'),'collaborators'=>['label'=>'Collaborators','type'=>'staff'],'priority'=>$priority,'status'=>$status,'project_start'=>$date('Project Start'),'project_end'=>$date('Expected Project End'),'kpis'=>$rich('Targets & KPI Commitments'),'results'=>$rich('Reported Results'),'gaps'=>$rich('Gaps & Next Actions'),'notes'=>$rich('Other Notes')],
 ],
];
