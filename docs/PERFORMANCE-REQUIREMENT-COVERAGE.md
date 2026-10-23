# Performance upgrade — current requirements reconciliation

Updated 21 October 2026. This replaces the earlier incremental matrix. Scope is the local Staff Portal upgrade; production deployment has not occurred. Design coverage and executable implementation are distinguished below.

| Master brief section | Current implementation / evidence |
|---|---|
| 1 Role | 45-part business/process/data blueprint; portal implementation follows the existing Laravel stack. |
| 2 Business objective | Evidence-backed delivery/KPI summaries, executive business cards and explicit missing-source coverage. |
| 3 Accountability chain | Objective → KPI → commitment → owner → evidence → independent review → corrective action → decision and audit. |
| 4 Architecture layers | Normalized objectives, versioned KPIs, plans, observations, commitments, evidence, dependencies, cases, policies, outbox and report packs. |
| 5 Functional summary | Six department views, shared weekly results, private source registers and executive overview. |
| 6 Tracker | Scoped filters, owners, targets/results, dates, quality, supporting staff, reasons and approved target amendments. |
| 7 KPI dashboard | Approved target/actual/variance, period coverage, RAG and owner/team filters; original definition versions retained. |
| 8 Exceptions | Typed invoice, budget, complaint, vacancy, maintenance and project rules; repeated failure across distinct periods; approved effective policies. |
| 9 Trends | Monthly/quarterly KPI comparisons, delivery trends, cause analysis and explicitly labelled projections. |
| 10 Client Relations | Private pipeline/scorecard, weekly/monthly/yearly targets, archived import, editable source correction queue; public percentages/masked amounts. |
| 11 Creatives | Original weekly columns plus brief received/deadline; brief source adapter and typed workload/quality register. |
| 12 Projects | Operations workbook registers, milestones, approved budget/task linkage, actual-cost correction/review, risks and dependencies. |
| 13 Finance | Revenue, invoices, receivables/payables, credit notes, ageing, expenses and cash; executive totals distinguish bank balance from movement. |
| 14 HR | Workbook-shaped reports, prior comparison, source population for current roster/attendance, draft corrections and explicit N/A states. |
| 15 Administrator | Separate office, fleet, driver, asset and priorities sections inside HR/Admin; quantity/efficiency calculations and safe public summaries. |
| 16 Cross-functional | Cycle-safe dependency graph; counterpart department confirmation; execution blocked until upstream verification. |
| 17 Scorecard | Eight dimensions and exact 10/65/25 staff/team scores; missing components not reweighted; source-task completion deduplicated. |
| 18 Workflow | Accept/execute/submit/review/complete/verify/close/reopen; revisions, independent verification, evidence and approved baseline amendments. |
| 19 Automation | Scheduled deadline/exception sweeps, deduplicated dashboard/email outbox, retries and period reports. Live email delivery is a release-environment check. |
| 20 Hierarchy | Individual/line-manager/department/executive scopes plus direct-report team filtering across sources, plans and results. |
| 21 Data architecture | Normalized operational schema and all requested Dim/Fact mappings documented. External warehouse/BI views remain a recommended integration design. |
| 22 Governance | Append-only database guards, audit revisions, immutable report packs, original source retention and explicit reconciliation workflow. |
| 23 Dictionary | Definition versions, future effective dates, independent approval, owner/reviewer/source/formula/threshold/evidence metadata. |
| 24 Executive dashboard | Delivery/function results and approved-source revenue, GP, receivables, cash, satisfaction, critical issues and open risks with source references. |
| 25 Drilldown | Scoped function/owner/KPI/commitment views, evidence/history, source registers and issue/action trail. |
| 26 Root cause | Controlled causes plus issue/risk/action/decision records, owners, deadlines and independent closure. |
| 27 Meeting mode | Commitments, misses, causes, impacts, actions, decisions/review dates and scoped PDF/Excel reports. |
| 28 Executive reporting | Weekly/monthly/quarterly schedule and immutable versioned report packs retaining definition/source/evidence references. |
| 29 Technology | Current Laravel implementation and Microsoft/BI alternatives assessed in blueprint; no external subscription/tenant setup assumed. |
| 30 Collection | Typed department forms plus existing-task/budget adapter and current HR source population; imported CRM retains source provenance. |
| 31 Security | Server-side role/export/evidence scope, CVO/Super all-module policy, independent approval and no-store restricted CR reveal. |
| 32 Mobile | Responsive stacked forms, contained tables, department tabs and centred confirmation dialog; desktop/mobile browser checks recorded. |
| 33 Business rules | Direction-aware math, periods, ageing, thresholds and future effective exception policies; scoring weights remain fixed. |
| 34 Analytics | Department-specific target/delivery/quality/financial/capacity measures retain units and disclose missing coverage. |
| 35 UX | Department tabs, collapsed HR actions, concise subtitles, no-wrap badges, stable cards, Excel/PDF exports and centred blurred confirmations. |
| 36 Business questions | Mapped in blueprint and answered only from qualified sources; absent revenue/cash sources display Not Measured. |
| 37 Roadmap | Seven phases documented with release checks separated from local implementation. |
| 38 Outputs | All 45 requested blueprint sections present in PERFORMANCE-ACCOUNTABILITY-BLUEPRINT.md. |
| 39 Principle | Traceable ownership, trusted sources, independent review and useful intervention; incomplete source facts never invented. |

## User-specific additions

- Preserve Brands/Creatives weekly columns; Creatives adds brief received and deadline.
- HR/Admin remains one department with HR and Administrator reporting tables and collapsed tools.
- All staff can view shared departmental results. Raw money remains private; CR HOD/line manager and executives have audited two-minute reveal, with refresh/tab-hide remasking.
- Operations printing form uses its supplied workbook structure, manager/executive access and styled PDF.
- CRM source import retains 2,555 rows from 36 tabs, 287 project snapshots and 34 target source rows. The four current owners use the approved mapping. Nine target rows are confirmed; 25 are flagged with correction prompts, ownership and independent review. Those 25 facts require source-owner correction, not guessed figures.
- Shared dashboard tabs, pagination interruption handling, title capitalization, status badges and card presentation addressed.
- User-facing exports return genuine XLSX or PDF. Legacy CSV payloads are converted centrally; CSV import compatibility is retained.
- Download and sensitive actions use confirmation dialogs with centred positioning and blurred background; Cancel/Escape preserve data.

## Verification record

- Latest full repository run after repairing baseline defects: **501 tests / 3,421 assertions passed; zero failures and zero errors**. Earlier runs with 88 failures/5 errors are historical, not the current result. Test configuration explicitly uses all application modes; production staff-only restrictions remain unchanged.
- Focused upgrade run: **63 tests / 971 assertions passed**, including the team-filter regression; affected 15-test / 233-assertion run also passed.
- Confirmation policy: eight JavaScript tests passed. Frontend production build passed.
- New migrations 000024–000025 and the restored attendance/category-target migrations applied, rolled back and reapplied successfully in a disposable SQLite database. Existing migration/audit-guard tests cover the earlier upgrade migrations. MySQL production runtime is not claimed tested.
- Visible browser checks cover ordinary department roles in earlier audit entries, executive modules, source corrections, printing, source linkage, current HR population, policy proposal, cross-department request and counterpart approval, linked-source independent approval, mobile layout and confirmation cancellation. See PERFORMANCE-TOUR-AND-AUDIT-2026-10-20.md.

## Release and source limitations

The implementation is local. Production migration/import, live notification delivery and production-engine verification still require the deployment environment. External Teams/Power BI/Fabric are assessed future integration choices, not connected services. Forecast usefulness and business KPI thresholds require operational validation with real reporting periods. The 25 source facts remain explicitly assigned for Client Relations correction, with the requested workflow in place. These limitations are not silently counted as verified data or a production release.

## Baseline defects closed — 21 October follow-up

The previously failing legacy tests are now passing. Repairs restored missing visit-window APIs, attendance clock-out columns/model fields, category-target model/table and category KPI aggregation; retained configured SKU facings/planogram/SOS fields; fixed encoded PHP property arrows in confirmation attributes; restored protected photo delivery and local initials; and stopped route targets from silently truncating scheduled outlet lists or assigning them on unrelated days. Root routing now respects the configured application kind: staff still opens login, brands opens its hub, all/website uses the existing site controller. Legacy tests now assert genuine XLSX exports and the user-requested Gemini-first provider sequence. These are functional fixes and updated requirements, not disabled assertions or skipped tests.

## Latest requirement audit

See PERFORMANCE-24H-AUDIT-2026-10-21.md for the requirement-by-requirement audit, six gaps corrected during the audit, fresh 501-test verification and explicit live-data/integration limitations. Deployment is on hold at the user’s instruction.

## Latest scoring and local completion check
The final user-approved formula is **10 / 65 / 25**, with a Total Score / 100% card and manager-only verified completion credit. Latest full suite: **517 tests / 3,473 assertions passed**, plus eight confirmation checks and a successful build. Local mail transport/retry and scheduled-command tests are complete. See `PERFORMANCE-SCORING-10-65-25-2026-10-21.md` for evidence and the distinction between optional external service connections and release-environment checks.
