# 72-hour requests report — 20 November 2026

Scope: requests visible in this project chat for 17–20 November, with relevant carried-over requirements and later corrections. This replaces the 19 November status snapshot. The earlier report existed in repository audit notes but was not delivered to the user as requested; that delivery was missed.

## Completed and deployed

| Request | Result |
| --- | --- |
| Executive dashboard overhaul using Ericka’s tracker | Seven views: Overview, Projects, Calendar, Resources & Assets, Compliance, Accountability, Department Reports. Coverage includes canonical project identity, staffing, process, resources, thirteen compliance checks, history, source reports and reminders interface. Workbook examples were not imported as live data. |
| Live project movement tracker | Department-to-department journey with original departmental substages, dates, owners, missing evidence and optional/skipped departments. Direct Creative and independent Operations work retain their own source workflows. |
| End-to-end departmental connections | CR ↔ Creative and CR ↔ Operations reuse shared projects. Client receipt date, brief creation, requester deadline, HOD commitment, assignments, comments, handover/sign-offs and outcomes read back into authorized views. Required inputs exist in the original entry forms. |
| Executive charts | Each of the seven tabs has a source-driven top chart. Duplicate versions of the same chart were removed; exact chart-data tables and common filters remain. |
| Executive calendar and navigation | Horizontal cards, compact day headers, expandable accountability, original-record links and filtered return navigation. Standalone Mega Table daily tasks are excluded from the executive project tracker. |
| Excel/PDF exports | Select sections, people, departments, projects, date scope and individual records after preview. All source pages are included; existing privacy restrictions apply. Live workbook/PDF generation passed. |
| Creative dashboard simplification | Four tabs: Overview, Work Flow, Calendar, Showcase. Existing HOD-led assignment/review/completion rules retained; Board/Table switch, metallic styling, larger filtered graph, optional multi-image sliders and gallery remain. Screenshots do not affect KPI scores. |
| Creative accountability and team display | Lead-only calendar placement, support people inside the lead’s card, Creative-only member rows, clear brief title, compact cards, shared CR project name, both deadlines and provenance. Detailed calendar dates collapse on demand. |
| Operations handovers and assets | Optional handovers feed the master tracker, not automatic weekly reports. Staff write their own reports. All Operations staff can submit asset proposals; HOD approval publishes them. Project quantities and actual issue/return/custodian records are connected. |
| Finance overhaul | Overview plus Revenue, Invoices, Cash Flow, Expenses, Commercial Risks, Payroll, Taxes & SSNIT; period/team filters, direct Finance revenue entry, source connections and corrected GHS formatting. Precious can publish her work; Benedicta’s entries require review. Individual salary/bank/payslip data is excluded from the dashboard payroll tab. |
| HR/Admin and Peggy’s requirements | Native staff/attendance sources plus live function registers, HR review/privacy rules and Ericka’s retained read-only audit access. Existing vendor contacts now appear in HR Supplies without duplicate vendor entry. See original verification limitation below. |
| Pass-outs and meeting invitations | Afternoon half-day pass-out approval workflow, monthly limit and history; boardroom attendees, agenda, date/time and invitation/update/cancellation notifications. |
| Scores and accuracy | 10% punctuality / 65% verified delivery / 25% business. Monday–Friday expected calendar with applicable exclusions; equal reporting-area shares when weights are unset; missing required setup earns zero credit with a flag. Creative on-time scoring uses HOD commitments. Actual outcomes remain distinct from reported status. |
| Archive and clean start | On explicit greenlight, archived 6,016 reporting records and 513 obsolete report-notification queue entries. Original source rows remain retained. All Accounts preserves the original 81 client/brand names; old projects, pipeline stages and engagements are cleared from active reporting. |
| Remove old collapsed cards/tables | Removed the earlier saved weekly report tables/cards, old CR weekly updates tab, Creative historical snapshot lane and shared collapsed Reporting History & Review Status card. No legacy consolidated weekly dashboard is embedded beneath the new views. |
| Preserve operational data | Row counts and content hashes matched across 137 protected database tables. Assets, custody, vendors, attendance, leave, fleet, payroll, loans and other native operational records were retained. Published audit report payloads remain immutable. |

## Still pending or limited

1. **Executive deadline/escalation reminder automation remains in Preview.** The rules, schedule, preview/log and delivery integration are implemented. Preview does not deliver those new automatic reminder digests. Existing workflow notifications remain separate and active. Turning that additional reminder automation Live is a remaining rollout decision.
2. **The clean dashboards need real staff entries.** This follows the authorized reset. The system cannot invent client receipt dates, targets, commitments, project links, outcomes or evidence. Empty reporting figures after the reset are expected; retained attendance, assets and other operational figures still use their original data.
3. **A fresh comparison with Peggy’s original document is unavailable.** Its supplied WhatsApp temporary file no longer exists at the original path. HR feature coverage is supported by the retained requirement-by-requirement audit, source code, regression tests and live checks; it is not a new line-by-line reading of that missing original file.

No additional unimplemented feature was identified in the retained audit coverage. This is an evidence-based status report for the requests visible in this chat, not a claim to have audited conversations outside it.

## Validation and evidence

- Full regression suite: 891 tests, 8,167 assertions passed.
- Final account-directory/reset/export checks: 17 tests, 131 assertions passed.
- All six departmental dashboard views checked after the reset; all seven executive chart tabs checked for existing executive profiles.
- Production archive Excel/PDF downloads, executive export generation, source permissions and vendor read-back passed. No synthetic business entries were created in production for verification.
- Retained operational counts include 33 assets, 1,292 attendance rows, 24 leave records, 127 fleet requests, 52 payslips, two loan records and six shared vendor rows. These are verification snapshots, not permanent totals.
- Live visual verification on 20 November supersedes the earlier 19 November browser limitation for the executive layouts and project journey. Reset and archive browser checks confirm the new empty reporting state and retained account directory.

Supporting audits: `2026-11-19-executive-tracker-coverage.md`, `2026-11-19-request-closure-report.md`, `2026-11-20-department-input-to-tracker-audit.md`, `2026-11-20-executive-project-flow-and-navigation.md`, `2026-11-20-executive-tab-charts.md`, `2026-11-20-reporting-reset-and-exports.md`.

## Subsequent completion and revised archive scope

Peggy supplied the original document again; it is now retained under docs/requirements and was compared fully against the implementation. The vendor, ID-document, damaged-facility, completed-activity/filter and approval-flow gaps found in that audit were fixed. Executive reminders are Live with a verified dedicated hourly portal schedule and same-run notification processing. The user then explicitly extended the archive to attendance and KPI performance history: all 1,292 clock-ins were retained in a separate archive and excluded from scoring. New-cycle eligibility starts on 23 November 2026. This supersedes the earlier statement that attendance remained active. Staff still provide real new business entries; no results were invented. See 2026-11-20-peggy-requirements-completion.md.
