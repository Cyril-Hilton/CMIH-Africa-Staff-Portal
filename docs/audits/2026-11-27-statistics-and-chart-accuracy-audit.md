# Cards, charts and calculation audit — 27 November 2026

Scope: the six departmental dashboards, staff and agency task cards, Executive Command charts, Performance Hub calculations, and shared reporting projections. This builds on the earlier KPI-card audit and Finance partial-entry update.

## Corrections

| Finding | Resulting behavior |
| --- | --- |
| Repeated Brands brief snapshots could retain an older status. | The latest report per brief is used within the selection; an earlier week keeps its own historical result. |
| Creative work awaiting review disappeared from task completion denominators. | Three approved completions and one pending review now display **75% completion**, at individual, department and agency levels. Staff scoring still handles manager review delays separately. |
| Generic SLA calculations used only completed records. | Pending due obligations remain in the denominator; future deadlines are excluded. The selected report cutoff applies to historical calculations. |
| Generic percentage ratios dropped target rows with no actual. | Known targets remain in the denominator, rather than inflating a partially reported result to 100%. An entirely unknown numerator remains unmeasured. |
| Finance cards/charts converted absent actuals into zero. | Missing money remains null and displays as a dash or a gap. Explicit recorded zero remains zero. Partial known totals are identified, and incomplete entries are counted. |
| A missing invoice paid amount was assumed to be zero. | Receivable/payable totals require a known document amount and known paid amount. Missing settlement data no longer fabricates a full outstanding balance. |
| Generic outstanding/overdue calculations omitted credits. | Both subtract recorded credits, matching the Finance and executive balance calculations. |
| The Finance profit table subtracted variable costs as well as direct costs, unlike its KPI. | Gross profit consistently uses revenue minus direct costs. The table, KPI catalog and executive card reconcile. Incomplete pairs remain unmeasured. |
| Executive and generic revenue totals could add consolidated monthly totals to their underlying direct entries. | Monthly totals replace direct entries for that month. Executive labels explicitly identify Finance-recorded revenue; CRM-confirmed revenue remains separately available in Finance. |
| Generic operational metrics summed percentages, internal IDs and repeated bank balances. | Internal identifiers are excluded; percentages without weighting denominators are not added or averaged. Bank balances use the latest known snapshot per account. |
| Historical overdue breakdowns used today's date and disagreed about timely submissions awaiting review. | Report-end cutoffs and the manager-review responsibility rule now agree across summary, department, staff and trend breakdowns. First submission timing is reused instead of blaming staff for later manager approval. |
| Operations pending-procurement chart checked procurement status instead of delivery status. | A delivered item is no longer counted as pending merely because its procurement record says "Purchased". |
| Creative and HR empty populations appeared as zero-percent results. | No measured Creative outcome, no due briefs, and no eligible HR days/population display as unmeasured. Measured zeros remain valid zeros. |
| Non-GHS Finance records could enter GHS aggregates. | The common published-Finance guard excludes other currencies from GHS source totals. Original records are retained. |
| Client Relations timeliness included issue deadlines that were still in the future. | The radar uses only dated issues due by the selected reporting cutoff. |

## Coverage reviewed

- **Brands & Marketing:** productivity, headcount snapshots, attrition, Perfect Store partner/location results, briefing success, activation sales/reach/conversion, visibility gaps, and weekly workstreams. The existing three-won/one-pending success-rate regression was rerun, including search and reporting-period scope.
- **Client Relations:** unique engaged accounts and portfolio focus rate; latest project snapshots; confirmed revenue/targets and growth; decided-deal conversion; due issue resolution and satisfaction. Pipeline forecasts, POs and confirmed money remain distinct.
- **Creatives:** full-cohort counts versus explicitly labelled current-page lists; first submissions, HOD deadlines, verification, grading, review queues, KPI snapshots and time buckets. Missing grades do not become recorded zero grades.
- **Operations:** approved activation counts/status/brand distributions; dated execution trend; POSM deployed/returned/outstanding conservation and invalid-row exclusions; procurement/fabrication counts. Past event dates alone do not prove completion.
- **Finance:** cards and monthly chart reconciliation across week/month/year and all four revenue bases; owner/search/status scopes; whole-period additive amounts; dated cash receipts/payments; known balances; credits; currency and approval restrictions; source-specific executive totals.
- **HR/Admin:** native employee/register/asset/vendor counts; eligible attendance and approved leave; current snapshot versus date-labelled figures; empty populations; executive/supervision exclusions in attendance.
- **Executive and Performance Hub:** complete filtered populations rather than paginated counts; project stages, calendar entries counted once, evidence-based readiness, weighted KPI denominators, source-unit separation, latest balances, and historical deadline cutoffs.

## Interpretation

Completion, success and on-time performance have different populations. Completion includes pending work; a due-work SLA excludes work whose deadline has not arrived; conversion can explicitly use decided deals. Those definitions must be stated rather than making every percentage share an arbitrary denominator. Raw target achievement may exceed 100%; credited performance is capped separately.

Missing reports/actuals do not gain free credit or cause other KPI weights to increase. No business targets, actuals, approvals, payroll values or archived records were invented or edited during the audit. This verifies calculations against recorded evidence; it does not certify that a human-entered amount or declared status is factually correct.

Validation: **1,008 application tests / 10,710 assertions passed**, **85 JavaScript tests passed**, and the production browser bundle built successfully. This includes the original Brands 75% case, every configured SLA/percentage-ratio population, unknown versus zero amounts, chart gaps, profit consistency, credits, balance snapshots, review responsibility and historical cutoffs. Independent production verification passed: **17 denominator checks**, **18 department/period checks**, **93 credited-measure checks**, **12 executive/staff dashboard renders**, **36 Finance chart/card reconciliations**, and checks of the profit definition and browser bundle. All **24 deployed runtime files** matched their tested checksums. Business data was unchanged. A deployment-result reporting error was corrected before this independent verification; no business calculation rollback was required. The final Brands correction also passed production verification: **75% with three won and one pending brief**, **100% after the latest update closes that same brief**, exactly four briefs counted, and the earlier weekly result preserved at 75%. Temporary test rows were rolled back. The final change adds one verified runtime file, bringing the release scope to **25 runtime files**. Both temporary verification schedules were removed.

Brands correction backup: `cmih-statistics-brands-final-20261127-20261127-165302`.
