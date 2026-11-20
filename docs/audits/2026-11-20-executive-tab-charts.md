# Executive charts at the top of every tab

Reference: Ericka’s Activations Tracker – Updated Draft, Dashboard chart titles and source references inspected on 20 November 2026. No example workbook values were imported into production.

| Tab | Primary chart | Counting rule |
| --- | --- | --- |
| Overview | Execution / source-status distribution / doughnut | One filtered connected project or independent activation/brief per portfolio record; reported closure is labelled and an unrecorded execution stage remains separate from recorded planning. |
| Projects | Brief-to-budget process pipeline / horizontal bars | Execution projects only, once at the next process step lacking the required completion, evidence and status. Direct Creative work does not require activation controls. |
| Calendar | Planning volume / grouped columns | Work commitments, meetings/transport and approved absences; one count on the first visible day. Reporting activity and attendance are excluded. Year view uses chronological monthly buckets; other periods use daily buckets, retaining zeros. |
| Resources & Assets | Asset status / doughnut | External asset checklist lines and original warehouse requests, not stock units. No second inventory total is created. |
| Compliance | Next ten asset readiness versus compliance / grouped horizontal bars | Open execution projects ordered by scheduled start; undated projects follow. Existing evidence-based percentages are reused; a missing asset plan earns zero credit. |
| Accountability | Ownership and deadline state / grouped horizontal bars | Open work commitments split into within deadline, past deadline or approved-leave coverage review, with each commitment counted once. |
| Department Reports | Source records by register / horizontal bars | Full pagination totals from the same authorized source-report groups, rather than just the eight visible rows. Existing report date scopes and private access rules are retained. |

All charts sit after the common filters and before their tab’s detail content. Existing copies of the same lifecycle, process, asset status, workload and readiness charts were removed. Distinct secondary charts remain available. Large SVG plots use a shared dark teal finish, colour legends, gradient marks, axes, native value tooltips and expandable exact-value tables. Narrow screens retain readable plots with horizontal scrolling; doughnut layouts stack vertically. No new external chart library or data ledger is introduced.

Charts read the authorized tab projection. They do not change KPI scoring, review rules, permissions or original data. Historical missing evidence is not invented, and zero-value series are not given artificial bars.

## Verification

- Focused chart and executive workflow checks: 15 tests, 122 assertions passed.
- Full application suite: 872 tests, 8,019 assertions passed.
- Compiled Blade views successfully.
- Production rendered exactly one top chart in each of the seven tabs for Super Admin, CVO and Traffic profiles, including full-year records. Existing monthly role checks also passed.
- Browser review confirmed the full-width overview doughnut, calendar column and weekly ownership-bar layouts, legends, totals, date context and position above tab details. Opened the ownership chart data table live and checked its values against the plotted rows. Verified the department selector now displays “All departments”.
- Final label and filter cleanup checks: 16 tests, 125 assertions passed. Empty staff-department values no longer create a second empty selector option that masks “All departments”.
