# Creative Tracker reference comparison — 23 October 2026

Sources: 26-page Developer Handover v1.0 (22 October) and the supplied interactive HTML prototype. Later user decisions override demo assumptions. This is a source comparison, not a production acceptance certificate.

## Coverage and differences

| Source area | Portal implementation / evidence | Result |
|---|---|---|
| Product intent, shared visibility (§1) | Shared authenticated Creative Studio, neutral staff visibility, central database, request and owner history | Covered locally |
| Intake (§5–6) | Server identity/reference/time; structured brief, references, future deadline, request department snapshot, attachments | Covered locally; attachment can be added after intake |
| Roles (§3) | Active internal staff intake; assigned creative production; independent reviewer; Creative manager planning/verification; CVO and Super Admin full access | Covered; executive access follows explicit user instruction |
| Planning (§7) | Separate requester deadline/ETA; active creative and independent reviewer; positive effort; workday validation; capacity and ETA warnings require override reason; revision conflicts | Covered locally; read-only pre-save forecast added and browser-tested |
| Shared queue (§4/17) | Search title/reference/brand/requester/brief; department, staff, priority, stage, date filters; new newest/oldest/deadline/title sorting | Search by brief and explicit sorting added in this comparison |
| Overview (§4) | Active, review, verification, attention; awaiting assignment, due in next seven days, ETA conflicts, capacity overload summaries | Missing explicit summaries added in this comparison |
| Status/audit (§8) | Server-authorised moves, immutable events and feedback, reasons, return/reopen/archive, revision conflict protection | Covered locally |
| Combined activity screen (§4/12) | New shared paginated Activity view, filtered through authorised request IDs | Added in this comparison; previously audit existed only inside each brief |
| Calendar (§9) | Monday–Sunday resource lanes, week/month/agenda, planned windows, received/deadline/submission markers, unassigned briefs, detail navigation | Covered locally; busy month cells use a More list |
| Workload (§9) | Configurable per-person capacity, holidays/approved leave, cross-week apportioning, historical vs remaining load | Covered locally; effective-dated temporary capacity changes added and regression-tested |
| Evidence and review (§14) | Private files, immutable feedback rounds, separate submission/review/verification times | Covered; integrated multi-image review submission added in this comparison |
| Notifications (§14) | Transactional outbox, in-app notifications, email delivery/retry, reminders and reviewer accountability | Local tests; real mail delivery and hosting scheduler require deployment verification |
| Portal embedding (§15) | Native portal route and shared main-dashboard workspace; no separate login or iframe | Covered; old Creative Mega Table pane replaced during this comparison |
| Exports (§2/12/16) | Styled Excel and PDF, authorisation and confirmation dialogs | User explicitly replaced CSV |
| New report requirements | Six-section weekly/monthly/quarterly/yearly report editions, private image evidence, complete period cohort, on-time first submission and approved KPI bars | Implemented locally; all four six-page editions rendered and inspected locally |
| Security (§16) | Existing authentication/CSRF; role checks; restricted records; private files; MIME/size limits; escaped output; upload throttling | Configurable ClamAV-compatible screening added; production uploads fail closed if unavailable; rejection/unavailable/clean paths tested |
| Accessibility (§16) | Keyboard Move To, labels, native dialogs, focus/escape, reduced motion, mobile layouts | Existing tests; new editions and main views checked at phone, tablet, desktop and wide desktop sizes |
| Operations (§17/19) | Migrations, tests, outbox delivery logs, local preview, handover documentation | Production backups/restore, monitoring, retention policy and rollout sign-off remain operational checks; not proved by local UI tests |
| Suggested future work (§2) | Kanban, file approval, holiday-aware calendars, reviewer SLA and analytics included per subsequent user scope | Multi-project dependencies, actual-hour timesheets and automatic reassignment remain recommendations, not silently claimed features |

## Explicit choices that supersede the demonstration

- Real portal accounts instead of the prototype persona selector and fictional staff.
- Central database and server timestamps instead of localStorage and browser-generated IDs.
- No invented default 32-hour capacity or 8-hour effort; managers set these.
- User requested Kanban now; it is not deferred just because the source calls it a future phase.
- Requested deadline, internal ETA, portal receipt and reported earlier external handover are separate.
- Requester review and independent manager verification are separate. A review backlog does not become fake verified completion or creative lateness.
- Staff scoring remains 10% attendance, 65% verified impact-weighted delivery, 25% department KPI achievement. Work counts are not staff ranking scores.
- Visual Creative views replace the default shared Creative table. Historical records remain retained and accessible.
- Four report editions use different compositions and aggregation periods rather than just different titles.

## Final local closure

- Added the read-only plan preview and date-bounded capacity exceptions, with permission/validation tests.
- Added image evidence to the review submission transaction, preserving files/status atomically. Browser submitted a synthetic image, reviewed and independently verified the work, and found it in the generated showcase.
- Added configurable scanner screening. Production intentionally refuses uploads until a scanner is configured; local isolation is explicitly not a claim that a production scanner has run.
- Corrected shared activity timestamp conversion and added a restricted-record visibility regression test.
- Default Creative Mega pane now uses the production board. Historical task and cross-department-support registers remain collapsed rather than being discarded.
- Corrected review/delivery next-action ownership in reports. Missing KPI results remain Not Measured.
- Confirmed export cancellation and a browser PDF response (HTTP 200, application/pdf). Generated PDFs independently rendered at six pages per edition and visually inspected.
- Phone/tablet/desktop/wide-desktop checks found no page-level horizontal overflow in tested views. Detail opening resets to the top; board lanes scroll independently; keyboard Move To remains available.

## Evidence and limits

Staff Portal: 564 tests / 3,829 assertions passed; Creative subset: 26 tests / 180 assertions. Confirmation JavaScript tests and asset build passed. Tests use isolated synthetic databases. See LOCAL-UPGRADE-CLOSURE-2026-10-23.md for umbrella results and rollout boundaries.

Production backup/restore, retention policy, scanner executable/configuration, real mail delivery and hosting scheduler remain deployment checks. Production rollout is not authorised. The 25 CRM facts remain for real Client Relations staff to resolve. No original source facts were invented.
