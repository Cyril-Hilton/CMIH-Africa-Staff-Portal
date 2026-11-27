# Department KPI card audit — 27 November 2026

Scope: Brands & Marketing, Client Relations, Operations & Projects, Creatives, Finance, and HR/Admin. Audited the shared achievement/gap cards, supporting calculations, source coverage, published-result restrictions, individual applicability, manager review responsibility, and shared-data redaction. Reviewed native Brands success-rate and Client Relations focus-rate calculations as well.

## Findings and corrections

1. **Record counts were being confused with measured outcomes in the presentation.** A recorded entry is evidence of reporting; it does not establish a target, measured actual, or successful delivery. All six departments now distinguish measured achievement (including a genuine zero) from an unmeasured outcome, no source records, an unassigned category, and pending manager review. Unmeasured cards show a dash and an explicit status instead of an apparent 0/100 result or a numeric progress meter. Shared projections retain the measurement flag after confidential amounts are removed.
2. **A known target with no actual was counted as measured.** Missing actuals retain zero credited weight, but no longer increase the measured-result count or appear as recorded zero outcomes. This also makes individual completeness checks reflect whether a result actually exists.
3. **Operations retained obsolete KPI sources.** Removed Master projects, Suppliers, Production, Milestones, Reports/Approvals/Risks from the automatic Operations KPI categories/calculations and pending-area mapping. The three remaining categories use current dashboard entry types. Stored historical data is retained. Current activation completion uses recorded end dates/statuses; fabrication timeliness uses due/delivered dates. Future/unconfirmed deadlines and cancelled activations do not enter the due-outcome denominator.
4. **Finance source achievement omitted its separate revenue-target form.** Automatic Finance revenue achievement now uses approved separate targets when present. Consolidated totals take precedence over underlying direct/CRM amounts; without consolidated totals, direct revenue and Finance-confirmed CRM projects are combined once. Missing actuals remain unknown. Targets do not grant achievement by themselves.

## Live source findings before correction

- Operations: nine approved weekly updates, five in progress and four planned; no activation targets/actuals or staffing plans. These entries should display as recorded but **not measured**, rather than implying nine completed outcomes or a measured zero.
- Brands: both measured outcomes and incomplete measures exist. Existing source-unit grouping, missing-result denominators, and capped achievement credit remain in use. Brief success rate already uses won/received briefs, including pending briefs in the denominator.
- Client Relations: published projects, revenue evidence, and decided-deal conversion are separate. Finance confirmation remains required for revenue credit. Focus rate already uses unique engaged accounts / portfolio accounts.
- Creatives: brief planning, first submission timeliness, and verified quality remain separate. Missing manager grades are excluded from an individual's quality obligations; due but unsubmitted work can still legitimately produce zero.
- Finance: four invoices were recorded in the current selection, without a due collection measure in that selection. Invoice entries alone do not establish collection achievement.
- HR/Admin: live registers and activities provide evidence across eleven areas. Static register counts alone do not establish timely delivery. Recorded document/attendance/target coverage obligations remain measurable, including genuine zero coverage.

## Validation

- Regression coverage across every configured department for missing measurement, real actual zero, safe shared projections, obsolete Operations sources, due/cancelled/future activation handling, fabrication delivery, Finance separate targets and consolidated-total precedence.
- Existing scoring regression coverage checks approved vs pending results, partial actuals retaining the full target denominator, unequal weights, lower-is-better zero targets, no assigned work, automatic attendance, manager responsibility, and direct correction links.
- Final full suite: **991 tests, 10,432 assertions passed**. JavaScript suite: **82 tests passed**. Production verification: **12 dashboard checks** (executive plus departmental staff view across all six departments), **31 KPI areas**, no numeric meters on unmeasured cards, and shared financial-source redaction verified. Existing business data was unchanged. Operations’ nine weekly updates now display `not_measured`.

This audit does not create targets, invent actuals, approve pending work, or alter archived business records. Outstanding source-data gaps require the responsible team to enter/confirm their actual business data.

Production backup: `cmih-department-kpi-audit-20261127-20261127-150702`. The temporary deployment schedule was removed after verification.
