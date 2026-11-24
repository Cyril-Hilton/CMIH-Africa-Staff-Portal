# Operations master-project form simplification

The circled master-project fields were retired from the shared schema used by the Operations dashboard, module, new entries, corrections and published detail displays:

- Completed On; Rework Count; Complaints; Quality Issues.
- Supporting Team; Ends On; Due On; Project Stage; Progress Percent.
- Action Due On; Is Activation; Outlet; Primary Requirement.

The project table also removes its Project Stage, Progress Percent and Due On columns. Unmarked fields remain, including Awarded On, Started On, Satisfaction Score, Brand, Location, Starts On, Project Status, RAG Status, Key Issue, Action Owner and Team Handyman Requirement. The existing supporting-staff selector and warehouse checklist remain available.

This is specific to master-project entries shown in the supplied screenshots. Separate activation schedules, fabrication deadlines, weekly-update progress and handover commitments retain their own fields. Existing project data and audit history are not deleted. New requests cannot populate the retired project fields through the reporting schema; editing an existing record preserves its previously saved values rather than overwriting them with blank or stale browser fields. Historical dates remain historical evidence, while new project entries have no requirement to supply the removed fields.

The Operations tests render forms as both HOD and regular staff in dashboard and module views, check all thirteen removed inputs and the unmarked inputs, submit a new simplified project, and correct a legacy project while preserving its saved history. The suite also exercises the real form payloads and clock-in recovery. Focused suite: **13 tests / 395 assertions passed**.

Full application suite: **955 tests / 9,262 assertions passed**. Whitespace checks passed. These config/view changes require no frontend asset rebuild.

Production deployed three runtime files, refreshed configuration, and verified all thirteen inputs are absent for Salisu (HOD) and Samuel (staff), in both dashboard and module project forms. Unmarked inputs and the separate activation/weekly-update schemas remain intact. Existing project data and protected business state were unchanged. Rollback backup: `cmih-operations-retired-fields-20261124-20261124-191503`. The temporary deployment schedule was removed. There were no existing active master-project records to render an edit form in the live check; legacy editing/history preservation was exercised in the test database.
