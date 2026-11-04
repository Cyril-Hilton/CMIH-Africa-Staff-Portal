# Brands reporting, Creative dates, leave handovers and Action Points

Date: 4 November 2026

## Delivered

- Replaced Brands weekly dashboard and module reporting with six table-based categories: Productivity, People, Perfect Store, Activations & Volume Depletion Initiatives, Visibility Deployment, Weekly Work Stream.
- Every category supports week, month, year, staff photo/name filters, search, sorting and pagination. Perfect Store separates Castel and Unilever; location and staff are kept separate for comparisons. POSM ledger reporting remains connected without changing stock balances.
- Brands staff submit entries; managers publish them or return correction notes. Authors correct and resubmit. Stale revisions and concurrent source changes are rejected. Managers can report their own operational work. Publication is separate from independently verified KPI results.
- Existing weekly rows, rich tables and images, supporting roles, reporting dates and additional saved fields are retained. Corrections preserve a version record. Narrative reports are displayed without inventing structured measurements or importing prototype figures.
- Creative leads can choose an earlier actual/planned work start and internal delivery deadline when creating a brief. The real portal receipt timestamp stays separate. Calendar milestones include Work Starts.
- Leave requests require handover notes. Private optional attachments, covering-colleague acknowledgement and response, revision history and fresh acknowledgement after correction are implemented. Existing leave records remain valid.
- Action Points saves, status changes and deletions refresh only the Action Points table. Edits use a dialog; duplicate submissions are blocked; unsuccessful status changes revert the displayed selection. Errors remain visible.

## Verification

- Full PHP regression: 703 passed, 5,526 assertions.
- Final focused feature regression after UI refinements: 73 passed, 560 assertions.
- JavaScript checks: 23 passed.
- Production asset build, Blade compilation and diff whitespace checks passed.
- Two older October scoring tests now freeze their intended October clock so they remain valid when the calendar moves into November. Their manager-signoff assertions remain unchanged.
- Isolated SQLite QA copy and local server used for all browser mutations. No fake reports, leave requests or meeting actions were created in production.
- Browser: Action Point edit/save/delete; all six Brands categories; retained HTML tables; pagination; staff filter; Perfect Store partner toggle; year period; report submission and manager publication; required rich leave notes; Creative lead earlier-start fields.
- Responsive inspection at 375, 768 and 1440 CSS pixels. Tables scroll inside their boundaries. Duplicate generic table controls were removed from the new Brands workspace.
- Console review: validation test intentionally returned 422 for missing breakdown, followed by successful submission. One transient service-worker/offline 503 did not recur on a fresh load and subsequent report requests. Extension-origin messaging errors remain outside the portal code. Do not describe the console as entirely error-free.

## Hosting dependency

A read-only production-host probe found no configured upload scanner, known ClamAV executable or ClamAV PHP extension. Production remains fail-closed for new attachments. The leave form explains this and disables new file selection when scanning is required but not configured. Required notes and existing protected downloads remain available. The scanner is the remaining dependency for enabling new handover attachments; no security checks were bypassed.

## Release safeguards

Additive migrations only: leave handover fields and Brands submission queue. No historical backdating or report re-entry was performed. Release procedure backs up replaced files, compares existing record counts/fingerprints under maintenance, verifies all Brands period/category projections and a historical week, compiles views, and restores previous code automatically if validation fails.

## Production release verification

Initial release b2dc3bc completed with additive migrations and successful read-only projections for all six categories and all three periods. Unchanged existing-record counts/fingerprints: 98 users, 2,021 tasks, 159 weekly entries, 23 leave requests, 12 meeting action points and 24 CRM financial rows. The seven historical Brands weekly entries remain available; the earliest is 19 October 2026. No structured Brands performance-register measurements existed in production, so these new tables must not be populated with prototype numbers. Existing narrative tables remain readable.

A final month-boundary regression attributes weekly reports to the month/year of their reporting start, preventing a week spanning month-end from inflating two monthly reports. All ten final Brands reporting tests pass (92 assertions), including month-boundary attribution, copied-draft publication and retention of explicitly confirmed reporting partners.

Copied-week drafts remain manager-only until publication and do not inflate published task totals. Managers can open a draft, complete it and submit it for publication. Direct draft-detail access by ordinary staff is denied. A dedicated regression covers this flow and historical approved publication.
