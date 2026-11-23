# Finance table entry workflows

Finance now has a separate table and modal entry form for each native record type: revenue, monthly targets, profitability, client invoices, supplier payables, receipts, payments, cash balances, expenses, commercial risks, payroll groups, and taxes/SSNIT. Each table paginates independently and retains shared team, date, search and review filters.

Manual client, project, account lead, dates, financial values and comments can be entered without CRM or Operations links. Optional project links retain canonical names and connected departmental tracking. Read-only connected records appear separately. Every table retains its edit, recorder, review and change-history controls.

Revenue provides a month selector and calculated achievement/status. Monthly totals remain an alternative revenue basis, preventing double-counting with CRM and additional direct revenue. Pending reconciliation invoices can be tracked before an amount is agreed; settlement requires a recorded document amount. Optional references are generated, with a stable form key preventing repeated submissions from duplicating records.

Precious publishes directly; other Finance submitters require her approval. Pending values do not enter published financial totals. Payroll remains group totals only. Existing business records are preserved; notifications remain in-app only.

Verification: full suite 937 tests / 8781 assertions passed; final focused Finance and review checks 19 tests / 368 assertions passed, including generated-reference duplicate prevention. JavaScript 81 passed and asset build passed. Live server checks rendered every tab as Precious (92) and Benedicta (119), verified separate forms, comments, monthly calculations, cash separation, optional links, privacy and unchanged protected business records. Live browser checks confirmed table-specific entry buttons and automatic revenue calculation; no financial test entries were submitted to production.
