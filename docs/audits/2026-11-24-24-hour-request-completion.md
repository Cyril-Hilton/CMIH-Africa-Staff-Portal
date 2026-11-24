# Recent request completion report — 24 November 2026

Scope: the requests handled on 23–24 November, checked against their implementation, regression tests and production release evidence. Earlier app-wide requests are not represented as freshly retested in their entirety.

| Request | Result |
| --- | --- |
| Stop flagging nonexistent, unassigned work across all staff | Personal expectations use assigned/owned work or explicit KPI obligations. Future work and empty departmental categories do not create failures. Empty work remains unscored. |
| Check Cyril's account | Production found zero assigned Creative briefs, zero applicable KPI expectations and zero warning codes. A fresh browser reload displayed “Your accountability · no actions due.” |
| Preserve genuine accountability | Due work, actual attendance outcomes and late submissions remain tracked. Pending manager decisions are attributed to the reviewer; quality grades the HOD has not entered are not treated as staff failures. |
| Manager turnaround | Approve or return by the end of the next working day, excluding weekends, company holidays and approved reviewer leave. Existing Creative review deadlines remain. Original staff submission timing is retained through correction rounds. |
| Automatic tasks and attendance | Completed automatic tasks are recognised. Cyril/Curtis sessions stay open during the working day and close at the configured 18:00 time when the scheduled close runs. No premature full-day credit. |
| Executive exemptions | Super Admin, CVO/Solomon and Ericka are supervision-only: excluded from personal attendance, automatic tasks, performance scoring and personal ranking flags. |
| Stop outbound mail | Global mail delivery is blocked, including queued delivery; notifications remain in-app. Previously delivered mail is not recalled. |
| Actionable warning links | Warning actions retain the source record, owner, date and permissions. Creative follow-up opens assigned briefs or an explanatory empty state when none exist. |
| KPI cards for every department | Collapsible shared presentation retains each department's accent. KPI Achievement & Reporting Gaps follows the reporting workspace in all six departments. |
| Brands appearance | Individual blue Productivity cards and collapsible scoring details; existing values, tables, filters and entry controls retained. |
| Finance entries like Brands | Each native Finance table has its own manual entry form. CRM/Operations links are optional. Revenue/monthly target achievement and status calculate automatically; comments, editing and independent pagination are supported. Receipts and payments remain separate. |
| Finance approvals and privacy | Precious publishes directly; other Finance submitters require her review. Pending money is excluded from published totals. Payroll dashboard stores/displays group totals rather than individual salaries or bank details. |
| Finance payer/payee | Actual receipt payer/payment supplier is shown separately from the project client/brand. |
| Barakah archived entries | Read-only extract delivered: 9 daily tasks and 12 weekly project snapshots for 16–20 November. Nothing was restored or changed. Plain text was provided for WhatsApp. |
| Remove Operations Milestones and Campaigns tables | Removed from the reporting dashboard; underlying records and history preserved. |
| Remove Operations Campaign Tools tab | Removed from the module navigation; retained campaign and warehouse movement records remain accessible through their relevant records/resources. |
| Optional Client Relations handover dates | Setup, execution start and completion may be unconfirmed. No fabricated date is required; known dates still have ordering validation. |
| Return to confirm/update handover dates | Sender/project owner/authorised Client Relations manager can update or clear requested dates. Changes are audited, linked task deadlines update, sign-offs reset as appropriate, and both departments receive in-app updates. Undated work does not acquire an invented deadline warning. |

## Verification and production evidence

Final application suite: **949 tests / 8,913 assertions passed**. JavaScript: **81 passed**. Production asset build and whitespace checks passed. Earlier releases already verified all six departmental views, executive exclusions, in-app-only notification policy and Finance forms for both named Finance profiles. Finance browser checks covered desktop and 393 × 852 phone layouts, light/dark modes, unsaved calculations, Cancel and Escape. No test financial entries were posted to production.

The final regression suite covers permissions, source navigation, deadlines, review ownership, optional/partial timelines, later confirmation and clearing, connected task updates, preserved campaign/warehouse data and dashboard placement. One old test still expected warehouse movement text inside the removed Campaign Tools section; it was updated to check the retained Assets movements table and confirm the campaign record remains stored.

Production checks are read-only apart from the authorised nullable-date schema migration. Protected business data is compared before and after verification. File hashes and rollback backups guard deployment. The final release does not perform further archiving.

Final production result: **deployed**, 24 November 2026. All **30 active accounts** passed the responsibility checks. Cyril: **0 assigned briefs, 0 applicable KPI expectations, 0 warning codes**. All six card placements passed. Removed Operations tables/tab, nullable timeline columns, return-to-update action and in-app-only policy passed. Protected business data was unchanged. Twelve runtime files were installed; rollback backup: `cmih-final-handover-dates-20261124-20261124-001402`. The temporary deployment schedule was removed after verification.

No implementation or deployment item from this recent-request checklist remains pending. A real new submission/manager decision will continue to follow its normal permissions and review process.

## Limits

This report does not claim that every button for every historical account was manually clicked. Account-level production checks cover the 30 active accounts, with targeted live UI and automated workflow coverage. Previously observed extension connection errors and an unattributed browser eval-policy issue are not claimed as application fixes. New entries and approvals were exercised in the test database; production financial records were preserved.

Related details: `2026-11-23-assigned-work-and-dashboard-layout.md`, `2026-11-23-review-accountability-auto-clock-and-kpi-ui.md`, `2026-11-23-finance-table-entry-workflows.md`, and `2026-11-23-ranking-warning-actions-and-brands-kpis.md`.
