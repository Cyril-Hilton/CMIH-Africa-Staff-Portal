# Final local scoring and automation checks — 21 October 2026

User-approved formula supersedes the earlier 30/35/35 design:

- System Attendance: 10 points. On-time clock-in days / expected workdays. Approved leave, non-working calendar dates and dates before employment are excluded; future workdays are not penalised. Attendance is not scored a second time as a separate clock-in component.
- Verified Task Completion: 65 points. Verified assigned tasks/deliverables / all assigned tasks/deliverables in the reporting period. Linked task/weekly records count once. A completed status alone is insufficient. Task approval needs a recorded review timestamp, an independent manager and the expected review route. Weekly deliverables need a manager verification audit event and verified/closed state. Reopening removes credit. Self-approval and peer-only verification earn no points.
- KPI Achievement: 25 points. Weighted achievement of the employee's assigned approved KPI results/individual target plans in their department. Administrator KPIs remain inside HR/Admin. Another department's measures or another person's totals do not become personal credit. Existing independently approved per-KPI weights determine the split within the 25 points.

Total = attendance rate × 0.10 + verified completion rate × 0.65 + KPI achievement × 0.25. Rates are capped at 100 for scoring. Missing inputs leave an incomplete total and a measured subtotal; weights are never redistributed. Formula version is `10-65-25-v2`. Existing immutable report packs remain unchanged; legacy pre-upgrade award periods retain their historic methodology.

The dashboard has four cards: the three components plus **Total Score / 100%**. Team performance uses the same weights with pooled denominators. Current award rankings exclude incomplete scores rather than treating missing results as a zero-score winner. API responses retain incomplete records separately. Current ranking breakdowns show attendance/tasks/KPIs and their weighted points; historical displays preserve their original breakdown.

Workflow controls now offer valid next actions for the current actor/state. Final verification is restricted to an independent line manager, department head or executive. Quality input is shown when verification is available.

## Local verification

- Full portal suite: **517 tests / 3,473 assertions passed**, zero failures/errors. Log: `/tmp/cmih-score-10-65-25-final.log`.
- An exact-score award regression confirms both Employee of the Month and Department of the Month return **62.5** for 100% attendance, 50% verified completion and 80% KPI achievement (10 + 32.5 + 20).
- New tests explicitly cover self/peer approval rejection, real manager sign-off, forged verified labels, reopening, foreign-department KPIs, exact weights and total-card rendering.
- Local array mail transport exercised the real mail construction/delivery path, recipient and portal link. Reprocessing did not duplicate mail/dashboard notifications. Simulated transport failures stopped at five attempts without duplicating dashboard notifications.
- Hourly processing and weekly/monthly/quarterly report schedules are registered for Africa/Accra with overlap prevention and tested cron expressions. Email delivery rechecks the locked outbox state to prevent concurrent workers sending the same row simultaneously. A crash after SMTP accepts a message but before persistence can still cause a retry; no claim of universal exactly-once external delivery.
- Eight confirmation JavaScript checks passed. Frontend build passed.
- Browser checked a normal Creative staff account: 0.67 attendance points + 16.25 verified completion points + 20 KPI points = **36.92 / 100%**. These are explicitly synthetic preview records, not real staff assessments. Staff review choices showed Submit for Review without manager-only verification.
- A 390px browser viewport showed no page-wide horizontal overflow. Desktop screenshot: `/tmp/cmih-performance-total-100.png`.

## Deferred boundaries

The 25 real CRM source facts remain untouched and assigned to Client Relations for production correction. No deployment or production mail/scheduler change was performed.

Teams, Power BI/Fabric and accounting/ERP were requested in the supplied master brief as potential integrations/architecture options (sections 29–30), not specified live services with endpoints or tenant credentials. That design work is covered by the blueprint. Connecting a chosen external service requires its actual configuration and an authorised connection; it is not honestly classed as an already connected service or something that can be certified without that service. Live mail delivery, actual host scheduler execution and production database behavior remain release-environment verification, while their local code paths have been tested here.
