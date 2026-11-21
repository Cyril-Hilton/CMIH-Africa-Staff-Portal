# App audit and production verification

The audit covers the staff portal and the connected Brands portal, including departmental reporting, shared workflows, access boundaries, navigation, entry forms, filtering, sorting, pagination, notification handling and theme readability. Confirmed defects were repaired and deployed without creating test business records in production.

## Coverage and evidence

| Check | Result |
| --- | --- |
| Production account profiles | 106 profiles represented in an isolated database; 1204 page and permission checks passed |
| Account states | 102 active, 3 archived, 1 suspended; inactive access gates checked |
| Staff page contracts | 78 rendered page variants checked for valid internal links and form actions, duplicate IDs and unnamed buttons |
| Staff live controller actions | 531 registered actions verified after deployment |
| Connected Brands live actions | 91 registered actions verified; retained roster route rendered successfully |
| Staff regression suite | 915 tests and 8394 assertions passed |
| Brands regression suite | 455 tests and 3328 assertions passed |
| Browser logic suite | 81 tests passed |
| Spreadsheet export validation | Python regression passed in the private test environment |

The account matrix uses production role, department, manager and permission settings with isolated test identities. It does not reuse passwords or log into every person's live account. Form submissions, authorisation failures, approvals, archive behaviour and connected workflows are exercised in isolated regression tests. Production checks use existing records and read-only rendering. Mobile and tablet browser checks used 390 and 768 pixel layouts; desktop checks used the actual browser window. They include both themes, dashboard navigation, Creative tabs, HR supplier forms and Finance reporting controls.

## Repairs

- Notification polling stops after an expired session, avoids overlapping requests, backs off after outages and preserves the last valid badge data. The header explains reconnection or sign-in requirements. Notification cursors and deduplication are separated by account.
- Notification audio respects browser interaction requirements and closes completed audio contexts. CVO-role accounts now receive their financial notifications through the same permission-aware path.
- Old update entry links use the canonical task workflow. Ownership and details save correctly, staff completion requests still require manager review, and archived tasks cannot reappear through the legacy model. Missing edit and update handlers are present.
- Legacy stock entry and change routes enforce the Merchandiser Admin permission boundary. Missing roster and stock update handlers resolve to current workflows.
- The connected Brands agency-team edit route now works, validates the brand and active assignment, preserves omitted permissions and venue history, and records the change in its activity log. Tests cover forbidden and mismatched assignments.
- Department and executive breadcrumbs preserve reporting context and show the selected section. Creative and Brands tabs update their breadcrumb without a page reload.
- Day-first dates sort correctly across months and years. Equal sort values preserve row order. Saved table filters are separated by account, page and department section.
- Themes still switch when browser storage is unavailable. Finance now has readable light-mode tabs, cards, controls, chart labels and notices; HR entry disclosures and KPI warnings have readable contrast.
- Existing visible form labels are associated with their controls, including dynamically opened forms. Generated controls receive unique identifiers. Asset-label and messaging action buttons have accessible names.
- Brands clock-in test fixtures use a scheduled weekday rather than the current calendar day. Working-day rules remain unchanged.

## Live verification

All six department dashboards rendered for the Super Admin, CVO and authorised audit profiles. An ordinary staff projection retained HR and Finance privacy. Individual department pages, notification responses, breadcrumb routes and shared historical Finance dates passed. Protected business data hashes remained unchanged during the staff release checks. The live HR module showed six vendor contacts, and its entry form expanded with readable labels. Creative Calendar to Showcase switching updated the URL, content and breadcrumb together. Finance retained Year selection and displayed its independent revenue-entry action.

The browser also contains extension-injected scripts. The console's receiving-end connection error is consistent with extension messaging; an observed script link points to a Chrome extension. A separate source-less CSP eval diagnostic remains in DevTools; normal portal responses use a report-only policy that permits the app's current evaluator, so that diagnostic cannot be attributed to a failing portal route from the available browser evidence. No browser extension or security setting was disabled to suppress it. Fresh portal loads and tab changes were checked separately from those diagnostics.

## Scope of the result

This is a regression, route, permission and sampled live-browser audit, with the exact coverage above. It does not establish that every possible browser, device, input combination or external service delivery has been exercised. All registered live controller actions in the two deployed app scopes were checked; write workflows were tested in isolation to protect real records. Existing archived reporting data, vendors, assets, payroll and staff records were preserved.

The final all-departments reload showed zero console messages flagged as errors or warnings, while DevTools retained the source-less CSP issue. An earlier navigation logged one transient 503; the subsequent authenticated reload succeeded, all six panels loaded, and the server-side release checks passed. This observation is retained rather than being counted as an application calculation failure.
