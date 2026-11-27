# Finance partial entry support — 27 November 2026

All user-entered fields in every Finance table form are optional. This includes revenue/targets, invoices/payables, receipts/payments/cash balances, expenses, commercial risks, payroll and statutory records. Both new entries and edits accept partial details. Optional fields retain type/range checks when supplied; contradictory amounts and invalid dates are still rejected.

- Removed browser-required fields and server-required business values, including conditional requirements for document amount, payment/credit date, earned date and revenue confirmation checkbox.
- Blank actuals remain unknown, rather than saved as zero. Missing actuals do not display a false percentage or "Below target" result. Gross profit stays unmeasured until revenue and both cost components are recorded.
- Required internal record metadata is assigned automatically when omitted: entry description, source reference, Finance owner, reporting period, work status and audit-event reason. Actual payment/earned dates are never generated. Existing references and metadata are preserved on partial edits.
- Staff entries still require Precious's review. Manager entries still publish directly. Access permissions, edit conflict checks, payroll privacy and audit events remain in place.

Validation: full application suite **993 tests / 10,603 assertions passed**; final focused Finance/KPI suite **23 tests / 707 assertions passed**, including the additional blank-field display regression. All Finance types accept partial new entries and later updates. Every Finance tab's entry forms were checked for browser-required fields.

Production verification passed: **24 partial entry saves and 24 later updates**, covering all 12 entry types as both Finance staff and manager. All seven Finance entry tabs rendered without required entry fields. Staff review and direct manager publication were preserved. Temporary records, audit events and notifications were rolled back; existing business data was unchanged.

Production backup: `cmih-finance-optional-fields-20261127-20261127-161102`. Temporary deployment schedule removed after verification.
