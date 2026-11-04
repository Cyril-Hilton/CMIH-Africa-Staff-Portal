# Client Relations collaborative reporting — 4 November 2026

All active Client Relations staff can submit their own weekly project and activity updates from **Add Weekly Entry** in the weekly dashboard. The department manager reviews them in **Team Entries & Reviews**, approves publication, or returns them with mandatory correction notes. Authors receive notifications and can correct and resubmit their own returned version. Review and submission revisions are recorded in the workflow audit. Published records remain unchanged while a replacement awaits approval; stale revisions cannot overwrite newer changes. Manager-created dashboard submissions use the same queue. Existing direct manager editing remains available in the department module.

Finance confirmation remains independent. Client Relations approval publishes the operational entry, but does not confirm monetary values. Existing production records, imports and historical reporting are not rewritten by this upgrade. The new submission queue is an additive table. Unknown amounts remain blank; unresolved workbook facts are not invented.

Account-owner tabs filter all six Client Relations dashboard views, including activity-only accounts. The default source includes all portal records. Mega Table edit and delete return to the dashboard with its filters and open section preserved. Redirect destinations are restricted to the dashboard.

The application logo now uses the current portal origin rather than the storage disk's stale local address. The report-only script policy explicitly permits the existing CKEditor CDN. This preserves the security policy and supports tables, images and formatted text in the entry dialog.

## Verification

- Final focused submission, centre, existing activity-update and asset-policy tests: 19 tests / 160 assertions passed.
- Broader Client Relations, task and revenue regression pass: 65 tests / 586 assertions passed before the final asset-only changes.
- Full regression: 681 passed, 2 existing date-dependent fixture failures (StaffPerformanceAndHrReportsTest and StaffScoreGovernanceTest); not a completely green suite.
- Production build passed.
- Isolated local SQLite browser test: staff submission remained unpublished, manager approval published it, owner selection persisted across all six views.
- Visual checks at 375, 768 and 1440 CSS pixels: entry dialog contained within viewport and scrollable; editor toolbar initialized. Fresh console did not reproduce the stale-image, CKEditor-policy or dashboard 503 messages. Browser-extension messages were separate from the portal.
- Automated checks cover correction notes, resubmission, authorship, stale reviews, pending-version preservation, Finance separation and Mega Table return navigation.

No test accounts or sample entries are intended for production.

## Production verification

Released commit `21a8c3e`. Before/after existing record counts match: 98 users, 2,018 tasks, 139 pipeline projects, 24 financial register rows and 159 weekly consolidated items. The empty new submission table contains no QA records. Both new routes are present. All five active Client Relations accounts render entry/review/owner controls, and only Masana Alfah (ID 22) is a department manager. Production login and both built assets return HTTP 200. Temporary deployment and verification jobs were removed.

A follow-up logo fix preserves configured shared HTTPS storage origins, rebases obsolete localhost URLs and falls back to the bundled logo when a same-origin uploaded file is missing. Asset-origin regression coverage includes these cases.

## Owner portraits and historical rosters

Owner tabs use the existing protected staff-photo endpoint, with the normal initials image when no photo is set. Active Client Relations staff appear in the current roster. Suspended staff are excluded from current and future weekly tabs; selected historical periods retain them when dated pipeline, activity, financial or legacy weekly entries identify them. Undated account allocations do not add former staff to a new week. Existing entries and recorded ownership remain unchanged. Historical dates of suspension are not fabricated from user-profile update timestamps. New-entry owner choices remain restricted to active staff.

Focused regression after this addition: 21 tests / 172 assertions passed; production build passed. Additional cases cover current/future exclusion, past-record preservation, legacy weekly-only history and portrait URLs.

Production roster verification: five active owners have portrait URLs. Nicolette's suspended account (ID 17) is excluded from the week of 2 November 2026 and remains available for her saved week of 14 September 2026. Both cases were confirmed in the live browser. The actual logo PNGs were restored after finding Git LFS pointer files on the host; the image response now has a valid PNG signature (18,854 bytes). Deployment and verification jobs were removed. Upgrade branch synced to GitHub.
