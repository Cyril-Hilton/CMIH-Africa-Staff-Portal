# Creative workflow update — 23 October 2026

The shared Studio and embedded weekly board now use six lanes:

1. Brief Received — all new briefs, including those recorded by creatives directly from clients.
2. Scheduled — a Creative HOD/line manager has assigned an active creative and recorded the work plan.
3. In Progress — active production.
4. Awaiting Review — the assigned creative submits completion evidence for Creative HOD/line manager inspection.
5. Approved for Delivery — an independent Creative HOD/line manager has approved release to the requester/client team.
6. Verified & Completed — an independent Creative HOD/line manager explicitly confirms client approval, records its details and supplies the quality score.

Ordinary assignees cannot approve delivery/completion or move work after submission for review, including when they originally submitted the direct-client brief themselves. Requesters can return released work for corrections; the feedback category and reason are retained and the actual lane becomes In Progress. Creative leads can return inspected work for correction. Historical submission and feedback dates are preserved. CVO/Super Admin retain their authorised executive access.

New briefs remain unassigned until Creative management plans them. Creatives already have access to the shared New Creative Brief form; the explanatory copy now calls out direct-client intake. Reviewer assignment is restricted to an independent Creative lead. Work assigned to the HOD still requires another authorised lead for approval.

Migration 090000 merges existing Backlog into Brief Received and Changes Requested into In Progress without changing original receipt dates or deleting event history. Older source tasks/weekly records are retained. This migration was rehearsed on the local preview and isolated production copy, not on production.

## Verification

- Visible browser: six lanes, no Backlog lane; assigned ordinary Creative dragged synthetic work from In Progress to Awaiting Review and it persisted. Their transition form disappeared after submission.
- Visible browser: manager approved local sample delivery; missing client-approval confirmation blocked completion; explicit confirmation/details and quality allowed completion.
- Visible browser: manager dragged Awaiting Review back to In Progress, supplied correction feedback, and the saved lane changed while first submission time and correction update history remained intact.
- Phone check: Studio and board scroll stayed within their containers; temporary viewport overrides reset. Existing screenshot evidence is local and not committed.
- A sign-in flow defect found during role switching was also fixed: a background dashboard polling endpoint cannot become an ordinary staff member's login landing page.
- Final full regression: 605 tests / 4,213 assertions passed. JavaScript confirmation suite: 13 passed; production bundle build passed.

No production deployment or data mutation is part of this update. Local browser mutations use clearly labelled disposable QA records.
