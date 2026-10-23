# Local upgrade closure — 23 October 2026

## Data entry and shared views

Creative Studio and the main dashboard use the same authorised endpoints and central records. New Creative Brief is available in both. Opening a brief exposes assignment, production, feedback, evidence and verification according to the actor's role. No duplicate data entry is needed. All permitted departments can follow shared records; confidential records remain restricted.

## Final changes

- Shared default Creative production board, calendar, showcase, workload, reviews, activity and report tabs; retained historical registers collapsed.
- Exact six report sections with weekly/monthly/quarterly/yearly editions and period-specific aggregation and visual styling.
- Atomic completion-image submission; private evidence automatically used in presentation/PDF previews.
- Read-only plan conflict check, date-bounded capacity changes and scanner integration with fail-closed production behaviour.
- Explicit source comparison, test coverage, independent verification and impact-weighted scoring retained.

## Validation

- Staff full suite: 571 tests, 3,949 assertions passed. Creative subset: 33 tests, 300 assertions passed.
- Brands/Merchandiser full suite: 451 tests, 3,317 assertions passed. Two existing clock-window tests now freeze a valid workday/time rather than depending on midnight.
- Public website: 372 tests, 2,096 assertions, no failures/errors; 72 existing PHPUnit metadata deprecations remain maintenance warnings.
- Creator Universe surface: 118 tests, 1,078 assertions passed. Filter includes Creator, Universe and UI presentation tests. Copied Staff Portal test classes in that split repository target routes which do not exist there and are not represented as Creator feature coverage.
- Staff JavaScript confirmation tests: 12 passed. Production asset build passed.
- Actual visible browser: new brief → plan preview → confirmed assignment → production → completion image submission → review approval → independent verification → report showcase. Original four-account handover evidence remains in the earlier handover.
- Phone 390px, tablet 768px, desktop 1440px and wide desktop 1920px checks; contained board scrolling and no document-level horizontal overflow in tested views. Native sizing restored.
- Rendered and inspected all six pages of each report edition; confirmed PDF API response and cancellation flow. These are selected visual reports; limits/selection counts are labelled, and full history remains in the portal.

## Rollout boundaries

No production deployment. No guessed CRM facts. Production must configure the scanner executable, scheduler, mail delivery and backup/restore procedures during authorised rollout. The code refuses production uploads when mandatory screening is unavailable. Local tests use synthetic records and simulated scanner outcomes, not a claimed live security service.

## GitHub

Push only after local closure. Use a non-production upgrade branch; do not merge into the production/default branch or invoke deployment tooling. Exclude real environment secrets and private preview databases/evidence.

## Final workflow and navigation follow-up

- Mega Creative now shows daily task entries only; weekly Creative retains its visual board/calendar/report workspace.
- Daily controls: search, entry date, status, priority, staff, sorting/direction, 10/20/50 records and server pagination. Searching no longer forces Creative page two back to page one.
- Weekly controls: shared search/filter/sort with 12/24/48/120 records and Previous/Next navigation. Filters reset to page one; changing pages keeps the selection.
- Fixed the dashboard generic form handler intercepting Creative forms, and prevented outer automatic refresh from resetting Creative tabs/filter state. Creative manages its own live updates.
- Client Relations can submit unassigned briefs; Creative leads assign themselves/team and split original briefs into individually tracked work packages.
- Operational drag/drop works forward/backward, including paused backlog; evidence, review and independent verification remain enforced. Reopening removes verified completion credit.
- Priorities appear on board/calendar/details. Native mouse moves exercised across all seven columns. Desktop and 390px phone controls inspected; no horizontal page overflow or console errors in the final control checks.
