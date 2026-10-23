# Creative Studio implementation checklist — 22 October 2026

Build on the existing Staff Portal; do not replace its shell or fabricate missing historical facts. Deploy nothing. References: supplied Creative Tracker developer PDF and HTML prototype; subsequent user decisions take precedence (Kanban is in scope now; PDF/Excel instead of CSV; executives retain portal-wide access).

## Acceptance scope

1. Existing brief/task records preserved and imported idempotently.
2. Existing weekly records preserved and linked without duplicated task scoring.
3. Unknown legacy dates, capacity and effort explicitly Not Recorded, not invented.
4. All internal departments can submit briefs using authenticated identity and role/department snapshots.
5. Brief title, type, client/internal project, scope, priority, requested deadline, timezone, references and attachments.
6. Server-generated reference, timestamp and revision.
7. Creative HOD/line manager plans assignment, primary owner, start, internal ETA, effort and reviewer.
8. Requested deadline and internal ETA remain separate; recorded reasons for changes.
9. Distinct visual Overview, Board, Calendar and Showcase; nested reviews/workload/history.
10. Staff photos/names filter every view; All Creatives option.
11. Drag-and-drop with server-side state transitions and accessible Move To fallback.
12. Needs Information clarification route.
13. Rework loop with mandatory feedback and correction-vs-scope-change categorisation.
14. Evidence required for submission, version history and external delivery evidence.
15. Independent requester review and creative manager verification; no self-verification.
16. Row locking and stale revision conflict handling.
17. Reopening/archiving with reasons and complete retained history.
18. Private attachment delivery, previews and downloadable originals.
19. Append-only comments, feedback, source links and existing proofing comments/files.
20. Shared creative dashboard accessible from every department's main dashboard.
21. Replace Creative weekly table presentation with visual views; table remains optional for detail/export.
22. Six-section weekly presentation with Previous/Next and section navigation.
23. Showcase artwork, videos and document links, connected to tasks.
24. Calendar week/month/agenda, per-person lanes, deadlines/received/delivery markers, unassigned list.
25. Workload hours apportioned over working days/weeks, configurable capacity, leave/holidays and overload reasons.
26. Historical planned load separate from remaining future work.
27. Milestones, blocker ownership, next actions and review queue.
28. Search and filters: reference, title/client, requester/department, assignee, stage, priority, date range.
29. Notification outbox for intake, assignment, changes, submission, review and completion.
30. Idempotent deadline reminders and reviewer escalation; failed delivery retry through existing outbox.
31. Submission and approval clocks separate; review waiting does not create creative lateness.
32. Awaiting-review work has no unverified completion credit and is reported separately from accountable completion denominator.
33. Reviewer timeliness measured from manager-assigned review obligations, not attributed to the creative.
34. Preserve 10/65/25 and manager-approved impact weighting; connect verifiable task completion and creative KPI sources.
35. Role-specific actions, confidential requests and restricted attachments; safe shared projections.
36. Branded Excel/PDF exports with export permissions and confirmation; no CSV.
37. Mobile/tablet/desktop layouts, keyboard alternatives, focus/escape and reduced motion.
38. Shared live state, pagination/bounded feeds, no localStorage as system of record.
39. Automated tests of migration, workflow, permissions, scores, calendar math, exports, revisions and notifications.
40. Actual browser workflow and visual tests at phone, tablet, desktop and wide desktop; inspect console/network.

41. Neutral staff see shared workflow, timestamps, evidence and next-action owner, not only requester/assignee.
42. Portal submission, assignment acknowledgement, externally reported handover and notification delivery are separately labelled. Never backdate system timestamps or equate notification sending with reading.

Status: implemented locally. See CREATIVE-STUDIO-HANDOVER-2026-10-22.md for the acceptance mapping, test evidence and operational boundaries. No production deployment has been performed.
