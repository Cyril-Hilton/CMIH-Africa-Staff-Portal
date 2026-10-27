# Department module alignment — 27 October 2026

## Implemented

- Brands, Operations and Finance now have tabbed module workspaces with reporting periods, historical navigation, shared weekly reports, whole-week copying, operational registers and the existing governed KPI definition/target/result workflows.
- Brands has POSM and document tabs, real inventory references, an optional project link and movement date, truthful warehouse balances only for tracked inventory, and no unlabelled example stock. Movement logs do not double-adjust warehouse allocation/return quantities.
- New strategy documents are stored privately and downloaded through a department-authorised route. Existing source records are retained; no historical source/date/approval is inferred.
- Operations has Projects & Activations, Handovers & Requests, Vendors & Assets and Promoters tabs. New vendor links use Campaign IDs; original task links remain explicitly labelled historical. Project lists have server search and pagination.
- Finance includes the existing jointly confirmed CRM revenue workspace and fixes CVO claim/invoice queue visibility. Ordinary staff retain access only to their own claims/invoices.
- Client Relations uses named module workspaces instead of one stacked page. Its Creative intake wording matches Brief Received. HR/Admin and Creative expose their existing KPI tools and measurement guide in the module.
- SharedModuleActivity explicitly projects safe operational facts into module and dashboard views. It does not return raw financial/source models or invent independently verified deliveries. Pending source reports can appear as generic awaiting-review entries; their private payload remains restricted.
- HR/Admin publication coverage shows approved/pending report counts while withholding unapproved/private content. Existing approved public summaries, attendance eligibility and private HR tools remain in place.
- Approved source registers paginate independently by category. Public pagination contains only allow-listed display arrays, never original source models.
- Retail KPI starter definitions and eligible approved-record calculations supplement the older campaign definitions. Existing KPI records, targets, weights and the staff 10/65/25 scoring formula are unchanged.

## Data and scoring boundaries

A recorded task/report is activity. A derived operational count/rate is a measure. A scored KPI requires an agreed definition, owner, period, target, weight and independently approved result. Rich-text tables remain formatted evidence/context, not automatically trusted numeric actuals.

The protected production snapshot contained no structured KPI definitions or target plans. This release provides the entry/review tools and starter choices; it does not invent business targets or turn historical narrative figures into approved baselines.

Revenue remains separate from expenses and repeated weekly project snapshots are not summed as cash receipts. Confidential amounts, receipts, source notes, HR cases, identity/payroll documents and evidence stay behind existing authorised access; the general dashboard uses explicit public projections.

## Validation

- 673 PHP tests / 4,865 assertions passed; 20 JavaScript tests passed. Production build passed.
- Migration rehearsal: 149 original tables compared without changes to original values; SQLite integrity OK. Two additive migrations introduce nullable source links/date fields and retain all old references.
- 96 tab/layout checks at exact CSS widths 375, 768 and 1280: no page overflow across Brands, Operations, Finance, Client Relations and HR/Admin. Creative KPI tools were also visually inspected. Light-theme form contrast checked; module accent colours adjusted for legibility.
- Local browser stock entry appeared in the shared dashboard activity using the same source row. In-module tabs, copy preview, period controls, revenue workspace, existing records, rich-text tools and role/field privacy were checked.
- Production release uses code backup, protected database snapshot, targeted additive migrations and rollback on failed route/integrity verification. No production test submissions or approvals are created for verification.

## Production verification

All 19 checked module/dashboard routes returned HTTP 200, including CVO Finance access and current/previous-week dashboards for all six departments. Integrity remained OK and all original IDs were retained across 13 checked business tables. Brands’ seven activities for 19–25 October, including formatted KPI tables, were visible inside the new module. Final filter/reset/pagination corrections also passed 30 focused regression tests.
