# CMIH umbrella audit — 22 October 2026

## Final local verification — 22 October 2026

The remaining local browser checks were completed using a fresh, isolated Chrome profile through Playwright. This does not attach to the user's signed-in Chrome session. Earlier notes saying browser checks could not be performed are superseded by this section.

### Results

- Staff full suite: **538 tests / 3,649 assertions**.
- Website full suite: **372 tests / 2,096 assertions** (72 pre-existing PHPUnit annotation deprecations).
- Brands/Merchandiser full suite after final browser fixes: **451 tests / 3,317 assertions**.
- Creator active application/presentation suite: **118 tests / 1,078 assertions**. Unrouted inherited Staff tests are not counted as Creator coverage.
- Total across those suites: **1,479 tests / 10,140 assertions**. Shared confirmation JavaScript: **12 passed**. Three frontend builds and all four applications' template compilations passed.
- **288 desktop/390px browser checks** on valid app routes: 88 Staff checks, 20 public website checks, 8 initial Brands checks, 74 Creator role/workspace checks and 98 Brands/Merchandiser role/panel checks. No runtime failures, failed requests, broken images or page-wide overflow in these final checked surfaces.
- Additional browser workflows verified personal offer/payment pagination, independent queue paging, drawer background isolation, Escape/focus restoration, nested confirmation cancellation and an actual Excel download. The workbook reopened with bold headers, A2 freeze panes and filters. Three repaired Merchandiser chart pages were checked for actual Chart instances and visually inspected; an absence of console errors alone was not treated as proof they rendered.

### Final defects repaired

- Brands admin enrollment, overview and activity logs expanded beyond phone width. Scoped grid, sizing and scroll corrections were retested in all four admin tabs.
- The Merchandiser chart library was deferred while inline chart constructors ran immediately. This caused errors on Perfect Store, User Performance and Price/Promo, and silently skipped some other charts. The local library now loads before dependent inline initializers. Browser checks confirm real chart rendering.
- Generic password/OTP client sign-in led Merchandiser clients to the wrong application. Both now return to their client dashboard. Same-origin brand portal return links remain supported; external, protocol-relative, backslash and control-character destinations are rejected. Regression checks cover external redirect rejection, existing portal-specific returns and password/OTP client routing. No real SMS was sent.
- Creator personal welcome content is confined to Overview so tabbed lists are reached sooner. Row drawers isolate background controls, preserve scrolling and give open confirmation dialogs priority for Escape. Creator readiness review and assignment writes now have explicit confirmation prompts.
- Agency task performance totals now aggregate in the database using only each task's latest submission, preserving approved points without loading every task and submission. Regression verifies old submissions do not multiply counts or points and unapproved work earns no credit.

### Evidence and repeatable checks

Saved evidence: `storage/app/private/umbrella-audit-2026-10-22/`. Latest JSON results, final screenshots, workflow/chart proof and test logs are retained there; before-fix captures are labelled in evidence-notes.md. Repeatable isolated browser regressions are in Creator Universe `tests/Browser/creator-workspaces.cjs` and Brands Portal `tests/Browser/brand-merchandiser-workspaces.cjs`, with local-only setup notes.

### Deferred and not certified

Deployment remains on hold. The 25 unresolved CRM source facts remain flagged for genuine Client Relations correction in production. Production email/SMS/scheduler credentials, real camera/GPS hardware, live external services and production configuration were not certified by local fixtures. The earlier sealed security scan still has partial directory-wide source coverage; these functional checks do not turn that report into an exhaustive source/dependency audit or guarantee every possible interaction/device is error-free.

## Current closeout status — 22 October 2026

This section supersedes earlier progress notes below. The Creator Universe workspace redesign and confirmed audit fixes are implemented locally. This is not a claim that every line of every application or dependency has been audited.

### Latest automated checks

| Application | Automated result | Scope |
| --- | --- | --- |
| Staff Portal | 538 tests / 3,649 assertions | Full suite |
| Public Website | 372 tests / 2,096 assertions | Full suite; 72 existing annotation deprecations |
| Brands / Merchandiser Portal | 451 tests / 3,317 assertions | Full suite |
| Creator Universe | 118 tests / 1,078 assertions | Active Creator/Universe/presentation suite; unrelated inherited routes excluded |

Three Vite builds passed. All 12 shared confirmation policy and event integration tests passed. Creator directory scale regression uses 3,000 synthetic creators and verifies bounded pagination and filtering. No scale fixture was added to live data.

### Creator Universe redesign

Agency work is split into nine selectable sections. Creators, screenshots, campaigns, assignments, briefs and responses use searchable, paginated tables and row actions. Screenshot images load only when inspected. Forms open in a reusable drawer with keyboard focus handling and return to the list. Tasks, requests and benefits use compact tables; creation forms collapse when unused. Creator and brand users have separate tabbed workspaces. Support keeps its own role-specific profile and availability flow. Private contact information is not added to brand discovery. Existing account, review and assignment permissions remain server-enforced.

The final pass reduced oversized header/section spacing so staff reach working controls sooner. This replaces the previous card-heavy management interface rather than adding another management page.

### Browser evidence

Staff: 26 destinations, ordinary sign-in for six departments, six shared departmental panels at 390px, archive/restore preservation, suspended login rejection, manager impact save, centered confirmation and cancel.

Creator: agency sections, creator personal sections, brand sections and support account/calendar/notifications; screenshot review, request/reply, task submission/approval, brand brief submission and agency response, drawer close/escape, single sign-out confirmation. Brand brief and promoter submissions used synthetic local records only. Recent Creator console inspection returned no errors.

Brands: collaboration tabs, permission-sensitive controls, selected activation/date filters; all seven agency sections verified at 390px with document width 390px. Ordinary promoter login, disabled out-of-window clock-in, rejection of empty activity with visible feedback, successful synthetic activity save. Promoter mobile overflow fixed and retested at 390px. Fixed the observed date-filter URL constructor error; the corrected filter navigates with activation and from date preserved.

Merchandiser: administration destinations, 13 field panels, paired/unpaired states, leave submission, supervisor empty-state accuracy and mobile client views. Website: ten pages, mobile navigation, successful synthetic contact form and corrected local staff-login handoff.

A browser PDF download was rendered and inspected. The final browser Excel download was reopened: A2 frozen header, A1:E7 filter, bold navy headers, wrapped 60-point rows. Files use test data. Screenshot evidence: `/tmp/cmih-umbrella-browser/creator-directory-final.png` and earlier captures in that folder. Browser network/runtime monitoring was used during the exercised flows; this does not establish all possible routes are error-free.

### Security review and limits

The saved Codex Security report is complete as a report with explicitly **partial directory-wide coverage**. Three validated findings from the original source snapshot were fixed locally and regression-tested: profile authority approval bypass, foreign-brand assignment revocation and export-permission bypass. Report: `/Users/cyrilhilton/.codex/state/plugins/codex-security/scans/CMIH-AFRICA/unversioned_20261022T140646Z_gnw3iknp/report.md`. The scan retains unreviewed duplicated controllers/templates/dependencies in coverage; it is not a production penetration test.

A later browser-led code review also removed reward redemption mutation from generic field activity logging. Regression checks same-brand and foreign-brand references remain unredeemed. Counted activity now requires positive units/actions or conversions; stock issues require explanatory notes. The dedicated reward validation workflow remains responsible for redemption. This later fix is covered by the final 448-test Brands run and is outside the original sealed snapshot report.

Security tool-reported usage is 140,770,846 total tokens with **partial attribution**, six missing threads and a rollout-unavailable warning. This includes cached input and should not be treated as an isolated scan cost.

### Remaining coverage limits

- Directory-wide source review is partial; unreviewed areas remain in canonical coverage.
- Real camera/GPS, production email/scheduler, external credentials and production configuration were not exercised with real people or services.
- No claim of exhaustive every-button coverage, or a bug-free production environment, is made.

### User-approved deferrals

The 25 unresolved CRM source facts remain flagged for genuine Client Relations corrections in production. Deployment and production verification remain on hold. No production changes or real outbound messages were made during this continuation.


Historical progress notes follow. Current status and remaining coverage limits are stated above. Local changes only; no production deployment is authorised.

## Confirmed fixes and checks

- Display names no longer grant HR or manager access. Target-user checks prevent staff administrators from changing protected executive accounts. Manager edits cannot promote subordinates into executive or HR privileges.
- People & Teams combines directory, staff records and account access using role-specific tabs. Original page links remain valid.
- Suspended staff retain task ownership and historical weekly work, including historical team filters.
- Messenger, claims and salary-advance dialogs now remain within their Alpine state scope. Browser retests show no related runtime exceptions.
- Messenger mobile conversation navigation has a back-to-conversations control.
- Public events without a registration address link to the event detail section, rather than an unrelated external event site.
- Decorative application emojis have been replaced with reusable SVG icons. Native select options and notification text remain text. Staff-uploaded profile photos remain preferred; sample users without uploads use a fallback.
- Password inputs start masked before scripts load, have one accessible visibility control, and no longer show two eye buttons on Brands sign-in.
- Brands activity-log export now has an actual protected Excel endpoint and respects the displayed filters.
- Creator Universe sign-in chooser identifies access as CMIH Staff; the access rule already permits active CMIH staff.

## Automated evidence

Before the latest presentation/export changes:
- Staff: 526 tests, 3,578 assertions passed.
- Website: 362 tests, 2,044 assertions passed; 72 legacy PHPUnit annotation deprecations.
- Brands: 434 tests, 3,221 assertions passed.
- Creator Universe active app: 107 tests, 947 assertions passed.

After icon, password, and Brands export changes:
- Staff: 528 tests, 3,585 assertions passed.
- Website: 364 tests, 2,051 assertions passed; 72 PHPUnit deprecations.
- Brands: 438 tests, 3,236 assertions passed.
- Creator Universe active app and presentation: 109 tests, 954 assertions passed.

Creator Universe also carries inherited legacy Staff/merchandiser tests for routes not present in its dedicated app. Those are not represented as passing dedicated-app coverage.

## Browser evidence and outstanding coverage

Screenshots and page observations: `/tmp/cmih-umbrella-browser`.

- Staff: 26 observed sidebar destinations initially checked for rendering, overflow, console/runtime and failed network responses. Claims/advances retested after fixes. Normal staff People directory and organogram visually inspected.
- Website: 10 public pages initially checked on desktop; event CTA correction still needs its final browser retest, plus mobile and contact-flow checks.
- Brands: public brand carousel and brand detail, admin sign-in, all four administration tabs inspected. Field/retail/merchandiser roles and mobile checks remain.
- Creator: creator login, required-photo onboarding, profile photo upload/save, workspace and tasks inspected using a fictional local account. Remaining creator workflows and agency/brand/support roles remain.

Initial rendering checks do not mean every button or complete workflow has passed. Continue role-by-role and record final results.

## Work still to finish

- Manager-controlled impact weighting is implemented and regression-tested; continue remaining role and presentation checks.
- Finish all role, mobile, interaction and export workflow checks and resolve issues found.
- Confirm remaining source-template and layout consistency across apps.
- Re-run final builds/tests after subsequent changes.

## Deliberately deferred

- 25 genuine CRM source facts require Client Relations confirmation in production. Do not guess them or include unresolved values in scoring.
- Production rollout/deployment and real production verification remain on hold at the user's instruction.

## Latest continuation

Manager-governed delivery impact is implemented in the Staff Portal, with independent authorization, an audit reason, immutable weights after final verification, and preserved historical ratings when managers are later suspended. Individual and department award scores consume the weighted results. Latest complete Staff run: 532 tests / 3,611 assertions passed.

The local browser successfully saved a clearly labelled DEMO ONLY 20-point rating on preview task 11 after testing Cancel and Continue. The Brands export returned HTTP 200 with the XLSX MIME type; the in-app browser did not report a completed download event, so a saved browser download is not claimed.

Browser use became disabled during the continuation. Remaining visual/role workflow checks are outstanding; code checks continue.

## Browser resumed / public contact repair

Browser access resumed successfully. Mobile public navigation opens correctly. Contact form was discovered to be an inert form without an action or named inputs. Added a throttled, validated POST handler, reply email, honeypot, accessible labels, error preservation and explicit success/failure feedback. Career Opportunities now opens the contact form with Career Enquiry selected instead of a dead anchor. Local synthetic submission visibly showed success with mail routed to the array test transport; no real email was sent. Preview sessions were changed from array to file so redirect feedback can be inspected faithfully.

## Continued browser workflows

- Creator preferences saved, creator request submitted, agency received and replied with a status change. Synthetic task assigned to Ama Demo, evidence submitted, reviewed and awarded 10 points. All data is isolated, labelled DEMO ONLY; no production activity or real outbound email.
- Found and fixed duplicate Creator sign-out confirmations; browser verified one dialog then successful sign-out. Added explicit confirmation on task and screenshot review decisions.
- Website news cards no longer advertise a nonfunctional Read More action; events contact action accurately says Ask About an Event.
- Website image resizing now checks available memory and safely retains original assets when resizing would exceed the budget. Low-memory regression passes.
- All six shared departmental tabs were activated at 390px; one panel visible at a time and no document-wide horizontal overflow. Screenshots saved per tab.
- Merchandiser admin navigation: distributors, schedules, SKU catalog, forms, field team, supervisors and assets loaded without console errors.
- Merchandiser field navigation: all 13 panels opened without console errors. Found real unpaired-account server error; replaced with setup guidance and tested active paired and unpaired local accounts. Removed duplicate password toggle on Merchandiser login across shared templates.
- Synthetic field leave application saved. GPS timeout is an expected local device limitation, visibly reported. Initial label now says Checking GPS rather than falsely claiming a connection.
- Explicit regression now verifies CVO archive/restore preserves work and suspended users cannot sign in.

Full suites after these additions are being rerun. Remaining role/workflow/security coverage is not represented as complete.

## Access and export follow-up

- Independent offline security review validated a nullable-profile-field privilege escalation. Fixed approval interception for missing/null prior values and for HR self-edits, and made self-service job title read-only because it participates in role checks. All three routed applications pass 17 profile tests / 82 assertions each. Creator active account updater does not expose these authority fields.
- Brands cross-brand assignment revocation now requires assignment.brand_id to match the authorized route brand. Report exports enforce explicit can_export permission. BrandPermissionBoundaryTest proves foreign-brand revocation is blocked, legitimate revocation works, denied export remains denied, and an authorized export is a styled valid XLSX.
- Shared formatted-download middleware is now used in Website, Brands and Creator as well as Staff. Import CSV support remains available; exported CSV labels changed to Excel. Existing export tests inspect actual workbook cell content rather than treating a ZIP file as CSV.
- Latest full runs before final empty-state/label touch-ups: Staff 538 / 3,649; Website 372 / 2,096 (72 legacy annotation deprecations); Brands after export authorization 445 / 3,277; Creator active tests 109 / 954. No claim that inherited Creator legacy routes have passing coverage.
- Confirmed ordinary sign-in and no executive navigation for all six departments, recorded in staff-role-tour.json. Merchandiser client reporting has four working view destinations; no console errors or document overflow at desktop width.
- No-audit client reporting now shows Awaiting audits / N/A rather than a fabricated failing score, and distinguishes no observations from healthy observed SKUs. Improved contrast of the small client report labels.

Remaining: finish final visual/export file checks, remaining Creator brand/support and Brands collaboration workflows, and finish security coverage/report. No deployment.


## Personal workspace continuation — 22 October 2026

- Personal creator offers and open calls now use compact tables with reusable detail drawers, and independent 12-record pagination. Rewards and payment records also use compact tables and pagination. Benefit offers retain the assigned/active/completed status restriction.
- Profile and growth tabs no longer load unrelated offers, campaigns, payments, audience history or dashboard summaries. The overview retains complete assignment counts; a regression with 30 assignments checks that pagination does not reduce the summary total.
- Fixed the dead Career Opportunities footer link in Staff, Brands and Creator to open the configured public website contact page with Career Enquiry selected.
- Latest Creator active suite: **115 tests / 1,052 assertions passed**. Staff and Brands presentation checks: **2 tests / 7 assertions each passed**. Changed Creator files pass whitespace checks.
- Browser control is unavailable in this continuation (only Node REPL is exposed, without a browser connection). These latest visual changes have **not** been visually retested; earlier browser evidence does not certify them. No attempt was made to bypass unavailable browser access.
- Full umbrella source coverage remains partial. This continuation does not close that gap or claim exhaustive UI coverage. Production and the 25 CRM source facts remain deferred as instructed.


## Reporting and operations scale follow-up — 22 October 2026

- Brand/campaign reports no longer load the creator directory unnecessarily or run count/name queries for each result. Grouped queries preserve report filters, sorting, downloads and full totals. A 40-brand/campaign regression checks pagination, counts and a fixed query budget below 12 SELECTs.
- Agency Operations now loads only ten queue preview records and at most twenty failed deliveries per mail category before selecting the oldest twenty overall. Full task, request and mail failure counts are calculated independently.
- Unanswered requests are selected using their latest message in the database; the preview no longer loads every historical conversation message to filter requests in memory. Regression checks agency-replied conversations are excluded and 25 review items / 22 unanswered requests retain full counts while previewing ten.
- Latest active Creator suite: **117 tests / 1,069 assertions passed**; changed files pass whitespace checks.
- Browser/computer tools were searched again and none are currently exposed. Latest UI visual checks remain unperformed; no production deployment, real mail or CRM source correction was attempted.
