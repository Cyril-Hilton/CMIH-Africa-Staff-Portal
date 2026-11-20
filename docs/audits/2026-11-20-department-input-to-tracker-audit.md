# Department entry to executive tracker audit

Scope: all six departmental reporting areas, including fleet/driver records within HR/Admin. Checked source forms and validation, ownership, review/publication, original workflow history, the executive projection, and read-back in the appropriate authorized dashboard or original record details. Standalone Mega Table daily tasks remain excluded.

| Department | Entry and publication path | Audit result |
| --- | --- | --- |
| Client Relations | Shared project entry/edit form → Sales Stage and process/routing fields → manager publication → shared project and executive journey | All sales stages are selectable. Stage history now ignores unrelated note edits and identifies its timestamp as recorded in the portal. Client-reported dates remain distinct from publication dates. |
| Creative | Original brief → HOD assignment/schedule → staff progress/submission → HOD review/delivery/verification | Existing role-controlled transitions remain authoritative. The executive journey now reads recorded scheduling/progress transitions from their audit history, with the planned start displayed separately. Corrections return the work to its real active state. |
| Operations / Projects | Original weekly/module entry → HOD review → activation, procurement, execution, reports and shared controls | Added actual delivery to procurement and actual setup completion to team allocation. Required dates and actual outcomes now connect to the executive calendar. Report sharing and issue resolution use their own outcome fields. Handover planning/sourcing/setup dates use recorded transitions. |
| Brands / Marketing | Native Brands submissions and weekly workstreams → manager review → published source records | Explicit project start/end dates entered in weekly workstreams now reach the calendar as project windows. The reporting week remains activity, not a delivery deadline. Available report-sharing dates close the corresponding reported deadline. |
| Finance | Finance source form → Precious publication/review → published financial record | Existing paid/received dates now determine dated settlement outcomes. A partial payment leaves the commitment open. Commercial risk deadlines use the actual resolution date rather than a payment date. Added actual credit date to invoice/payable entries; mixed payment/credit settlement uses the last dated component. Actual outcome dates and due dates determine lateness. No extra revenue/cash entry is created. |
| HR / Admin, fleet and drivers | Native HR registers / assigned activities → authorized HR publication → permitted executive view | Connected existing vacancy, probation, appraisal, policy, exit, utility, servicing and expiry dates. Added responsible person, follow-up deadline and actual resolution to employee/welfare cases and improvement plans. Confidential register commitments are omitted for executive viewers without the existing HR audit permission. |

## Rules retained

- Both directions are required: displayed values have an entry field or automatic source; entered values remain accessible in authorized record details. Primary cards summarize rather than duplicate every field.
- Recording or publishing a stage is not proof of the date an external client event actually happened. The UI labels recorded transition dates; original entered actual dates remain separate.
- Missing historical evidence is not invented. A later stage does not manufacture timestamps for earlier stages.
- Pending department submissions remain unpublished until the existing reviewer accepts them. Existing own-entry manager publication rules remain unchanged.
- Actual completion/credit dates cannot be future-dated. A previous service date does not prove a newly scheduled service occurred.
- Optional departments remain optional. The executive view reads original data rather than creating duplicate department entries.
- This audit changes source connections and date interpretation; it does not change the agreed 10% / 65% / 25% scoring weights or grant new editing privileges.

## Verification

- 866 application tests, 7,963 assertions passed.
- All 67 interface logic tests passed; Blade views compiled.
- Input-to-projection tests cover HR entry/resolution/privacy, Operations staff entry → HOD approval → dated delivery, Finance partial/full settlement and credit dates, Brands/driver/Operations date mapping, weekly project windows, and stable stage dates after unrelated edits.
- Reverse-display checks confirm approved Brands remarks and closure dates, Finance credit dates, Operations delivery dates, and HR case outcomes remain visible after saving. HR audit viewers can read permitted outcomes without edit inputs.
- Published Operations activation details now link to the shared execution plan, exposing entered staffing, crew, schedule, compliance and task commitments without duplicate entry.
- Existing department regression tests cover CR publication, Creative transitions, Brands review, Finance review, Operations handover/warehouse controls and HR permissions.
- Deployed to production and verified all six departmental dashboards and executive role views. Live checks confirmed HR follow-up inputs, Operations actual delivery and execution inputs, and Finance credit-date inputs, without inserting test business records.
- Production resolved 582 executive source links; the original-record return route rendered successfully. No orphan Creative/Operations connections or duplicate handover publications were found. Standalone daily tasks remained excluded.
