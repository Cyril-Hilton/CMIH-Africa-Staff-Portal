# Requests review — past 72 hours

Review period: 16–19 November 2026, including relevant carried-over requests in this project chat. Reviewed the actual chat requests, current implementation, retained production audits, source workbooks, and automated checks. Later instructions take priority: four Creative tabs, optional screenshots, HOD delivery dates for Creative scoring, Operations handovers feeding the master tracker rather than automatically creating weekly reports, and Precious publishing her own Finance entries.

**Release status: deployed and production-verified on 19 November 2026.** The implementation and this report are included in the GitHub branch publication.

## Feature coverage

| Requested work | Implemented result | Verification |
| --- | --- | --- |
| Accurate department / individual KPIs, rankings and performance | Reporting coverage separated from achievement; 10% punctuality, 65% verified delivery, 25% business; equal required-area shares where weights are unset; missing required inputs earn zero credit with setup flags. Rankings remain numeric and provisional. | Full regression suite; retained six-department source reconciliation and ranking checks. |
| Attendance and clock-in reliability | Actual clock-ins, lateness and expected eligible Monday–Friday staff-days; holiday, approved leave, future/pre-employment exclusions; reopened/expired clock-in recovery. | Attendance / scoring regression tests and production source audits. |
| HR / Admin overhaul and Peggy’s requirements | Eleven function tabs, native staff/attendance/assets data, document tracking, recruitment, leave, training, confidential cases, welfare, policies/exits, facilities, fleet, supplies, activities, exports/imports and audit. Ericka retains read-only HR audit access. | Retained Peggy requirement-by-requirement verification, HR integration tests and live source audit. Her original WhatsApp temporary file is no longer available at its supplied path; this review uses that retained verification and current implementation. |
| Breadcrumbs across the app | Shared breadcrumbs and working parent links, including executive views and HR subviews. | Navigation tests; dashboard route checks. |
| Pass-out requests | Afternoon half-day, one pending/approved request per calendar month; supervisor → HOD → HR; history, notifications, privacy and no annual leave deduction. | HRM workflow regression tests and prior live release checks. |
| Boardroom meeting attendees and notifications | Named invitees, agenda, date/start/end, visibility, tentative/confirmed invitations, changes/removals and cancellation notices; room access tracking retained. | Booking/invitation tests and prior live release checks. |
| Client Relations consistency and usability | One weekly-entry form, consistent wording, support staff, current-period engagement separated from all accounts, former-owner labels, relationship details and published-source review. | Client Relations tests; prior desktop/mobile and live verification. |
| Brands copy / weekly workstreams | Copy flow and duplicate prevention, support staff, reporting period filters and current source-driven workstreams. | Brands regression tests and retained production release audit. |
| Creative simplification and visual updates | Overview / Work Flow / Calendar / Showcase; existing flow preserved, Board/Table switch; metallic styling, optional multi-image auto/manual slider and gallery, full-width activity graph/date filters, compact cards and visible titles. | Creative server/interface tests and prior source/data release audits. |
| Creative ownership and accountability | One brief under its lead; prominent support people within it; correct team roster; daily-only imports excluded; current project link, CR project name, client receipt/portal creation dates, both teams’ deadlines, provenance, comments and history. Dates panel collapses on calendar cards. | Connected workflow and Creative tests; live source checks. |
| Existing briefs can link to projects; staff and executive UI parity | Existing-record project connection and project picker; common Creative calendar component used for both roles. | Picker / cross-role regression tests and production checks. |
| Bidirectional Client Relations ↔ Creative / Operations connections | Optional routing, one canonical project name, original request and routing dates, HOD commitments, comments, assignment, sign-offs and completion reflected from shared records. | End-to-end workflow tests and live link / orphan / duplicate audits. |
| Operations review reliability | All pending submissions available for review; repeated approval and changed-source comparison handled safely. HOD assignment/sign-off remains required. | Review queue / approval conflict regression tests and prior live checks. |
| Operations assets and project custody | All Operations staff can submit inventory proposals; HOD reviews before publication. Project asset quantities queue Warehouse requests and assign custody to the Operations lead. Actual issue/return remains authoritative. | Warehouse permissions/custody tests; all nine Operations profiles checked live in the preceding release. |
| Operations handover publication rule | Handover populates the master tracker; staff prepare their weekly reports themselves, then HOD reviews. No automatic duplicate weekly entry. | Connected handover publication tests and live duplicate checks. |
| Finance dashboard overhaul | Eight tabs; shared date/period/member/status/search filters; overview cards and graphs; separate revenue, cash, expenses, receivables and payables calculations; GHS display fixed. Local preview links respond. | Finance tests, filtered-source reconciliation and prior live eight-tab render checks. |
| Finance connected projects and independent revenue | Linked CR/Operations identity and processing states; direct Finance revenue entry outside CRM; original PO/invoice sources remain separate from actual revenue/cash. | Source-link / duplicate / amount tests; live CRM source audit. |
| Precious / Benedicta workflow and payroll privacy | Precious can publish her entries; other Finance entries wait for her review. Seven business KPI areas. Payroll workspace excludes individual salaries, bank details and payslips. | Finance review/privacy tests and five live Finance profiles in the preceding release. |
| End-to-end source entry and accountability | Operations activation entries now include schedule/staffing, crew/vendor bookings, external assets, compliance and real tasks; CR entry forms include process milestones. Original entrant, HOD publisher and later revisions are retained. Shared plan access does not grant executive editing permissions. | Staff → HOD → tracker mutation tests; live Operations field checks; 9/10 staffing = 90% source calculation. |
| Further existing connections | Native independent Ops activations and direct Creative briefs are visible; original warehouse catalog/custody, exact task-linked finance processing and upstream dependencies are reused. Approved absences provide coverage context without private reasons or automatic delivery credit. | Exact-source / stock / dependency / privacy tests and production reconciliation. |
| Executive calendar readability | Compact day-number headers, wide horizontal cards, a separate accurate date strip, collapsible full-width details and compact month entries. | Calendar modes render live; visual walkthrough limitation below. |
| New executive command centre / Ericka tracker | Seven organized views, unified organisation calendar, canonical projects, process, staffing, tasks, resources/assets, all 13 compliance checks, ownership/history, source reports, ten required chart themes, reminder settings/preview/log. Existing native department data retained. | Workbook coverage matrix, executive cross-role/source tests and final production verification. |

## What remains

### Business records that cannot be invented

The forms and connections are available. Historical gaps still need the responsible staff to supply actual facts: client brief receipt dates, missing original requested deadlines, old project links, agreed delivery dates, required staffing, targets/results, external asset requirements and compliance evidence. Missing information stays visible and earns no undeserved credit.

Finance’s original year-only PO/invoice records need real transaction dates before they can be assigned to a week or month. A PO, proposal or imported example does not prove recognized revenue, payment or receipt. Historical weekly reports remain available; the system does not manufacture new financial ledger entries from their narrative.

The new executive controls start with existing native source records. Additional execution plans, bookings and checklist evidence appear when entered. No workbook example projects, people, sample contact information or placeholder holidays were imported into production.

### Reminder configuration

The new executive deadline rules start in **Preview**, matching Ericka’s workbook. Preview candidates, timing, active rules, escalation, subject and sign-off are available in Accountability. Live delivery begins when its mode is changed to Live; existing submission/update notifications and Creative reminders retain their existing delivery settings.

### Visual verification remains open

The Mac browser accepted navigation, but its screenshot/control surface returned a blank page. Server rendering, role permissions and calculations passed; a final visual walkthrough of the new executive layout is still unverified. No outstanding feature implementation was identified in the reviewed request set, but that does not turn this visual check into a completed test.

### Production findings

- All seven executive views rendered for Super Admin, CVO and Traffic. Week, month, year and agenda modes rendered; six department dashboards and all eight Finance tabs also passed source/render checks.
- The 2026 portfolio contains 109 latest Client Relations projects, 97 independent approved Operations activations and 48 direct Creative briefs. These are separate source records unless explicitly connected; similar names are not proof they are one project.
- There are 96 active execution records missing staffing/compliance setup and zero published shared execution controls so far. Existing activation records are visible instead of being omitted. A zero staffing-gap count does not establish full staffing: the separate setup count exposes unknown plans.
- There are 1,279 attendance records. HR has 7 recorded clock-ins against 8 expected eligible staff-days in the current period, so attendance is not zero; business reporting achievement remains a different measure. Figures are a snapshot at verification time.
- This week has 10 Creative briefs (4 scheduled, 6 in progress), zero first submissions and zero dated verified deliveries. There are no eligible completed briefs lacking a completion date. Zero verified deliveries is supported by the original records for this selection.
- The original Warehouse Assets source currently has zero published tracked catalog items and zero warehouse requests. Unrelated office assets or POSM movements are not silently converted into warehouse stock.
- Finance has zero new financial registers, zero native supplier invoices and zero native reimbursement claims. Twenty-four original CRM PO/invoice trackers and fifteen historical weekly Finance reports remain preserved. They do not by themselves prove actual revenue or cash. No confirmed CRM revenue actuals support a nonzero recognized-revenue total in the checked selection.
- The executive year view reconciles 864 open commitments and 804 overdue commitments. Sixteen deadline conflicts require absence/coverage review. No duplicate source event keys or orphan interdepartmental links were found.
- Reminder delivery remains Preview; verification did not queue synthetic business notifications. No test business records were written in production.

## Evidence

- Executive workbook comparison: `docs/audits/2026-11-19-executive-tracker-coverage.md`.
- Application regression run: 853 tests / 7,819 assertions passed.
- Interface logic: 64 tests passed.
- Executive, source-entry and privacy group: 16 tests / 188 assertions passed after the calendar adjustment. The further dependency check brought the source-connection group to 5 tests / 73 assertions, all passing.
- Earlier feature releases and data findings are retained under private release audits; no synthetic production business records were created for verification.

## Follow-up on 20 November

The executive Projects view now includes the live departmental journey with nested stages. Standalone Mega Table daily tasks have been excluded from the executive calendar and its project commitments, following the clarified scope. Read-only published-source navigation, filtered return links and cross-route pagination handling were audited and updated. Browser access worked on 20 November: the project journey layout, expansion, live refresh and workflow navigation were inspected successfully. See `2026-11-20-executive-project-flow-and-navigation.md` for the updated checks. Earlier counts above remain the dated 19 November snapshot.
