# Operations weekly entry investigation

The reported symptom was returning to the dashboard after submitting, without seeing an entry. Production investigation read records and rendered forms without creating or approving business entries.

## Findings

- Samuel's submissions 23 and 24 (Rexona Black Friday Deployment and RedBull In-Shop Visibility Deployment) were saved at 09:03 and 09:16 on 24 November. Both await HOD review and have not been published into reporting totals.
- Salisu clocked in at 09:24 and successfully published submission 25 / register 228 at 09:31. His current publishing path therefore works. These observations do not prove that every earlier attempted submission reached the server.
- No recent application exceptions were found. Live Operations reporting/controller/form files matched the tested repository. Forms rendered for Salisu and Samuel. The inventory has 154 warehouse assets; the tested forms are below the configured 1,000-input limit.
- A reproducible loss-of-input path existed: the dashboard permits opening the weekly form without clock-in, while its POST requires attendance. The attendance guard sent that POST to the plain dashboard without retaining input or the entry destination. Several Operations profiles had no clock-in that day. This is a demonstrated failure mode, rather than an assertion that every reported attempt had the same cause.

## Changes

Weekly Operations forms display an attendance notice and a link to open Clock In in another tab when required. If a weekly save is blocked, it now returns to the correct add/edit/review form, keeps entered fields and shows an explicit “not saved” explanation. Staff still need clock-in; attendance is not fabricated or bypassed.

Successful submissions include the relevant Operations tab in the return URL. HOD entries publish directly; regular staff entries remain in their review queue until the HOD approves. No pending entries were automatically approved.

## Tests

The actual rendered HTML forms were submitted for both roles for weekly updates, projects, activations and team allocations, with a 154-item inventory fixture. Staff submissions reached pending review and HOD submissions published. The attendance middleware was separately enforced for both roles: blocked submissions retained title and narrative, wrote no entry, restored the form and successfully submitted after clock-in. Operations-focused suite: **12 tests / 293 assertions passed**.

Full application regression: **952 tests / 9,006 assertions passed**. Whitespace checks passed. No frontend asset rebuild is required for these server/view changes.

## Production verification

Deployed three runtime files with hash guards and rollback backup `cmih-operations-entry-recovery-20261124-20261124-094302`. The live attendance guard retained an unsaved verification form without allowing the write; the pre-entry notice rendered. The actual published HOD record 228 and staff pending submissions 23/24 rendered in their respective views. Protected business records were unchanged and the temporary deployment schedule was removed. No test report was created or approved in production.
