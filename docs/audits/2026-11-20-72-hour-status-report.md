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
| HR/Admin and Peggy’s requirements | Native staff/attendance sources plus live function registers, HR review/privacy rules and Ericka’s retained read-only audit access. Existing vendor contacts now appear in HR Supplies without duplicate vendor entry. The resupplied original was subsequently checked in full; all identified gaps were corrected. |
| Pass-outs and meeting invitations | Afternoon half-day pass-out approval workflow, monthly limit and history; boardroom attendees, agenda, date/time and invitation/update/cancellation notifications. |
| Scores and accuracy | 10% punctuality / 65% verified delivery / 25% business. Monday–Friday expected calendar with applicable exclusions; equal reporting-area shares when weights are unset; missing required setup earns zero credit with a flag. Creative on-time scoring uses HOD commitments. Actual outcomes remain distinct from reported status. |
| Archive and clean start | On explicit greenlight, archived 6,016 reporting records and 513 obsolete report-notification queue entries. Original source rows remain retained. All Accounts preserves the original 81 client/brand names; old projects, pipeline stages and engagements are cleared from active reporting. |
| Remove old collapsed cards/tables | Removed the earlier saved weekly report tables/cards, old CR weekly updates tab, Creative historical snapshot lane and shared collapsed Reporting History & Review Status card. No legacy consolidated weekly dashboard is embedded beneath the new views. |
| Preserve operational data | Row counts and content hashes matched across 137 protected database tables. Assets, custody, vendors, leave, fleet, payroll, loans and other native operational records were retained. Attendance was subsequently archived under the user’s extended authorization. Published audit report payloads remain immutable. |

## Remaining responsibility and scope

The requested implementation work recorded in these audits is complete. Staff must now enter their real new reports, commitments, targets, outcomes and supporting data for the new cycle. The system does not invent missing business facts. Unresolved ranking issues stay visible to the affected staff and executives until their source records are corrected; daily notification deduplication prevents repeated alerts on the same day while allowing reminders on later days.

Peggy’s resupplied original is retained in the repository and has been compared in full. Executive deadline and ranking reminders are Live, using the portal’s dedicated hourly schedule. Attendance and KPI history are archived, with new eligibility starting 23 November 2026 and no cleared-period absence penalty. Premature ranking positions are suppressed before that date.

This report covers requests visible in this chat and the retained audits, without claiming access to conversations outside them.

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

## Final ranking and recovery verification

Full application validation reached 904 tests / 8,242 assertions; final ranking checks passed 16 tests / 97 assertions. Browser verification confirmed the executive attention indicator for 29 staff, no premature staff rankings, the Creative Overview loading actual zero active records after the authorized archive, and all six retained vendor contacts in HR Supplies. Creative read-only loading now retries temporary service/network failures without retrying writes or disrupting open forms; all 71 JavaScript tests passed.
