# Department tour and requirements audit — 20 October 2026

## Verified scope

Visible in-app-browser walkthrough on local isolated preview port 8093. Signed into ordinary staff accounts for Brands, Client Relations, Operations, Creatives, Finance and HR/Admin, then Operations manager for restricted printing. No production changes or external messages. Browser visit is not equivalent to successful full workflow or mobile acceptance.

| Department | Visually visited | Observed | Outstanding |
|---|---|---|---|
| Brands | Staff dashboard, weekly headings; reporting workspace visited earlier | Original nine weekly headings retained; own 30/35/35 score | Representative source records, complete staff-to-reviewer flow, legacy dashboard simplification |
| Client Relations | Staff dashboard/private pipeline/scorecard; history previously | Sample 100 target/50 actual gives 50%; period-target/history entry points | Historical archive is not yet operational import; owner/period mapping, deduplication and reconciliation; populated weekly/monthly/yearly targets |
| Operations | Staff dashboard/performance/registers | Master projects and source-workbook table navigation; contained horizontal table | Populated workflows, partial returns/project linkage, financial reconciliation, grouped wide columns |
| Creatives | Staff dashboard/week table/personal KPI page | Original weekly fields plus received date/deadline; department targets not assigned as personal achievement | Populated brief/task adapters and personal baseline examples; manager editing/date capture flow |
| Finance | Staff dashboard/invoice reporting workspace | Invoice/payable/cash/expense navigation, approved-source coverage disclosure | Existing accounting-source reconciliation, full ageing/credit notes, populated approval flow |
| HR/Admin | Ordinary HR staff module/HR reporting/Administrator form | Collapsed module panels; sensitive sections hidden for staff; HR sections and separate Administrator reporting | Complete correction/resubmission workflows in browser, source attendance integration, approved comparisons, derived efficiency, public summary |
| Operations printing | Manager request register/edit/saved document preview | Branded complete request with specification, vendor, checklist and download links | New browser save/download round trip and final multipage PDF inspection |

## Scripted checks

77 targeted tests / 703 assertions passed after latest changes: performance hub, target plans, Operations tracker, printing, shared tables, HR/scoring, executive access, privacy, weekly accountability, cases, report packs, login and dashboard customization. `git diff --check` passed. This is not a claim that the entire pre-existing repository test suite passes.

## Requirement reconciliation

The full 39-section master-brief matrix remains in PERFORMANCE-REQUIREMENT-COVERAGE.md and the 45-output blueprint remains in PERFORMANCE-ACCOUNTABILITY-BLUEPRINT.md. All items stay tracked. Update the older matrix's implementation statements with these newer facts:

- Independent case/action/decision lifecycle exists, with evidence, designated reviewer, no self-closure and parent closure blocked by open actions.
- Linked task/weekly commitment deduplication has a regression test.
- HR draft/returned row corrections and explicit N/A section states implemented; Administrator return/correct/resubmit implemented, browser round-trip still required.
- Source exception service handles overdue invoices/payables/client issues/vacancies/maintenance/milestones and budget tolerance; configurable environment defaults exist, policy/UI configuration and repeated-underperformance rules remain.
- Immutable per-recipient report packs capture rows, definition versions and content hash; deeper immutable evidence/source snapshots and production scheduler/delivery verification remain.
- Weekly shared source tables for Operations/Finance have explicit field allow-lists; latest user instruction preserves Brands/Creatives original weekly columns. HR/Admin shared projection still outstanding.
- Weekly/monthly/yearly/YTD target types exist; CRM workbook source archive holds 2,555 rows from 36 tabs in preview only. Normalized operational/project/target import is NOT complete.
- CVO/Super Admin have all-module access; ordinary roles remain limited and self-approval remains blocked.
- HR module has 11 collapsed tool panels, with actions kept inside panels. Administrator wording replaced Reception in report navigation.

## Remaining release gates

1. Complete normalized CRM import, preserve actual versus forecast distinctions, historical periods, mapped owners, idempotency and reconciled totals.
2. Complete source adapters and avoid duplicate reporting/scoring across existing tasks, projects, HR, finance and new registers.
3. Approved baseline amendments, effective KPI versions, team-lead membership scopes and supporting-team attribution.
4. Complete financial/executive metrics, quarterly/delivery trends, typed exception coverage and repeated failure rules.
5. HR/Admin source integration, derived efficiency and safe staff-wide summaries.
6. Cross-function linkage, full action/evidence drilldowns, BI/master-data/reporting contracts, retention/audit controls.
7. Populate representative test records, exercise create/correct/return/approve/reopen/export/notification paths in both scripts and browser, test mobile/keyboard/overflow.
8. Validate build/migration rollback and final full-suite delta against known baseline failures; production deployment and live validation have not occurred.

Do not describe the overall upgrade as complete until these gates are resolved or an explicit scoped release decision is made by the user.

## Follow-up QA

- Added protected target-amendment approval workflow and regression test.
- Added numeric-only approved HR/Administrator shared reporting; privacy and derived fuel-efficiency test passed.
- Added repeated-failure detection and quarterly comparisons with scope separation.
- Extended immutable packs with source/definition/target/result/evidence metadata snapshots.
- Added POSM partial-return quantity validation and overdue regression test.
- Browser at 390 × 844: no document-level horizontal overflow; department tabs wrap; HR/Administrator panels expand. Tables retain contained horizontal scrolling.
- Browser amount reveal was exercised as CR manager; ordinary staff and another department's manager are denied by endpoint tests. Refresh masking was observed. Timed masking is tracked separately until a full uninterrupted two-minute check completes.
- Local server was restarted on port 8093 after an interrupted turn stopped it; preview database retained.

## Final follow-up verification

- Uninterrupted reveal test: amounts remained visible during the permitted window and were masked at the 139-second check without refreshing or switching pages.
- Private CRM search → Edit Project → change shared comments → save succeeded in the browser. Editing now opens a full-width panel instead of a narrow far-right table cell.
- All detailed CR/Operations/Finance source tables now follow the departmental tabs, rather than remaining visible together below the KPI panels. Unauthorized private-module navigation links are hidden.
- Operations manager edited and saved printing request revision 2; saved preview matched it. Browser download succeeded. Rendered A4 PDF originally exposed a checklist spill; spacing was corrected, then the newly downloaded PDF was confirmed as one page and visually inspected.
- KPI version proposal → separate reviewer login → approve succeeded in the browser. Original v1 retained; v2 starts 1 November in preview sample data.
- Finance credits reduce outstanding balances; ageing uses the reporting date, capped at today. Calculation regression test passed.
- Database triggers protect audit events and report packs from direct UPDATE/DELETE on SQLite/MySQL; SQLite direct-query protection test passed. MySQL runtime deployment validation is still required.
- All 22 upgrade migrations rolled back and reapplied on a disposable SQLite database. Production database untouched.
- Latest focused suite: 88 tests / 1,036 assertions passed. Full repository: 481 tests / 2,888 assertions, 88 failures and 5 errors. Failure/error-name comparison with baseline found zero new failures/errors.
- Frontend build and whitespace checks passed. Local server now runs detached so a turn interruption does not terminate the preview.
- Source-data decision remains pending for 25 ambiguous historical CRM target rows. Private reconciliation list: storage/app/private/crm-import-reconciliation.md.

## CRM correction workflow and visual closeout

- Private imported-target queue flags missing/ambiguous dates and amounts, shows original sheet/row and review notes, and supports owner/manager correction proposals followed by independent manager confirmation or return. Original import reference remains unchanged; proposal and review are audited. Revision conflicts and self-confirmation are rejected. In-app outbox notifications link to the private queue without financial values.
- Browser: Client Relations manager submitted a clearly labelled DEMO correction; another Client Relations manager confirmed it. All 25 actual ambiguous source rows remain flagged. No source values were guessed or silently approved.
- Four individual dashboard cards use opaque surfaces and consistent sizing to avoid backdrop blur recompositing. Shared table-pill CSS prevents bordered rounded status badges from breaking across lines; visible In Progress badges measured one line (25px) after reload.
- Build and whitespace checks passed. Targeted correction/import/page/dashboard checks: 32 tests, 382 assertions; subsequent workflow/privacy checks: 14 tests, 337 assertions. Full repository: 482 tests, 2,900 assertions, 88 failures and 5 errors. Compare failure names with prior baseline; do not claim the full suite is green.
- Migration 000023 applied to isolated preview only. No production migration, deployment, external integration or live email validation is claimed.

## Delivery trends and executive access follow-up

- Added monthly assigned/verified/completion/on-time/overdue delivery trends to the Trends view. Regression verifies review-stage work is not completed and undated completions are excluded from the on-time denominator. Browser heading/view rendered successfully.
- Fixed CVO recognition in existing project budget model/controller and collaborative workspace access checks; explicit CVO-with-staff-role tests pass, unrelated ordinary staff remain forbidden.
- Affected existing workspace/financial routing/budget suites: 18 tests / 123 assertions passed. Added executive workspace and delivery cases: 3 tests / 14 assertions passed. Hub/report-pack/page matrix: 9 tests / 251 assertions passed. Build and whitespace checks passed.
- Migration 000023 apply → rollback → reapply passed on disposable SQLite. No production changes.

## Export and confirmation upgrade — 21 October

- Shared response formatting now delivers actual styled XLSX for legacy CSV and CSV-disguised-as-XLS downloads, retaining existing endpoint authorization and query scopes. Navy headers, alternating rows, frozen headings, filters, wrapping, borders and print settings; literal text prevents formulas and preserves leading zeros.
- HR record archives now contain XLSX files; edited staff XLSX files can be uploaded through the existing preview/confirmation workflow. Legacy CSV uploads remain accepted for compatibility. Twenty-six export/HR/attendance/warehouse tests and 232 assertions passed; updated legacy contract/scoring tests: 20 tests / 115 assertions passed.
- Performance and warehouse PDF output uses branded tables and proper PDF bytes. Existing report-pack tests pass. Browser downloaded real PDF and XLSX; workbook inspected with read-only library, PDF rendered for layout review.
- Downloads and sensitive actions use an accessible centred confirmation dialog with blurred/dimmed background, Cancel/Continue choices, Escape dismissal and focus return. Existing simple inline form confirmations migrated to the shared dialog. Existing complex native confirmations remain in place to avoid removing protection. Browser Cancel and Confirm paths exercised; fixed interception of the dialog's own Download button.
- All changes remain local. The wider integration/release gates are not declared complete.

### Export/confirmation closeout checks

- Extracted action classification into a tested policy: downloads, sensitive actions, explicit prompts, normal navigation and Archive Directory navigation are covered. Task delete buttons use requestSubmit flow rather than bypassing submit handlers. Five JavaScript policy tests pass.
- Focused export/import/warehouse/report/legacy-contract suite: 47 tests, 357 assertions passed. Full suite after updated Excel contracts: 488 tests, 2,931 assertions, same 88 failures and 5 errors as baseline.
- PDF metadata now uses full-width values separate from KPI/commitment tables; KPI and commitment headings receive consistent workbook styling.
- Browser inventory explicitly reports local browser use disabled; remaining mobile/dialog workflow visual checks cannot be certified. Desktop centred/blurred modal was visually checked before access became disabled. No production release performed.

## Imported-project date reconciliation

- Existing private project editor exposes an explicit manager-only confirmation for an ambiguous source period. The placeholder date is no longer prefilled as a real date. Confirmed date must be 2000 or later, original source reference is immutable during import-record editing, and the correction is audited with the existing revision/change reason.
- Unconfirmed imported projects are not published to the shared weekly projection. Once a manager confirms the reporting date, the normal safe projection is published. Ordinary staff cannot forge confirmation. CRM import/privacy/reveal/correction suite: 7 tests, 103 assertions passed.
- Added Archive wording for soft-deletion confirmations and activation/reactivation coverage. Six confirmation-policy tests pass.

## Final local verification — 21 October

- Browser access restored. At 390×844, confirmation measured 352px wide, centred with 19px side margins; Escape dismissed it, desktop Cancel dismissed it, and task Delete → Cancel left the sample task unchanged. Screenshot /tmp/cmih-confirmation-mobile-final.png and /tmp/cmih-delete-confirmation-final.png.
- Confirmed same-origin download links now navigate directly to the authorized endpoint after confirmation, avoiding synthetic click/delegated-navigation conflicts. Browser download-event tooling timed out, so no new saved-file claim is made for that event; earlier real XLSX/PDF downloads and server contract/format tests remain the file evidence.
- Corrected source exceptions: approval does not settle an invoice/payable; full payments plus credits clear the overdue alert. Shared project reports no longer remain overdue. Regression tests passed.
- Hourly automation queues private, deduplicated CRM source correction reminders for the accountable owner and authorized CR managers/executives, without amounts in notifications; unrelated department manager receives none.
- Focused upgrade run: 84 tests, 1,074 assertions passed before adding the reminder test; later affected CRM/automation tests: 9 tests, 98 assertions passed. Full repository run: 492 tests, 2,945 assertions, 88 failures, 5 errors. Failure-name comparison against baseline: zero new failures/errors. Six confirmation-policy tests, frontend build and diff whitespace check passed.
- Actual source facts remain unresolved (25 target rows) with the user's requested in-app flags/correction workflow. Production migration/import/deployment and external service delivery are not performed. Earlier broader integration/architecture gates remain recorded in the requirement matrix; this is not a 100% release claim.


## 21 October — final source/workflow follow-up

Added current-period HR roster/attendance source population (existing rows retained; stale/approved/historical protection), approved-task/budget linkage and controlled corrections, executive business cards with qualified source rules, future effective exception policy proposals with independent executive review, and cross-department dependency proposals/counterpart confirmation/returned-request resubmission. Team filters now apply consistently to results, plans, registers and staff, and other authorized team options remain selectable. CVO header now labels the executive role correctly.

Visible browser: HR source population added three missing rows and retained the existing absence row; dependency request WCT-4 → WCT-7 stayed pending and did not create an active blocking edge; policy #1 stayed pending with unchanged thresholds and a future date. All sample writes are local-only DEMO data. Mobile dashboard width was 390px with no document overflow. Browser download-event callbacks did not return saved paths for the latest confirmed downloads; endpoint/export format tests and earlier saved-file checks remain the evidence, not a claim of a newly saved browser file.

Latest full run: 498 tests / 3,014 assertions, same 88 failures + 5 errors as preceding baseline; zero new failing names. Focused 62 tests / 965 assertions passed, new team-filter test passed, then affected 15 tests / 233 assertions passed. Six JavaScript confirmation policy checks and frontend build passed. Disposable SQLite migrations 24–25 up/down/up passed.

Screenshots: /tmp/cmih-hr-source-population-final.png; /tmp/cmih-dependency-request-final.png; /tmp/cmih-dashboard-mobile-final.png (initial capture included navigation transition; subsequent settled 390px screenshot verified clear content); /tmp/cmih-policy-editor-final.png. Requirements matrix has been rewritten as the current authoritative reconciliation instead of retaining contradictory old remaining-work entries.


Final follow-up: Operations manager independently approved local dependency request #1 and linked source register #25 through the centred approval modal; both approved states were visibly verified. Final focused run 63 tests / 971 assertions passed; seven JS checks passed. Legacy custom confirmation gained keyboard focus cycling/restoration and the certificate-resend native prompt was replaced with the styled modal. The visible browser is left on Weekly Departmental Performance, Operations / Projects tab. Final screenshot: /tmp/cmih-final-department-dashboard.png; approved source screenshot: /tmp/cmih-source-approved-final.png.


## 21 October — repository suite repaired and green

Full run `/tmp/cmih-full-repaired.log`: **498 tests / 3,407 assertions, all passed**. This supersedes previous baseline failure counts. Test mode no longer inherits the developer `.env` staff-only redirect policy; production isolation is unchanged. Restored missing attendance/category source contracts, fixed encoded Blade arrows, retained SKU fields, corrected route visibility/calendar behavior, protected profile photos/local initials, and updated obsolete CSV/provider-order test contracts to the requested XLSX/Gemini-first behavior. No tests were skipped. The affected subsystem run independently passed 124 tests / 711 assertions.

Browser recheck on the staff dashboard confirmed the Operations manager page renders and the local initials image loads. Screenshot `/tmp/cmih-dashboard-repaired-final.png`. Restored migrations were applied to local preview and tested up/down/up in a disposable SQLite database. Production data and deployment remain untouched. Real ambiguous CRM facts stay assigned for correction under the workflow agreed with the user.


## 21 October — requirement audit follow-up

A requirements audit found and corrected historical scope double-counting, prior calendar-period boundaries, missing dedicated deadline filtering, Finance contribution/GP variance measures, meeting print confirmation, and field/landmark presentation issues. New regression tests and visible browser checks cover the additions. Full suite: **501 tests / 3,421 assertions passed**; focused: 21 / 222; JavaScript: 8 passed; frontend build passed. User explicitly instructed **do not deploy yet**. Detailed audit: PERFORMANCE-24H-AUDIT-2026-10-21.md.
