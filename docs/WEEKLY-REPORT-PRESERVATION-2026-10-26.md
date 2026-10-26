# Weekly reporting preservation

Each weekly entry owns an immutable reporting period. Editable project start/end dates are separate from that period. New reporting periods use system-calculated Monday–Sunday boundaries; existing historical dates are not rewritten.

## Carry-forward

A recurring project/workstream receives a series identifier. Copy to This Week creates at most one draft per series and week. Background carry-forward opens the current week's draft for an ongoing project with an explicitly recorded end date covering that week. It does not invent backdated updates or create future reporting claims.

Project identity, ownership, supporting roles, priority and project timeline carry forward. Results, percentages, actual amounts, target breakdowns, completion claims, evidence and report approvals do not. Previous status/progress is separately labelled as reference information. Carried Creative reporting rows do not create duplicate Creative workflow tasks.

## Corrections and review

Changing reporting dates through the edit endpoint is rejected. Past/submitted report corrections require a reason. Prior values are stored in a version journal inside the same database transaction as the update and are visible in the manager's correction history. Correcting a submitted/reviewed report returns it to draft for resubmission. Historical and submitted reports cannot be deleted through the normal deletion endpoint.

Work status and report status are independent. Submitting/reviewing a weekly report never verifies task completion or awards performance points. Empty carried drafts cannot be submitted. Report review requires an authorised line manager or executive, separate from the last editor and actual submitter.

## Summaries

Weekly/monthly/quarterly/yearly views use the latest linked weekly record for each workstream’s current work status while retaining every earlier weekly state. They distinguish work status, report status and missing weeks for explicitly tracked project timelines. Weeks belong to the month/year containing their reporting week start. Year totals query original weekly records directly, preventing month-boundary double counting. Linked project series are distinct from weekly-entry counts. Historical project links are not guessed; missing historic records are flagged, not manufactured. Free-form percentages and financial tables are not automatically totalled or treated as verified measures.

## Preservation and checks

Migration rehearsal retained all original values in 110 original production tables and all 155 original weekly entries. Existing report status defaults to historical/unreviewed; existing achievements are not promoted to approvals. Tests cover date locks, reason requirements, version retention, deletion protection, duplicate carry-forward, cleared results/approvals, self-review denial, missing weeks and month-boundary accounting. Browser tests exercise carry-forward, locked reporting fields, editable project dates and monthly reporting continuity.

Production rollout preserved all 155 original weekly records and their original columns byte-for-byte. Eight production transaction checks passed and rolled back all temporary rows. The final automated suite passed 623 tests. The hosting scheduler runs idempotent carry-forward hourly.
