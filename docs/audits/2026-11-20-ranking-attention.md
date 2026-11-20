# Persistent ranking attention

The user requested that anything affecting employee rankings remain flagged to the employee and the executive dashboard until resolved. The agreed 10/65/25 score and existing eligibility rules remain unchanged.

## Visible behaviour

Staff see their own ranking attention panel near the top of their dashboard, outside collapsed sections. Executive views show an attention indicator; Accountability lists the affected staff, the current issues and the responsible staff/reviewer/manager role. Executive department and person filters apply. Staff cannot retrieve another employee’s detailed flag projection.

Flags are calculated from live records, with no dismissal action or independent manual status that can drift away from the source. Correcting a missing clock-in, adding a real deadline, supplying KPI results or completing manager verification clears the corresponding flag when the dashboard is read again. A recorded low outcome or late clock-in remains visible unless its original record is legitimately corrected or the reporting period changes.

Checks include required KPI setup/evidence, recorded KPI shortfall, eligible missing clock-ins, recorded lateness, missing measurable delivery, missing task deadlines, submitted work awaiting review, other unverified linked work and Creative HOD scheduling gaps. No screenshot/image requirement is introduced. The new performance-cycle date is displayed, and cleared days do not create absence penalties. Approved leave, weekends, holidays and future/unstarted eligibility do not create missing-attendance flags.

The flags describe setup, outstanding review, work in progress and recorded outcomes separately from final performance. Fresh-cycle preparation uses “Setup needed before cycle starts”; incomplete live scores remain provisional. They do not become official awards merely because a provisional numerical order exists.

## Notifications

The portal’s hourly executive reminder command checks ranking attention as well. Staff receive their own unresolved items; executive viewers receive a staff attention digest. Dedupe keys include date and issue signature: unchanged issues do not generate repeated alerts in the same day, but recur on a later day while unresolved. Live dashboard flags remain visible between notifications. Notification history is a dated snapshot; its link opens the current live status.

The command processes the notification outbox during the same run. Existing notification channel settings remain authoritative. Confidential HR/Finance event content is separately subject to recipient permissions in deadline digests; the ranking digest does not reproduce case narratives, medical/leave reasons, salaries or banking details.

## Accuracy correction

The leaderboard’s Task completion column previously reused the total credited ranking score in its projection. It now displays the actual task completion percentage. Credited score remains a separate column and still controls ranking order.

## Validation

904 application tests / 8,242 assertions passed. A further focused run after the final preparation-task query and refresh view passed 16 tests / 97 assertions. Targeted tests cover live flag persistence/resolution, staff scope, approved absence, same-day deduplication and next-day recurrence, immediate delivery, and actual completion percentage versus total points. Production release verified 29 staff with unresolved flags (27 KPI evidence, 2 KPI setup, 2 delivery setup), 30 delivered portal alerts, same-day deduplication, private staff scope, visible staff and executive panels, and no archived attendance/KPI credit. Live browser verification confirmed 29 executive attention flags and zero premature employee ranking positions before the new cycle starts on 23 November.

The current flag panel and executive indicator refresh from live server results every minute while the page is visible; this does not reload open forms. Failed refreshes retain the last valid flags.
