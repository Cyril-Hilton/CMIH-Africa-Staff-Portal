# Final portal role, workflow and usability sweep

27 October 2026 · CMIH AFRICA Staff Portal

## Result

The expanded sweep passed the tested office-role/page matrix and full automated regression suite. It found two concrete presentation problems, corrected them and verified the corrections locally before release:

1. Task creation’s successful clock-in notice was too faint in light mode. It now uses dark green text on a pale green background. The footer no longer gives already-clocked-in users contradictory guidance to clock in after saving.
2. Performance & Accountability repeated its title and three completion metrics before users reached their tools. The inner heading is now Delivery & Review; the hero carries the single completion summary, the strip retains overdue/on-time/quality, and executive business/source exception details expand on demand after view navigation.

This is evidence for the scenarios below, not a claim that all possible data combinations, external integrations, physical devices or human usability preferences have been exhaustively tested.

## Roles and access coverage

Fourteen office personas were used: staff and manager in each of the six departments, Super Admin, and CVO. Disposable accounts were created only locally, clocked in locally for workflow access, then suspended and marked as service accounts after testing. Their test attendance was removed. No production staff account was altered for this sweep.

- 336 successful page/dashboard checks: 12 common pages and current/previous week for all six department dashboards per persona.
- The own department module also rendered for every persona.
- Cross-department private module boundaries checked for ordinary staff.
- Clock-in gate tested with enforcement enabled, independently of the test environment bypass.
- Anonymous users redirected to sign-in. A suspended account could not enter the dashboard.
- CVO and Super Admin browser checks included every department module, not only their account’s department.

The common page set: Tasks, Updates, Notifications, People & Teams, Collaborative Workspace, Messenger, Leaves, Fleet Requests, Profile, Finance/own claims, Salary Advances and Appraisals.

## Page inventory

47 static portal entry routes were checked with executive access. 41 returned HTTP 200. Six returned redirects: the weekly copy preview without mandatory source inputs, and five legacy merchandising navigation aliases. No checked route returned 404, 405, 419 or a server error. Parameterised detail/download/edit pages are exercised through their dedicated workflow tests rather than pretending an empty route-inventory check covers them.

Inventory includes handovers, CRM history/pipeline, HR and Administrator reports, report packs, corrective cases, performance views, updates/tasks, notifications/directory, workspaces, assets/warehouse, printing register/form, messaging, attendance, payroll, leaves/fleet, profile/visitors, HR staff/import, all department modules, Creative activity, awards, appraisals, budgets/create, surveys/create and events.

## Browser coverage

130 additional role/page/screen-size checks:

- 76 checks for 14 office personas at measured 375 and 1280 CSS-pixel widths: dashboard plus own module, with all six modules for both executive types.
- 36 checks for 12 shared pages at measured 375, 768 and 1280 widths.
- 18 checks for nine further Finance/asset/HR tools at measured 375 and 1280 widths.
- No horizontal document overflow or duplicate IDs in those inspected page states.

Visual review included phone task creation, successful clock-in feedback, Performance page hierarchy, module navigation, printing instructions and role-appropriate actions. Existing entry-to-dashboard, rich-text preservation, historical reports, filter/reset, copying, printing approval and responsive module-tab checks remain covered by the preceding module release tests.

The final browser sweep uses one desktop browser engine with viewport resizing. It does not replace physical iPhone/Android testing, screen-reader user research or browser compatibility certification. Visible long tables may scroll within their intended containers; no page-wide overflow was found in inspected states.

## Automated evidence

- Full suite: 677 tests, 5,317 assertions passed.
- JavaScript: 20 tests passed.
- Build passed; whitespace/diff checks passed.
- Focused usability/role/performance/attendance checks: 21 tests, 734 assertions passed before the final full run.
- Expanded role/inventory test: four tests, 452 assertions passed.

The full suite includes local scenarios for authentication, account states, clock-in/first-task flow, historical weekly preservation and whole-week copy, Creative workflow/history, Operations handover, vendor printing approvals/PDF locks, CRM imports/target corrections, joint revenue review, financial approval routing, budgets, salary advances, leave/appraisal workflows, HR attendance/staff records/privacy, service-account exclusions, warehouse assets, rich exports, notifications and messaging. Existing field merchandiser/brand-platform tests also ran. The 14-person browser matrix is specifically office roles; it is not described as manual browser coverage of every field-merchandiser screen.

## Metrics and data boundaries

- Operational records, reported weekly results and independently verified KPI results remain distinct.
- No production test transaction, claim approval, payment, weekly copy, file upload or handover was submitted during this sweep.
- Historical reports remain unchanged by the presentation fixes.
- Target setup is still a business task: the reviewed production snapshot had no structured KPI definitions/target plans. Old KPI narratives and tables stay available but do not become approved target baselines automatically.
- The 25 unresolved CRM facts still require staff clarification. No values were guessed.

## Remaining practical limits

There were no failing automated checks or confirmed unresolved blockers in this sweep. Usability judgement is not mathematically “100%”: long populated reports, actual staff devices, external mail/SMS/scanner/storage availability and newly introduced real-world data can still reveal cases not represented here. These should be assessed with specific evidence, not hidden behind a blanket completion claim.

Confidential personnel/finance documents remain role restricted. The sweep did not weaken permissions, remove approval requirements or alter scoring weights to make screens appear complete.

## Live release verification

The usability fixes were released and checked on production. All 24 selected live module, shared-page, current/previous-week dashboard and CVO Finance requests returned HTTP 200. The preserved-record check found no missing original IDs across 13 tracked tables, including all 158 weekly entries and 139 CRM projects; database integrity was OK. Production browser sign-in and the revised Performance page were confirmed, with no captured warning/error logs on that inspected page. No live test business transaction was created.
