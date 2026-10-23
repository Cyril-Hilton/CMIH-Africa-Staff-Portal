# Creative Studio — local implementation and acceptance report

Built inside the existing CMIH Staff Portal. The public website, Brands Portal and Creator Universe were not redesigned by this change. Nothing has been deployed.

## What staff will see

Creative work now has eight connected views: Overview, Board, Calendar, Showcase, Workload, Reviews, Weekly Presentation and optional Details. The Creative section of the shared departmental dashboard opens the visual weekly presentation. Other departments retain their existing reporting tables. The historical Creative table remains collapsed for reference and legacy row editing.

The six presentation sections are Week at a Glance, Work Showcase, Progress Board, Delivery Timeline, Performance Snapshot, and Decisions & Next Week. Staff can navigate sections directly, use Previous/Next or arrow keys, and enter full screen.

Every internal department can submit a brief. The portal records the sender's actual account, department and role; users cannot supply someone else's identity or backdate the portal receipt. The Creative HOD/line manager assigns the creative, reviewer, work window, internal ETA, effort and optional impact points. CVO and Super Admin retain executive access.

## Dates and shared accountability

The record separates portal receipt, assigned creative acknowledgement, reported earlier external handover, original historical record date, requested deadline, planned start, internal ETA, first submission, latest resubmission, feedback due, review decision, delivery approval and final manager verification.

Delivery & Correction Rounds lists each submission, correction request and approval with its actor and time. The following round is the corrected work's resubmission; earlier rounds remain visible. The immutable activity timeline also retains plan changes, acknowledgements, uploads, comments and state changes. A later deadline extension does not rewrite the first submission's on-time result. Historical date-only brief/deadline values are labelled as dates rather than presented as precise receipt times.

Shared briefs are visible to neutral staff across departments. Restricted briefs/files are limited to the requester, assignee, reviewer and authorised Creative leadership. Shared visibility does not grant permission to move another person's work or verify it.

## Workflow and scoring

Brief Received → Scheduled → In Progress → Awaiting Review → Approved for Delivery → Verified & Completed. Backlog, Needs Information, Changes Requested and Archived handle exceptions. Returns require written feedback and a correction, changed scope or clarification category. A new review submission requires a deliverable attachment or delivery evidence link. Reopen/archive actions require reasons. Drag-and-drop opens the necessary action form rather than bypassing evidence, feedback or role checks; Move To provides a keyboard/mobile alternative.

Requester review and independent Creative management verification are separate. Completion credit requires verification. Ordinary task forms cannot bypass a planned Studio task's workflow. Waiting for review is displayed separately and excluded from the creative's accountable completion denominator; it does not earn unverified credit. Each manager-assigned review round has its own reviewer timeliness obligation. Historical verification survives archiving.

The existing 10% attendance / 65% verified completion / 25% department KPI formula remains. HOD-approved impact points are separate from estimated hours, and the verified delivery component uses those points. The shared KPI visual shows approved achievement percentages and measurement coverage, without exposing private target/actual amounts.

## Acceptance mapping

| Requirement IDs | Local delivery and evidence |
| --- | --- |
| 1–3 | Idempotent legacy Task/weekly import, source links, retained original records; unknown receipt/effort/capacity not invented. Import test covers no duplicate tasks/scoring records. |
| 4–6 | Cross-department intake, authenticated snapshots, scope/type/priority/deadline/references/attachments; server reference/time/revision. Identity-forgery and input-validation tests. |
| 7–8 | HOD planning with assignment, reviewer, start, ETA, effort, impact and blockers; requested deadline separate; change/override reasons and audit. Independent-role and stale revision tests. |
| 9–10 | Eight visual views; photo/name staff selectors and All Creatives; search preserves selected staff. Browser view and filter checks. |
| 11–16 | Drag handle and Move To; needs-information/rework; evidence, versioned feedback and independent verification; locked revisions. Full four-account browser handover plus transition/permission tests. |
| 17–19 | Archive/reopen with history; private files/previews; append-only feedback, evidence, retained historical proofing tools/design library. Archive-score preservation and file-visibility tests. |
| 20–23 | Shared dashboard Creative workspace, six-section weekly presentation, linked visual showcase; optional historical/detail tables. Dashboard regression tests and actual browser screenshots. |
| 24–27 | Week/month/agenda and staff lanes; unassigned/unscheduled section; working-day allocation, recorded capacity, approved leave/holidays, workload warnings; blockers/next actions/review queue. Capacity arithmetic and holiday/leave tests plus browser capacity/calendar checks. |
| 28 | Reference/title/client/requester search; department, staff, stage, priority and date filters. Date filtering includes activity in the selected period even where the original deadline was earlier. |
| 29–30 | Immediate in-app delivery plus durable email outbox; deadline/review reminders and escalation; delivery state shown separately. Retry/idempotency test with a simulated provider outage; no real email sent by the test. |
| 31–34 | Separate submission/review clocks; neutral review waiting; per-round reviewer obligations; existing 10/65/25 and impact-weighted verified completion preserved. Numeric score, frozen first-deadline and multi-round review tests. |
| 35–36 | Role checks, safe shared projection, protected attachments, authorised branded PDF/Excel with confirmation. Direct ordinary-task bypass blocked. Export visibility and real-format tests, browser downloads and rendered PDF inspection. |
| 37–38 | Phone/tablet/desktop layouts, contained horizontal board/calendar, native dialogs with focus/Escape and blurred backdrop; keyboard actions; reduced motion. Bounded paginated feed and idle refresh; no localStorage system of record. 3,000-record feed test. |
| 39–40 | Full PHP suite, confirmation JavaScript tests, production asset build; eight views at five viewport widths; separate-account browser workflow, drag/drop, capacity, upload, search, calendar and downloads; console/network inspection. |
| 41–42 | Neutral observer sees the dated shared history without mutation powers; acknowledgement/external reports/notification delivery separately labelled. Four-account browser test and dedicated acknowledgement tests. |

## Validation evidence

- Staff Portal full suite: **557 tests, 3,758 assertions passed**. The Creative test group contains 19 tests.
- Existing confirmation JavaScript tests: **12 passed**.
- Production asset build passed.
- Actual isolated Chrome browser: end-to-end requester → HOD → creative → corrections → resubmission → requester approval → independent verification → neutral observer.
- Browser interaction pass: capacity saving, native drag-and-drop, protected upload/preview, calendar month navigation, retained staff selection during search, cancelled/confirmed PDF and Excel exports.
- Responsive browser pass: all eight views at **390, 768, 1,024, 1,440 and 1,920px**. No document-level horizontal overflow observed. Board/calendar/details use their own scroll areas where appropriate.
- No page or network errors in the completed browser passes. Screenshots were inspected, including phone details, board/calendar and rendered PDF.
- Evidence and repeatable preview-browser scripts: `storage/app/private/creative-studio-qa-2026-10-22/`. These scripts use only the isolated synthetic preview accounts/database, not production.

## Operational boundaries

- Production migration, historical import, real mail-provider delivery and scheduler operation must be checked during the separately authorised rollout. Local tests used synthetic data and simulated/array mail transport. They do not prove receipt by a real person's inbox.
- The earlier 25 CRM source facts still require real Client Relations confirmation in production; this change does not fabricate or reconcile them.
- Review feedback defaults to two company working days; the review-timeliness KPI has relative weight 10 within the person's KPI component. Both defaults live in `config/creative.php`. Capacity has no fabricated default; leadership records it.
- The feed loads 48 records per page, with Load More. Selection-level summaries identify their loaded basis; approved KPI results are separately period-labelled. Idle refresh runs every 30 seconds and pauses during editing, dragging or open details.
- PDF export supports up to 300 filtered records and Excel up to 10,000; larger selections must be narrowed rather than silently truncated.
- Existing historical tools remain available. There is no claim of automatic verification of external email/WhatsApp content or notification reading.
