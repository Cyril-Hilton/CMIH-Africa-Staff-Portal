# HR weekly delegation, Operations cleanup and executive navigation

Maxwell Addo Opoku (verified live account 123, active HR/Admin staff) now has an explicit, restricted weekly-entry delegation. The grant applies only while his account remains active and in HR/Admin; it does not change his role or grant full HR, payroll or finance audit privileges.

The contributor workspace exposes 19 non-confidential register kinds and activity entry forms in 10 HR/Admin function tabs. Sensitive staff files/documents, recruitment candidate files, probation, individual appraisals, improvement plans, employee cases, welfare case files/feedback and exits remain restricted. Direct register writes and document URLs enforce the same restrictions. Delegated contributors cannot archive records or inspect other staff's confidential activity histories. Activities are scoped to the contributor's own or assigned work, in allowed tabs. Existing full HR and executive permissions remain unchanged.

Peggy Agyemang Kwarteng (verified live account 16) already had payroll permission. Added a visible **Payroll & Payslips** link to the HR workspace, guarded by the existing payroll permission. The payroll ledger itself keeps its existing access checks. Maxwell's access to his personal employee payslips remains the standard employee access, without the all-staff ledger.

Removed **Approvals, Reports & Risks** and **KPI Results** from Operations navigation. Removed the KPI results table partial and disabled the retired approval/report/risk entry kinds in Operations forms, tables, summaries and review lists. Old tab URLs open Weekly Workstream. Previously recorded data is retained; nothing was purged. Automatic KPI reporting outside these removed tabs is preserved.

Fixed an executive report link with two fragment anchors. Report department switches now select Reports explicitly, clear incompatible person/project/source-category filters and mark the active department. Executive weekly department tabs use full navigation with a clean department/period URL, updating all related panels instead of only replacing a partial section while retaining stale executive filters.

## Verification

- Actual rendered HTML forms: all 19 delegated register types and activity entries in all 10 allowed HR/Admin tabs save and appear in the destination tables, in an isolated database.
- Negative tests: confidential register writes, private records/documents, another manager's activity, payroll export, staff file access and archive requests remain blocked.
- HR manager Payroll link and destination load successfully.
- Executive report department links and weekly department links load their target pages; department dashboard links use exactly one correct anchor.
- Retired Operations tab URLs load a usable fallback; retired kinds cannot be submitted.
- Full application suite: **970 tests / 10,350 assertions passed**.
- Focused end-to-end matrix: 10 tests / 1,016 assertions passed. Final focused privacy/navigation/Operations suite: 17 tests / 674 assertions passed. JavaScript: 82 passed.

Synthetic entries and approvals are confined to the test database. Production verification is read-only and compares protected business data before and after rendering.
