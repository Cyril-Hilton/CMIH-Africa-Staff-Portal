# Review accountability, automatic attendance and departmental KPI cards

## Requested behaviour

Separate staff submission timeliness from manager review turnaround. Managers must approve or return by the end of the next working day. Preserve existing Creative review deadlines. Prevent automatic attendance from reporting a completed day before 18:00. Make KPI warnings actionable and style the collapsible KPI cards across departments using their existing colours, including the Brands Productivity cards.

## Implementation

- The completed automatic task policy is recognised by ranking verification. Ordinary completed tasks continue to require their normal independent review. The earlier live correction was committed as `49ee052`.
- Automatic attendance stays open during the current working day; the scheduled close runs at 18:01 Africa/Accra and records the configured 18:00 close. No future clock-out or advance overtime is written. The routine repairs existing premature current-day closes and skips future dates and the current day before 08:00. Historical legitimate closes remain intact.
- Submission audit events freeze the responsible reviewer and review deadline. Weekends, company holidays and approved reviewer leave are excluded. Review decisions preserve the original deadline and late responses remain late. Existing pending entries can be projected from retained submission history; new submissions freeze their review metadata in the append-only audit trail.
- Review obligations cover task completion, commitments, Brands, Client Relations, Operations reports and handovers, Finance source records, HR and Admin reports, KPI targets/results, and warehouse inventory intake. Finance retains its named reviewer permission rules. Missing eligible reviewers are flagged instead of assigning blame to staff.
- On-time pending task/commitment submissions receive provisional delivery credit; this does not publish unapproved money, quality results or verified completion. Late task submissions retain a separate timeliness measure. The first recorded task submission and deadline remain the basis for timeliness through correction rounds. Commitment reporting uses staff submission timing where available instead of a manager's later completion timestamp.
- Pending reporting areas and KPI results are held for manager review instead of being described as missing staff evidence. Review turnaround is included in the manager's KPI measures and overdue review actions appear in live staff/executive ranking attention.
- A permission-scoped Review Queue shows submission, due time, response, responsible reviewer and source links. Native workflow permissions still govern decisions.
- Creative KPI gap links open a dedicated follow-up destination. Assigned active briefs link to the original brief; an empty state explains when role-specific targets are needed instead of implying a nonexistent quality form exists for portal maintenance.
- All departmental KPI cards and ranking disclosures use the shared collapsible presentation with department accents: Brands blue, Creative blue, Client Relations teal, Operations orange, Finance gold, HR/Admin purple. Brands Productivity now has individual blue stat cards and collapsible scoring notes. Existing values, tables, filtering and entry controls are retained.

## Verification

Full PHP suite: 933 tests / 8602 assertions passed on the final core release. Focused checks subsequently cover the first-submission correction-round rule, native Finance submit/approve and publication separation, all department task-review ownership, leave/holiday deadlines, late manager responses, source-link routing, privacy, automatic attendance timing and the retained scoring weights. JavaScript suite: 81 passed. Production asset build and whitespace checks passed.

Production deployment uses file hash guards and rollback backups. An initial attempt correctly rolled back when dashboard rendering invoked the automatic attendance repair during the protected-state comparison. The retry performs the authorised correction as a controlled, backed-up step before comparing other protected business state.

## Supervisory and notification policy

All executive dashboard accounts, including the CVO, Ericka and Super Admin, are excluded from personal attendance, automatic task creation, scoring and ranking attention. Executive reporting lines are cleared by the user model. Ordinary staff Cyril and Curtis retain their automatic feature. Global outbound email delivery is blocked, including queued mail; notifications remain in-app. No previous emails are recalled.

## Live verification

The 45-file core release deployed successfully with rollback backups. Live checks confirmed executive exclusions, all six departmental KPI presentations, actionable KPI follow-up, the mail block, normal staff privacy, and all six weekly dashboards for executive viewers. One premature automatic attendance session was corrected; other protected business records were unchanged. Brands Productivity cards were visually checked in the live browser, including collapsed scoring details. Finance table-specific entry improvements are a separate follow-up release.
