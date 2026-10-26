# Client Relations / Finance revenue confirmation

26 October 2026. Revenue only; no expenses added.

## Behaviour

- Client Relations keeps entering projects and period results in its existing module. All non-financial details remain visible.
- New actual revenue is marked **Awaiting Finance Confirmation**. It contributes neither confirmed revenue nor revenue achievement until independently reviewed.
- Active Finance staff confirm the reported amount or propose a correction with a required source/reason note. The original reported amount remains unchanged until Client Relations accepts a correction.
- A correction requires the account owner, Client Relations manager or authorised executive to accept or return it. No person may provide both confirmations.
- Previously recorded actuals retain their values. No historical Finance approval is invented: Finance and Client Relations must explicitly agree.
- Changing revenue, target, owner, client, brand, project or source period resets approval and advances the revision. Stale submissions are refused; restoring an earlier amount does not resurrect approval.
- Review events retain original/proposed/accepted values, actors and timestamps. Notification outbox entries alert the department that must act next.

## Shared presentation

Client Relations and Finance each have a revenue tab on the existing weekly dashboard, visible to internal staff. It supports weekly/monthly/yearly periods, previous/current/next navigation, search, sorting, confirmation filters and pagination. Account owner, client, brand, project, reported amount, proposed correction, confirmed amount and review history appear together.

Repeated weekly pipeline declarations are snapshots, not cash transactions: the latest snapshot per client/project in a selected period contributes once. Account target results are reviewed separately and never added again to project revenue. Missing revenue is not inferred from proposals, POs, invoicing or collections. Forecast/PO/invoice/collection amounts keep their existing distinct labels. Sensitive source contacts and private notes are excluded from shared projections.

The CRM business review, achievement calculations, public weekly financial snapshots and exports use only jointly confirmed actual revenue. Reported values remain available in pending entries and editing forms.

## Operations requests

Salisu Ibrahim was emailed the live Vendor Printing Request Form and register URLs, entry steps, PDF instructions and access guidance at his existing portal email address. Gmail returned a sent-message ID.

Operations HOD/line managers, CVO and Super Admin can create, edit, preview and download printing requests. Ordinary staff and unrelated managers remain denied. A previously missed clock-in gate now honours CVO executive access without requiring attendance. Request shortcuts are available in Operations module and weekly reporting views, in addition to the existing sidebar item.

## Verification

- Automated revenue tests cover two-party agreement, corrections, disputes, legacy values, stale revisions, self-approval, suspended actors, period filtering, duplicate snapshot exclusion, zero revenue and account/project separation.
- Executive printing tests enforce the real clock-in middleware and verify list/create/edit/save/preview/PDF for CVO and Super Admin; negative access tests remain.
- Browser: accepted a local correction from GH₵ 100 to GH₵ 90, verified confirmed total and original-value history; tested monthly/yearly filters and actual CSS viewport widths 320/768/1280 without overflow. Temporary source records removed after testing; audit events retained.
- Additive migration rehearsal on a protected production-derived copy: 149 original tables checked, no original values changed, SQLite integrity OK.
- Production checks use read-only route requests and compare all original values in weekly reports, CRM projects, targets, financial register, allocations and archive rows against the immediate pre-release backup. No test financial confirmations are created in production.

Final local verification: 641 PHP tests / 4,540 assertions, 20 JavaScript tests, production build and diff whitespace checks passed.
