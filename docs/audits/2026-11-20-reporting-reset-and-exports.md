# Department reporting reset and executive exports

The user authorized the reporting archive with “greenlight”, then explicitly requested complete removal of collapsed historical consolidated weekly cards and tables across all departments.

## Scope and preservation

The reset archives Mega Table tasks, consolidated weekly entries, departmental reporting registers and submissions, Client Relations pipeline and engagement data, creative briefs, handovers, execution controls and associated reporting dependencies. Original records, IDs, dates, content and links remain retained. Old reporting leaves current dashboards, scoring and project selectors.

Only account/brand/client names are carried into a dedicated Client Relations account directory. Old stages, projects, engagement, ownership and financial values are not copied back into the active pipeline. Names use exact case/whitespace normalization, with no speculative merging of similar brands.

Assets and custody ledgers, vendors and directory contacts, users, attendance, leave/pass-outs, fleet and boardroom requests, payroll, loans, claims, invoices and budgets remain active. KPI definitions and scoring policies are retained; historical reported results are archived. Every database table outside the explicitly selected reporting/archive metadata scope receives a before/after row count and SHA-256 comparison.

Published report packs retain their original payload and hashes. Existing database protections against updates/deletion stay in place. Separate membership records identify archived packs, and an insert guard prevents duplicate active report packs while allowing a fresh reporting cycle with the same dates.

A private complete database backup is integrity-checked before execution. Archive batches retain their scope manifest and backup reference. Emergency batch restoration is available without changing source payloads. Authorized executives and the existing HR/Finance auditor can download read-only archived Excel/PDF records separately from active dashboards.

## Legacy interface removal

Removed historical consolidated workstream tables, the earlier saved weekly report cards, the old Client Relations weekly updates tab, the creative historical weekly snapshot lane, and the shared collapsed “Reporting History & Review Status” card. New departmental forms, active workspaces, date filtering, review queues and current accountability details remain.

Automatic historical attendance/task backfills and the legacy creative importer cannot repopulate archived reporting. Future workdays and new staff entries continue normally.

## Executive exports

The executive portal supports Excel and PDF exports for overview, projects, calendar, resources/assets, compliance, accountability, departmental reports, charts, team, history, reminders and attendance. Users choose the current filters or a fresh selection, current/custom/all recorded dates, everyone or selected people/departments/projects, and individual records after previewing. Exports enforce the viewer’s existing data permissions and revalidate selected record IDs at download time. They include all matching source pages, not only the visible first page.

The HR supplies view also reads existing vendor directory contacts without creating duplicate vendor records or inventing active/contract status.

## Validation

Full application regression suite and targeted reporting-reset, archive export and executive export checks are required. Production verification renders all six departmental dashboards, all seven executive top-chart tabs for the existing executive roles, checks Excel/PDF generation, compares retained table contents, reconciles account directory names, checks source rows remain physically present, and confirms archived records are excluded from active model queries.

## Production outcome

Applied archive batch `1425ba34-f129-4e5d-b14c-62430ba8bf4c` on 20 November 2026 after an integrity-checked private database backup. Archived **6,016 reporting records**, retained **81 original client/brand account names**, and excluded **513 obsolete reporting notification queue entries** from delivery. Historical records remain physically present; active reporting queries returned zero after the reset.

All six departmental dashboard renders passed after reset. Legacy cards and tables were absent. **137 protected database tables** matched before and after verification. Retained operational records include 33 assets, 1,292 attendance rows, 24 leave applications, 127 fleet requests, 52 payslips, two loan records, seven directory contacts and six shared vendor rows.

The full regression suite passed **891 tests / 8,167 assertions**, followed by **17 focused tests / 131 assertions** for final account-directory and export changes, including immutable report preservation, fresh duplicate-period entries, new creative weekly linking after archive, archived Excel/PDF downloads, authorization and all departmental views. Production Excel/PDF archive downloads passed. Executive exports were generated against pre-reset production source data (41,494 matching export records), with the existing executive profiles checked and no synthetic business entries created.

Two guarded deployment attempts stopped before the archive: an export button wording assertion and an immutable report update. Files were automatically restored, and the archive transaction rolled back. The successful release uses separate immutable report membership and retains the original database update/delete protections.


The final directory reconciliation excludes four aggregate staff pipeline target labels and three unconfirmed historical spelling variants that were initially copied into the directory. Those seven labels remain recoverable in their archived source records and the archive correction manifest; no original source data is deleted. The resulting 81 names match the original All Accounts portfolio.
