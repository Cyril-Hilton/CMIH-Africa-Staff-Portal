# Shared Power BI report links

User clarified that adding links must be open to all staff, superseding the initial administrator-only proposal.

`/portal/power-bi` and the Power BI Reports navigation item are available to active internal staff in all six departments. Any staff member can add a report name and restricted Power BI URL. All staff can open active links in a new tab. Staff can edit/archive/restore their own links; administrators, CVO and Super Admin can manage all entries. Archived links cannot be opened through the portal. Revisions protect against overwriting newer changes; additions and updates are audited and use confirmation dialogs.

Only HTTPS app.powerbi.com report/group/sharing-link paths are accepted. Public Publish-to-web links, other hosts, embedded URL credentials and nonstandard ports are rejected. Microsoft determines report permissions; saving a link does not grant report access, verify its existence, or synchronise portal data. New tabs use noopener/noreferrer. No actual company report URL has been supplied or added to the preview. No Microsoft tenant configuration was changed.

Migration: 2026_10_21_000027_create_power_bi_report_links. Applied only to the local preview. No production deployment.

Verification: full portal suite **521 tests / 3,523 assertions passed**; focused tests **4 / 50 assertions passed**, covering additions across all departments, safe redirect/new-tab markup, owner/admin management, archive/restore, stale updates, duplicate URLs and unsafe URL rejection. Build passed. Browser checked ordinary Creative staff access and the responsive add form. Screenshot `/tmp/cmih-power-bi-staff-links.png`.
