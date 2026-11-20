# Peggy requirements completion audit

Compared the complete resupplied HR and Admin specification dated 18 November 2026 against current forms, records, calculations, role permissions, exports and workflows. The original file is now retained at `docs/requirements/2026-11-18-hr-admin-revised-structure.docx` rather than depending on a temporary WhatsApp attachment.

| Requirement | Entry, source and read-back verified |
| --- | --- |
| Shared layout | Automatic overview numbers; current registers with add/edit/details; activities beneath registers; no typed summary counts. |
| People and staff records | Existing internal staff, department/type counts, joining/exits, staff IDs, role, contacts and emergency contact; seven document types/upload; native contract, JD and Ghana Card/ID paths reused. |
| Recruitment and onboarding | Vacancies, candidate stages/interview/outcome, probation dates, open/pipeline/interview/hire/probation counts and all requested activity categories. |
| Attendance and leave | Native attendance/timesheets and leave/balances; public holidays; native exclusions; original leave/pass-out workflows. The later user instruction now archives historical attendance rather than preserving its active scoring visibility. |
| Performance and training | Approved staff/department targets, native and registered appraisals, training and improvement plans. Later user-authorized performance reset removes historical assigned KPI definitions, targets/results and awards from active scoring; scoring rules remain. |
| Employee relations and discipline | Cases, queries, warnings and grievances; responsible person, deadlines and outcomes; HR/CVO access plus the user’s explicit Ericka read-only audit exception. |
| Welfare and engagement | Birthdays/anniversaries from original staff records, welfare cases, events, feedback and native surveys without respondent identity disclosure. |
| Policies, compliance and exits | Policy document/version/approval/review, handbook acknowledgements, exits, clearance and exit interviews; assigned assets displayed and clearance blocked until recovery. |
| Office and facilities | Native office equipment reused; facility quantity/location/condition/service, utilities/cost/due date, cleaning schedule and admin support shifts; damaged native equipment included in attention counts. |
| Fleet and drivers | Vehicle status/mileage/driver/document/service, driver/licence/assignment, fuel and trip logs; native fleet requests shown separately and not treated as proof of a vehicle catalogue or actual trip. |
| Assets and equipment | Existing native office asset IDs, quantities, location, assignment, acquisition/cost/condition; current categories and assignment totals; history retained. Warehouse inventory remains its own source. |
| Supplies, vendors and communications | Native vendors plus existing directory vendor contacts, stock/reorder, purchases, communications and native staff notices/booking links. Directory contact visibility does not invent active contract status. |
| Shared activity form | Tab-specific types, description, original linked record, department/staff, HR owner, opened date, required deadline, priority, six statuses, automatic completion date, remarks/upload and change history. Overdue is calculated. |
| Home | Key source figures, open/completed/overdue activity totals, per-tab counts, required expiry/reorder/document/birthday alerts and next ten deadlines. |
| Reporting and imports | Date ranges and activity filters, Excel/PDF summary/activity exports, preview/confirm Excel imports for staff/assets/vehicles, duplicate/revision checks and retained history. |
| Access | HR manager editing, CVO read/approval, department heads restricted to their own people/attendance/performance, regular staff restricted to original self-service areas, Ericka audit access retained without editing. |

## Gaps corrected in this completion

- Vendor directory contacts now contribute to HR source coverage as well as appearing in the register. They remain descriptive evidence, not automatic KPI achievement.
- Vendor follow-up activities accept the linked existing directory contact without creating a second vendor record.
- Activities opened/due earlier but actually completed within the selected period now appear in that period and its completed total.
- Activity summary totals apply the selected activity filters consistently with the list and export.
- An HR activity awaiting CVO approval cannot be advanced or completed through an ordinary edit. Subsequent legitimate completion retains the original CVO approver and approval timestamp.
- Existing Ghana Card front uploads are recognized by the document checklist and completeness metric; no duplicate upload is required.
- Native damaged facilities are included in the faulty/damaged/under-repair count.
- Executive reminder digests respect HR/Finance recipient access. Escalations contain only overdue items permitted for that manager, with no duplicate self-escalation.
- Newly queued executive reminders are processed in the same scheduled command, rather than waiting for a later delivery run.

## Additional authorized performance reset

The user subsequently requested archiving clock-ins, punctuality and KPI records because they contribute to total staff performance. This supersedes the earlier attendance-preservation instruction. The follow-up reset selects attendance, KPI definitions/targets/results, awards, performance cases and report packs only. It does not re-archive fresh project/weekly reporting created after the original reset. Original rows and published immutable report contents remain retained.

The new eligibility boundary starts on the next working day after the reset (expected Monday 23 November 2026). Archived days create no new absence penalty or prior-cycle credit. New valid clock-ins remain recordable and measurable. Company scoring weights, departmental configuration, staff accounts, leave, payroll, assets, vendors and other operational records remain intact.

## Validation

Full suite: 899 tests / 8,218 assertions passed. This includes real source reuse, new-cycle eligibility, scoped archive preservation, pending approval, recipient privacy, immediate delivery and deduplication. Production verified all 12 HR views (home plus 11 function tabs), all six department dashboards, HR Excel/PDF exports and live reminder activation. Native read-back matched 33 staff records, 33 office asset units, six vendor contacts, 24 leave requests, 127 fleet requests and six staff notices. Native appraisals, HR surveys and new live-register rows were zero at verification; their source tables were checked rather than inventing figures.

The performance-only archive retained all 1,292 attendance rows outside active scoring, with no active KPI definitions/targets/results, awards or cases present to carry forward. Thirty active staff score calculations were verified with no prior-cycle points or cleared-period absence penalties. Content hashes across 156 unselected tables remained unchanged. New-cycle start: 23 November 2026. A dedicated hourly portal reminder job was installed because the existing scheduler targeted a different application root; live delivery and duplicate prevention passed.
