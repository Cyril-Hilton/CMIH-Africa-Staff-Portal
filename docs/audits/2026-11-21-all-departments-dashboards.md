# All Departments weekly dashboard visibility

The Super Admin URL `/dashboard?weekly_department=all` selected All Departments correctly but rendered only six summary links. The shared view then skipped every actual department panel because none of their department keys equalled `all`.

The All Departments selection now renders all six current weekly dashboards together in the shared reporting area: Brands / Marketing, Client Relations, Operations / Projects, Creative, Finance, and HR / Admin. The redundant summary-link cards are removed. The individual department tabs retain their focused views. The existing period toolbar and department-specific controls remain available; no historical dashboard tables are reintroduced.

All Departments has its own accessible panel containing labelled department regions. Its visibility condition allows the all-department selection, including after live region refresh. HR and Finance retain their original source permission checks, so displaying the combined page does not grant ordinary staff access to private records.

Regression coverage checks Super Admin, CVO, the authorised executive audit role, the live reporting endpoint, and ordinary staff privacy. The previous summary-card expectation now checks actual dashboard components instead.

Finance now recognises the shared dashboard reporting-period context on the live refresh endpoint as well as the initial page. Previously, an automatic refresh could default Finance to a monthly window even when the combined dashboard selected a historical week. Regression checks cover week, month and year dates through that endpoint.

Two existing leave tests used relative Saturday/Sunday request dates when run on a Saturday; they now pin their fixtures to a known Monday. No leave eligibility rules changed. Focused validation passed 74 tests / 794 assertions. Production verified all six dashboard components for three authorised executive profiles, restricted Finance/HR visibility for an ordinary staff profile, individual department views, historical-week Finance dates after live refresh, and unchanged protected business data. Only the shared presentation view and Finance date-context handling were deployed; no reporting data was reset or rewritten.

Authenticated Super Admin browser verification at the exact reported URL confirmed all six dashboards and successful asynchronous Creative/Client Relations loading. Switching to the individual Creative tab and back to All Departments restored all six dashboards without a page error. The combined page remains selected in the live browser.

Final full regression suite: 907 tests / 8314 assertions passed.
