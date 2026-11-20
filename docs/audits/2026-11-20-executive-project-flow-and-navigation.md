# Executive project flow and navigation

The executive Projects tab now displays the existing connected project journey with smaller departmental stages. It uses the original records and the existing shared date, department, person and project filters. Standalone daily Mega Table tasks are excluded from the executive calendar, tracker counts and reminder candidates. Explicit assignments belonging to shared project execution plans remain project work.

## Progress and accountability

- Connected Client Relations projects show six stages: Client project, Creative brief & design, Creative sign-off, Operations handover, Execution, and Closeout. Departments recorded as unnecessary are skipped; unknown links remain visible as evidence gaps.
- Expanding a stage reveals the original briefs, native Creative stages, assignments, review/verification, handover acknowledgement, meeting and both sign-offs, HOD approval, execution, submitted reports, acceptance and verification. Requested and departmental deadlines are displayed together where recorded.
- Direct Creative briefs and independent approved Operations activations keep their actual workflows. They do not acquire imaginary Client Relations or handover stages.
- Recorded later work does not prove earlier milestones were completed. Returning Creative work for corrections restores the active design stage. Handover approval requires the recorded HOD sign-off, and reported execution remains distinct from verified closeout.
- A visible-page refresh runs once per minute and preserves expanded details. A manual Refresh progress button is available. It replaces only the journey display, leaving an open project editing form untouched.

## Navigation changes

- Published department records have an executive read-only source page, including Traffic access to permitted operational data without granting Operations editing rights. Draft sources remain blocked. Private Operations fields and raw nested execution data are excluded from that projection as appropriate.
- Project task details carry a validated local return URL back to the filtered executive view. Read-only task detail access has no clock-in redirect dependency; the existing record access policy still applies.
- Executive navigation uses normal page navigation. Collection paging cannot intercept links to a different route merely because their URLs contain page parameters.
- The outdated Merchandiser pairing-form route now resolves to its existing action.

## Verification

- 857 application tests / 7,838 assertions passed; the final project journey checks also passed after the last status adjustments.
- All 67 interface logic tests passed, including same-page versus cross-route navigation cases.
- All Blade views compiled. The literal named-route audit found zero unresolved names across application code and views.
- The exact reported task URL, `/portal/tasks/2382`, opened successfully in the authenticated live browser during investigation. Its underlying task is daily work and is now excluded from the executive calendar.
- Production deployment succeeded for all three executive profiles and all four calendar modes. All 550 generated source links in the checked year projection resolved to registered routes. The exact task detail rendered with its return link; permitted published Operations source pages rendered for Super Admin, CVO and Traffic.
- The live projection contains zero standalone daily-task events. The year snapshot has 184 open project/report commitments and 158 overdue commitments; those counts exclude Mega Table daily assignments. A zero explicit project-task count is not a claim that no weekly work exists.
- Browser inspection confirmed the six-stage Lush project layout, expandable Creative milestones, named assignee, both deadlines, successful manual refresh preserving the open stage, the correct Creative brief opening, and return to the filtered Projects view. The calendar was also checked with the IRM filter after excluding standalone daily tasks.
- The production source and built assets match the tested workspace. Live checks use existing records only; no business evidence or workflow movement is fabricated.
