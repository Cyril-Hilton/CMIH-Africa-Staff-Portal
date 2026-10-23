# CMIH Portal — 24-hour requirements audit

21 October 2026, Africa/Accra. Scope: the Staff Portal upgrade, supplied master brief and subsequent reporting, access, import, styling, export and confirmation requests in this work session. Production deployment is explicitly on hold at the user’s instruction.

## Finding

The local feature requirements have implementation and regression evidence. The audit found additional calculation, filter and export-confirmation gaps after the earlier green test run; these were corrected rather than treating green tests as proof of full requirement coverage. This is a local implementation audit, not a claim that live data, production delivery or external integrations have been certified.

## Requirement-by-requirement reconciliation

| Requirement | Audit result and evidence |
|---|---|
| All master-brief outputs covered | All 45 blueprint sections present; the 39 brief sections are mapped in PERFORMANCE-REQUIREMENT-COVERAGE.md. Technology options and future phases are distinguished from connected services. |
| Six departments with different reporting columns | Brands, Client Relations, Operations, Creatives, Finance, HR/Admin schemas and reporting views; WeeklyDepartmentTablesTest and department rendering checks. |
| Original Brands/Creatives weekly columns | Preserved; Creatives adds brief received date and deadline. Existing weekly-table tests cover the distinction. |
| HR and Administrator in one department | Two reporting tables inside HR/Admin; collapsed HR actions, draft corrections, N/A/coverage states and prior comparison. ExecutiveModuleAccessTest and StaffPerformanceAndHrReportsTest. |
| HR/Admin workbook structures | Recruitment, personnel, attendance, training, performance, compensation/relations, metrics/priorities; office/fleet/drivers/assets/next-period sections retained. |
| Exact staff/team scoring | 10% system attendance, 65% manager-verified completion, 25% own department-specific approved KPIs; missing components not rescaled; department totals do not become personal scores. |
| All staff see department results | Shared dashboard department tabs expose approved performance and delivery detail while private operational sources remain scoped. SharedWeeklyPerformanceTest and DepartmentReportingPrivacyTest. |
| Private monetary data | CR amounts masked publicly; reveal limited to CR heads/managers and executives, audited/no-store, hides after two minutes, refresh or hidden tab. Private modules show figures directly. Earlier uninterrupted timer browser check recorded in tour audit. |
| CVO/Super Admin all-module access | Central authorization and module/navigation checks; independent review still required. ExecutiveModuleAccessTest. |
| CR scorecard and pipeline fields | Clients/projects/team/stage/progression/bottlenecks/next action, description/comments, PO/forecast presence, private commercial details and weekly/monthly/yearly targets. |
| Import historical CR workbook | 36 tabs and 2,555 source rows archived; 287 project snapshots and 34 target source rows imported in local preview with approved owner mapping. Twenty-five source facts remain explicitly flagged for correction, never guessed. |
| Ambiguous source correction | Field-specific prompts, assigned owner/manager, independent confirmation/return, stale revision protection, original provenance and reminders. Feature and browser round-trip evidence recorded. |
| Source reuse | Existing tasks/approved budgets can populate operational records; HR current-period staff/attendance population retains entered rows and requires normal report review. SourceIntegration and HR population regression tests. |
| Operations workbook and printing | Typed operations tables, POSM quantities/returns/readiness, scoped manager printing request form, styled PDF. Browser save/preview/export and tests recorded. |
| Accountability workflow | Accept/execute/review/complete/verify/close, evidence/history, reopen/correct, case/action/decision lifecycle, independent verification and baseline amendments. |
| Cross-department dependencies | Counterpart confirmation, cycle rejection, upstream blocking, returned-request resubmission; local Operations manager browser approval verified. |
| KPI definitions and policies | Effective versions, independent review, future dates, immutable historical meaning; configurable exception policies retain fixed staff scoring weights. |
| Period and history calculations | Audit fixed previous-calendar-period selection and department/individual double-counting in previous/YTD totals. New historical KPI regression passed. |
| Function/owner/team/client/project/KPI filters | Scoped across sources, plans/results and staff; audit added dedicated deadline range and validation, distinct from reporting dates. Browser filter showed the expected 23 October deadline. |
| Profitability measures | Audit added Finance contribution, gross profit/margin and GP variance from complete approved sources. Missing costs/targets stay unmeasured. New financial math regression and Finance form browser check passed. |
| Exceptions, trends, reporting | Typed overdue/budget/invoice/SLA/maintenance/recruitment rules, distinct-period repeated failures, monthly/quarterly trends, outbox retries and immutable weekly/monthly/quarterly packs. Live email delivery remains a release-environment check. |
| Presentation and responsive flow | Title capitalization, concise subtitles, stable cards, no-wrap badges, tabs, contained tables and collapsed actions. Audit improved field labels and removed a nested main landmark. Desktop/mobile evidence recorded. |
| Pagination | Stale requests interrupted; page navigation and search no longer silently reset/ignore selection. Earlier browser and regression evidence retained. |
| Neat exports | Genuine XLSX and PDF output; legacy CSV download payloads centrally converted, CSV import compatibility retained. Data strings/formula injection and phone formatting tested. |
| Sensitive-action confirmation | Download/delete/archive/suspend/approval/other sensitive actions, centred blurred dialogs, Cancel/Escape and focus restoration. Audit added meeting Print / Save PDF confirmation; browser Cancel prevented printing. |
| Dummy data and two-way testing | Local representative data across departments; existing ordinary-staff tours and new source/review workflows checked visibly. Automated tests exercise calculations, permissions, state transitions and exports. This does not assert every possible interaction or device has been exhaustively tested. |
| No deployment | Complied. Production server, database, live mail and production users untouched. |

## Gaps corrected during this audit

1. Historical KPI previous/YTD totals could combine department and individual observations. They now use a consistent scope and exclude withdrawn/unapproved baselines.
2. Previous month/quarter/year comparisons could use an equal number of days instead of prior calendar boundaries. Complete calendar selections now compare calendar periods.
3. A dedicated deadline filter was missing; reporting-period filtering alone did not meet that requirement. Deadline From/Through now filters commitments separately, including pagination and export requests.
4. Finance contribution/GP variance were not explicit calculated catalogue measures. A private profitability source form and approved-source calculations now cover them.
5. Meeting Print / Save PDF could open immediately. It now uses the confirmation flow.
6. Small usability/accessibility issues: KPI/owner/evidence field labels and nested main landmark corrected.

## Verification

- Full suite after audit changes: 501 tests / 3,421 assertions passed (latest completion log is retained privately).
- Focused affected checks: 21 tests / 222 assertions passed.
- JavaScript confirmation checks: 8 passed. Production frontend build passed.
- Audit browser screenshots: /tmp/cmih-audit-deadline-filter.png and /tmp/cmih-audit-finance-profitability.png. Print confirmation Cancel checked without printing.
- Earlier migration up/down/up, ordinary department tours, mobile checks, CR reveal timer, source correction, independent approval, printing and saved-file checks remain documented in PERFORMANCE-TOUR-AND-AUDIT-2026-10-20.md.

## Items not certified as live/verified business outcomes

- Twenty-five ambiguous CRM target source facts still need the Client Relations owners’ corrections. The requested flag/correction/review workflow is complete; the facts themselves are not confirmed.
- Real organizational KPI targets/thresholds and forecast usefulness require owner acceptance and real reporting periods; sample data proves behavior, not business performance.
- Live mail/scheduler delivery and production database-engine checks have not been performed. Deployment is on hold, not authorized by this audit.
- Teams, Power BI/Fabric, accounting/ERP connections and advanced AI-assisted insights are addressed as design/integration opportunities and roadmap items, not claimed as working external integrations.

The audit therefore supports local implementation coverage, with the above explicit limits. It does not justify an unconditional “everything is 100% verified in production” statement.

## Latest user decision and additions
The user explicitly deferred the 25 ambiguous CRM source facts to real Client Relations users in production. Preserve the flagged correction workflow and exclude unresolved facts from scoring; do not invent replacements. Deployment remains on hold. Event publication and website image work have a separate implementation/verification record in `EVENT-PUBLICATION-AND-IMAGE-PERFORMANCE-2026-10-21.md`.

## Final scoring revision
The user replaced the original weights with **10% system attendance / 65% manager-verified task completion / 25% department-specific KPI achievement**, and requested a Total Score / 100% card. This supersedes the earlier scoring tour. The final local implementation, verification safeguards, local mail/scheduler tests and 517-test result are recorded in `PERFORMANCE-SCORING-10-65-25-2026-10-21.md`.
