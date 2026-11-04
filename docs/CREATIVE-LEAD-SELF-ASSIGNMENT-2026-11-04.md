# Creative HOD self-assignment correction

Creative HODs can assign themselves as creative and reviewer, submit evidence, approve delivery and record client-approved completion. Ordinary creatives still cannot assign or approve themselves. Evidence, planning, client approval and quality requirements remain enforced. Self-completion is explicitly labelled in the task audit note; it is not described as independent verification. Impact point changes still require an independent manager, while resaving unchanged points does not block a lead's own plan.

The planning form defaults an unassigned reviewer to the acting lead rather than the first executive in the list.

Production audit: Curtis Barnor is active Creative manager (ID 28). Seven assigned board records named Super Admin or CVO as reviewer: 211, 213, 215, 237, 239, 241, 242. Task 98 named Super Admin as completion manager. The correction updates these current routing fields, retaining historical approval actors, statuses, dates and event history. Other reviewers and historical imports with no reviewer are preserved.

Verification: 43 Creative tests (329 assertions), 20 JavaScript tests and asset build passed. Full suite: 677 tests, two failures in dated October staff-score fixtures. Both failures were reproduced against unchanged HEAD production logic; they are not caused by this patch. No production test tasks or fabricated approval events were created.
