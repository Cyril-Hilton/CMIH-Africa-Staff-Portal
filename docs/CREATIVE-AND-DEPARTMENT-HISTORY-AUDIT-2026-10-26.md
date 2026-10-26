# Creative dashboard and departmental history audit — 26 October 2026

## Scope and outcome

The Creative dashboard was checked first, including Daily Creative Work, all nine Studio views, calendar modes, reporting periods, staff/search filters, pagination, workflow permissions and exports. Original departmental reports were then connected to the new presentation views. The duplicate **Existing Weekly Reporting** and **Earlier Weekly Entries & Row Editing** sections were removed, including the Creative legacy-table variant. Records were not deleted.

## Corrections

- Original Creative weekly reporting dates now determine historical report inclusion. Import time does not replace the original reporting period, and undated historical records no longer appear in every date selection.
- Previous/Next Week controls are distinct from Previous/Next Section. Calendar navigation clears stale date filters; This Week returns to the actual current week.
- Embedded Creative views use the selected reporting week. Monthly, quarterly and yearly presentation queries use their corresponding full periods.
- Week at a Glance shows saved weekly report status separately from current workflow state and dated delivery measures. For 19–25 October, the original eight weekly reports contain four In Progress and four Planned entries. The workflow cohort contains thirteen work records, including daily work and overlapping historical sources; those are not thirteen weekly submissions.
- Historical completion without an approval date is labelled **Historically Reported Complete**, rather than counted as dated verification. Missing receipt, submission and approval dates are not fabricated.
- Creative notification email processing runs through the database queue rather than delaying the web response. In-app delivery remains immediate; normal retry/outbox records are retained.
- When upload screening is unavailable, the Creative UI explains that uploads are unavailable and offers the existing HTTPS evidence-link workflow. Screening remains enforced by the server.
- Finance, Operations and HR/Admin have a primary **Weekly Updates** view containing original formatted progress, responsibilities, targets, results, gaps, notes and additional custom fields. Client Relations has **Weekly Team Updates** alongside its business performance views; narrative reports are not converted into invented financial figures.
- Edit, correction history, carry-forward and submit/review controls sit within the new update cards. Creative original-report controls sit inside the corresponding brief detail. Rich editors initialise only when opened. Supporting-staff editing also works inside Studio dialogs.
- Only the selected department panel renders, while all department tabs remain available. Sensitive Finance and HR narratives retain their existing access restrictions.

## Record reconciliation

The protected pre-upgrade production copy contains 155 original weekly records: Brands 7, Client Relations 116, Operations 6, Creative 11, Finance 5 and HR/Admin 10. Reconciliation compares original IDs and every original stored column with the live database. It also checks source-to-display IDs and historical periods; retained records are presented directly rather than copied into a second register.

Some original reporting date ranges span several weeks or months. Their original ranges remain visible. Overlap determines which saved reports are shown; weekly/monthly/yearly reporting counts use reporting-week starts, without treating an old multi-week range as a freshly submitted weekly report. Missing historical weeks are not invented.

## Verification

- 641 PHP regression tests (4,540 assertions) and 20 JavaScript tests passed in the final pre-release run.
- Browser checks covered all nine Creative views at 320, 768 and 1280 CSS pixels, with no whole-page horizontal overflow and no captured console errors.
- Live staff filtering, project search and pagination were exercised. Page two retained the selected historical period.
- A disposable local brief was moved through all six workflow columns using the browser, including drag-and-drop, evidence submission and the client-approval/quality gate. Independent approval and correction/rework permission cases are covered by regression tests. The disposable source/task were removed; immutable audit events were not bypassed.
- A disposable local weekly correction retained its 19–25 October dates and original table, changed only its reported work status, and retained a correction version. That fixture was removed.
- All 24 presentation sections (six sections across four periods) were exercised in the browser. Live exports returned valid PDF/Excel signatures. The four presentation variants were rendered for visual inspection and retained six pages each.
- Live source-ID reconciliation, duplicate-section removal, Creative inline source editing and original-record preservation are checked by the protected release verification. No production test records are created by those checks.

## Remaining external dependencies and data limits

File uploads remain protected and unavailable until a working server-side scanner is configured. Email delivery still depends on the mail provider and valid recipient addresses; queueing is not a delivery guarantee. Twenty-five unresolved CRM source facts remain for staff correction and were not invented. Historical narrative KPIs remain reported content until staff provide the structured, approved measurements required for verified scoring.
