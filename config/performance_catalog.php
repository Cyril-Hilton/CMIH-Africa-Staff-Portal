<?php

return json_decode(<<<'JSON'
{
  "client_relations": [
    {
      "name": "Revenue target",
      "kind": "client",
      "operation": "sum",
      "field": "revenue_target",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Revenue achieved",
      "kind": "client",
      "operation": "sum",
      "field": "recognized_revenue",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "New business",
      "kind": "client",
      "operation": "sum",
      "field": "new_business",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Cross-selling opportunities",
      "kind": "client",
      "operation": "sum",
      "field": "cross_sell_value",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Client meetings",
      "kind": "client",
      "operation": "sum",
      "field": "meetings",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Client engagement",
      "kind": "client",
      "operation": "sum",
      "field": "engagements",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Revenue growth",
      "kind": "client",
      "operation": "growth",
      "field": "recognized_revenue",
      "other": "prior_revenue",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Client retention",
      "kind": "client",
      "operation": "flag_rate",
      "field": "retained",
      "other": null,
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Client satisfaction",
      "kind": "client",
      "operation": "average",
      "field": "satisfaction_score",
      "other": null,
      "unit": "score",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Proposals expected",
      "kind": "proposal",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": "planned"
    },
    {
      "name": "Proposals submitted",
      "kind": "proposal",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": "submitted"
    },
    {
      "name": "Proposals won",
      "kind": "proposal",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": "won"
    },
    {
      "name": "Proposal conversion",
      "kind": "proposal",
      "operation": "status_rate",
      "field": null,
      "other": "submitted,won,lost",
      "unit": "percent",
      "direction": "higher",
      "status": "won"
    },
    {
      "name": "Proposal value",
      "kind": "proposal",
      "operation": "sum",
      "field": "value",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Pipeline value",
      "kind": "proposal",
      "operation": "sum",
      "field": "value",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": "submitted"
    },
    {
      "name": "Client complaints",
      "kind": "client_issue",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "lower",
      "status": "submitted"
    },
    {
      "name": "Client issues",
      "kind": "client_issue",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "lower",
      "status": "in_progress"
    },
    {
      "name": "Issue resolution time",
      "kind": "client_issue",
      "operation": "elapsed",
      "field": "opened_on",
      "other": "resolved_on",
      "unit": "days",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Issue SLA compliance",
      "kind": "client_issue",
      "operation": "on_time",
      "field": "resolved_on",
      "other": "sla_due",
      "unit": "percent",
      "direction": "higher",
      "status": null
    }
  ],
  "creatives": [
    {
      "name": "Briefs received",
      "kind": "brief",
      "operation": "count_field",
      "field": "received_on",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Briefs accepted",
      "kind": "brief",
      "operation": "count_field",
      "field": "accepted_on",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Concepts submitted",
      "kind": "brief",
      "operation": "count_field",
      "field": "concept_submitted_on",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Concepts approved",
      "kind": "brief",
      "operation": "count_field",
      "field": "concept_approved_on",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Final artwork",
      "kind": "brief",
      "operation": "count_field",
      "field": "final_delivered_on",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Briefs in development",
      "kind": "brief",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": "in_progress"
    },
    {
      "name": "Completed jobs",
      "kind": "brief",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": "completed"
    },
    {
      "name": "Revisions",
      "kind": "brief",
      "operation": "sum",
      "field": "revision_rounds",
      "other": null,
      "unit": "count",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Jobs per designer",
      "kind": "brief",
      "operation": "count_field",
      "field": "final_delivered_on",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Workload hours",
      "kind": "brief",
      "operation": "sum",
      "field": "planned_hours",
      "other": null,
      "unit": "hours",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Pending workload hours",
      "kind": "brief",
      "operation": "sum",
      "field": "planned_hours",
      "other": null,
      "unit": "hours",
      "direction": "lower",
      "status": "in_progress"
    },
    {
      "name": "Average turnaround time",
      "kind": "brief",
      "operation": "elapsed",
      "field": "accepted_on",
      "other": "final_delivered_on",
      "unit": "days",
      "direction": "lower",
      "status": null
    },
    {
      "name": "First-time approval",
      "kind": "brief",
      "operation": "flag_rate",
      "field": "first_time_approved",
      "other": null,
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Approval rate",
      "kind": "brief",
      "operation": "presence_rate",
      "field": "concept_approved_on",
      "other": "concept_submitted_on",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Revision rate",
      "kind": "brief",
      "operation": "positive_rate",
      "field": "revision_rounds",
      "other": null,
      "unit": "percent",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Quality score",
      "kind": "brief",
      "operation": "average",
      "field": "quality_score",
      "other": null,
      "unit": "score",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Capacity utilization",
      "kind": "brief",
      "operation": "ratio",
      "field": "actual_hours",
      "other": "capacity_hours",
      "unit": "percent",
      "direction": "higher",
      "status": null
    }
  ],
  "operations_projects": [
    {
      "name": "Projects awarded",
      "kind": "project",
      "operation": "count_field",
      "field": "awarded_on",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Projects initiated",
      "kind": "project",
      "operation": "count_field",
      "field": "started_on",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Projects completed",
      "kind": "project",
      "operation": "count_field",
      "field": "completed_on",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Project budget",
      "kind": "project",
      "operation": "sum",
      "field": "budget",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Actual cost",
      "kind": "project",
      "operation": "sum",
      "field": "actual_cost",
      "other": null,
      "unit": "GHS",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Revenue",
      "kind": "project",
      "operation": "sum",
      "field": "revenue",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Cost to completion",
      "kind": "project",
      "operation": "sum",
      "field": "cost_to_completion",
      "other": null,
      "unit": "GHS",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Budget variance",
      "kind": "project",
      "operation": "difference",
      "field": "actual_cost",
      "other": "budget",
      "unit": "GHS",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Gross profit",
      "kind": "project",
      "operation": "difference",
      "field": "revenue",
      "other": "actual_cost",
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Gross margin",
      "kind": "project",
      "operation": "margin",
      "field": "revenue",
      "other": "actual_cost",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Rework",
      "kind": "project",
      "operation": "sum",
      "field": "rework_count",
      "other": null,
      "unit": "count",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Complaints",
      "kind": "project",
      "operation": "sum",
      "field": "complaints",
      "other": null,
      "unit": "count",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Quality issues",
      "kind": "project",
      "operation": "sum",
      "field": "quality_issues",
      "other": null,
      "unit": "count",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Client satisfaction",
      "kind": "project",
      "operation": "average",
      "field": "satisfaction_score",
      "other": null,
      "unit": "score",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Milestones",
      "kind": "milestone",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Milestone on-time delivery",
      "kind": "milestone",
      "operation": "on_time",
      "field": "delivered_on",
      "other": "planned_on",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Open risks",
      "kind": "risk",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "lower",
      "status": "in_progress"
    },
    {
      "name": "Risk exposure",
      "kind": "risk",
      "operation": "sum",
      "field": "exposure",
      "other": null,
      "unit": "GHS",
      "direction": "lower",
      "status": null
    }
  ],
  "finance": [
    {
      "name": "Invoices raised",
      "kind": "invoice",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": "planned"
    },
    {
      "name": "Invoices pending",
      "kind": "invoice",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": "in_progress"
    },
    {
      "name": "Invoices submitted",
      "kind": "invoice",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": "submitted"
    },
    {
      "name": "Invoices approved",
      "kind": "invoice",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": "approved"
    },
    {
      "name": "Invoices paid",
      "kind": "invoice",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": "paid"
    },
    {
      "name": "Receivables outstanding",
      "kind": "invoice",
      "operation": "outstanding",
      "field": "value",
      "other": "paid_amount",
      "unit": "GHS",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Receivables overdue",
      "kind": "invoice",
      "operation": "overdue_balance",
      "field": "value",
      "other": "paid_amount",
      "unit": "GHS",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Payables outstanding",
      "kind": "payable",
      "operation": "outstanding",
      "field": "value",
      "other": "paid_amount",
      "unit": "GHS",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Payables overdue",
      "kind": "payable",
      "operation": "overdue_balance",
      "field": "value",
      "other": "paid_amount",
      "unit": "GHS",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Invoiced revenue",
      "kind": "invoice",
      "operation": "sum",
      "field": "value",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Collections achieved",
      "kind": "invoice",
      "operation": "sum",
      "field": "paid_amount",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Collection efficiency",
      "kind": "invoice",
      "operation": "ratio",
      "field": "paid_amount",
      "other": "value",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Cash inflow",
      "kind": "cash",
      "operation": "sum",
      "field": "inflow",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Cash outflow",
      "kind": "cash",
      "operation": "sum",
      "field": "outflow",
      "other": null,
      "unit": "GHS",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Forecast cash position",
      "kind": "cash",
      "operation": "snapshot",
      "field": "forecast",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Net cash flow",
      "kind": "cash",
      "operation": "difference",
      "field": "inflow",
      "other": "outflow",
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Expense variance",
      "kind": "expense",
      "operation": "difference",
      "field": "actual",
      "other": "budget",
      "unit": "GHS",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Gross profit",
      "kind": "profitability",
      "operation": "difference",
      "field": "revenue",
      "other": "direct_cost",
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Gross margin",
      "kind": "profitability",
      "operation": "margin",
      "field": "revenue",
      "other": "direct_cost",
      "unit": "%",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Contribution",
      "definition": "Recognised revenue minus variable costs; complete approved sources only.",
      "kind": "profitability",
      "operation": "difference",
      "field": "revenue",
      "other": "variable_cost",
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "GP variance",
      "definition": "Recognised revenue minus direct cost minus agreed gross profit target; complete approved sources only.",
      "kind": "profitability",
      "operation": "profit_variance",
      "field": "revenue",
      "other": "direct_cost",
      "unit": "GHS",
      "direction": "higher",
      "status": null,
      "target": "gross_profit_target"
    }
  ],
  "hr_admin": [
    {
      "name": "Approved headcount",
      "kind": "workforce",
      "operation": "snapshot",
      "field": "approved_headcount",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Actual headcount",
      "kind": "workforce",
      "operation": "snapshot",
      "field": "actual_headcount",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "New hires",
      "kind": "workforce",
      "operation": "sum",
      "field": "new_hires",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Departures",
      "kind": "workforce",
      "operation": "sum",
      "field": "departures",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Lateness",
      "kind": "workforce",
      "operation": "sum",
      "field": "late_days",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Leave days",
      "kind": "workforce",
      "operation": "sum",
      "field": "leave_days",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Candidates",
      "kind": "vacancy",
      "operation": "sum",
      "field": "candidates",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Interviews",
      "kind": "vacancy",
      "operation": "sum",
      "field": "interviews",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Offers",
      "kind": "vacancy",
      "operation": "sum",
      "field": "offers",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Reviews completed",
      "kind": "workforce",
      "operation": "sum",
      "field": "reviews_completed",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Vacancy gap",
      "kind": "workforce",
      "operation": "difference",
      "field": "approved_headcount",
      "other": "actual_headcount",
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Attrition",
      "kind": "workforce",
      "operation": "ratio",
      "field": "departures",
      "other": "average_headcount",
      "unit": "percent",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Attendance",
      "kind": "workforce",
      "operation": "ratio",
      "field": "attended_days",
      "other": "eligible_days",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Absenteeism",
      "kind": "workforce",
      "operation": "absence",
      "field": "attended_days",
      "other": "eligible_days",
      "unit": "percent",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Performance review compliance",
      "kind": "workforce",
      "operation": "ratio",
      "field": "reviews_completed",
      "other": "reviews_due",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Open positions",
      "kind": "vacancy",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": "in_progress"
    },
    {
      "name": "Time to fill",
      "kind": "vacancy",
      "operation": "elapsed",
      "field": "approved_on",
      "other": "offer_accepted_on",
      "unit": "days",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Outstanding employee cases",
      "kind": "employee_case",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": "in_progress"
    },
    {
      "name": "Resolved employee cases",
      "kind": "employee_case",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": "resolved"
    },
    {
      "name": "Performance improvement plans",
      "kind": "improvement_plan",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Recruitment SLA compliance",
      "kind": "vacancy",
      "operation": "on_time",
      "field": "offer_accepted_on",
      "other": "target_fill_on",
      "unit": "percent",
      "direction": "higher",
      "status": null
    }
  ],
  "admin_drivers": [
    {
      "name": "Vehicle availability",
      "kind": "vehicle",
      "operation": "ratio",
      "field": "available_hours",
      "other": "scheduled_hours",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Vehicle utilization",
      "kind": "vehicle",
      "operation": "ratio",
      "field": "used_hours",
      "other": "scheduled_hours",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Vehicle downtime",
      "kind": "vehicle",
      "operation": "ratio",
      "field": "downtime_hours",
      "other": "scheduled_hours",
      "unit": "percent",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Fuel consumption",
      "kind": "fuel",
      "operation": "sum",
      "field": "litres",
      "other": null,
      "unit": "litres",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Fuel cost",
      "kind": "fuel",
      "operation": "sum",
      "field": "cost",
      "other": null,
      "unit": "GHS",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Fuel efficiency (km/L)",
      "kind": "fuel",
      "operation": "ratio_raw",
      "field": "distance_km",
      "other": "litres",
      "unit": "km/L",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Fuel variance",
      "kind": "fuel",
      "operation": "difference",
      "field": "cost",
      "other": "budget",
      "unit": "GHS",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Scheduled maintenance",
      "kind": "maintenance",
      "operation": "count_field",
      "field": "scheduled_on",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Completed maintenance",
      "kind": "maintenance",
      "operation": "count_field",
      "field": "completed_on",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Maintenance SLA compliance",
      "kind": "maintenance",
      "operation": "on_time",
      "field": "completed_on",
      "other": "scheduled_on",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Repair costs",
      "kind": "maintenance",
      "operation": "sum",
      "field": "repair_cost",
      "other": null,
      "unit": "GHS",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Trips",
      "kind": "trip",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Logistics on-time completion",
      "kind": "trip",
      "operation": "on_time",
      "field": "delivered_on",
      "other": "due_on",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Asset register",
      "kind": "asset",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Missing assets",
      "kind": "asset",
      "operation": "sum",
      "field": "missing_count",
      "other": null,
      "unit": "count",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Damaged assets",
      "kind": "asset",
      "operation": "sum",
      "field": "damaged_count",
      "other": null,
      "unit": "count",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Facilities requests",
      "kind": "facility_request",
      "operation": "count",
      "field": null,
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Facilities resolution time",
      "kind": "facility_request",
      "operation": "elapsed",
      "field": "opened_on",
      "other": "resolved_on",
      "unit": "days",
      "direction": "lower",
      "status": null
    }
  ],
  "brands_marketing": [
    {
      "name": "Content planned",
      "kind": "campaign",
      "operation": "sum",
      "field": "assets_planned",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Content delivered",
      "kind": "campaign",
      "operation": "sum",
      "field": "assets_delivered",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Reach",
      "kind": "campaign",
      "operation": "sum",
      "field": "reach",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Impressions",
      "kind": "campaign",
      "operation": "sum",
      "field": "impressions",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Qualified leads",
      "kind": "campaign",
      "operation": "sum",
      "field": "qualified_leads",
      "other": null,
      "unit": "count",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Campaign budget",
      "kind": "campaign",
      "operation": "sum",
      "field": "budget",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Campaign spend",
      "kind": "campaign",
      "operation": "sum",
      "field": "spend",
      "other": null,
      "unit": "GHS",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Engagement rate",
      "kind": "campaign",
      "operation": "ratio",
      "field": "interactions",
      "other": "reach",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Content compliance",
      "kind": "campaign",
      "operation": "ratio",
      "field": "assets_delivered",
      "other": "assets_planned",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Spend variance",
      "kind": "campaign",
      "operation": "difference",
      "field": "spend",
      "other": "budget",
      "unit": "GHS",
      "direction": "lower",
      "status": null
    },
    {
      "name": "Reported outlets planned",
      "kind": "campaign",
      "operation": "sum",
      "field": "outlets_planned",
      "other": null,
      "unit": "outlets",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Reported outlets visited",
      "kind": "campaign",
      "operation": "sum",
      "field": "outlets_visited",
      "other": null,
      "unit": "visits",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Reported outlet visit completion",
      "kind": "campaign",
      "operation": "ratio",
      "field": "outlets_visited",
      "other": "outlets_planned",
      "unit": "percent",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Retailers supported",
      "kind": "campaign",
      "operation": "sum",
      "field": "retailers_supported",
      "other": null,
      "unit": "retailers",
      "direction": "higher",
      "status": null
    },
    {
      "name": "Retail reporting on time",
      "kind": "campaign",
      "operation": "on_time",
      "field": "report_shared_on",
      "other": "report_due_on",
      "unit": "percent",
      "direction": "higher",
      "status": null
    }
  ]
}
JSON, true, 512, JSON_THROW_ON_ERROR);
