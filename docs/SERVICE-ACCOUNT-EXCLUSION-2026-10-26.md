# Shared presentation account exclusion

26 October 2026. User request: exclude CMIH Staffs Presentation Account from records and calculations.

The existing shared presentation identity (cmihstaffs@cmih.africa) is marked `is_service_account`. This is durable metadata, independent of its displayed name. It does not delete the identity, change its credentials or remove historical business records.

The shared human/staff query excludes service accounts from staff headcounts, HR staff records, attendance roster/chart/logs/exports, award rosters, team scoring, staff selectors, reminders and staff notifications. Additional direct active-staff selectors in reporting, appraisal, CRM and Creative flows honour the same flag. Service accounts cannot be selected as new performance owners/reviewers.

Direct attendance scoring has zero expected workdays for the account and marks it excluded. Direct staff performance scoring has no measured score/activity. Team scoring rejects service accounts even if a caller supplies them explicitly. The task real-work scope excludes tasks assigned to service identities from individual, department and agency statistics; it retains genuine work assigned to a real person even when the shared account created it. Stored tasks/attendance remain intact. Auto-clock handling and date tracking do not introduce attendance for service accounts.

The flag is not mass assignable through the profile update fields. Existing user permissions and login credentials remain unchanged. Account administration can retain the technical identity; it is no longer treated as an employee.

Verification: 649 PHP tests / 4,587 assertions passed. Tests cover roster/log exclusion, stable exclusion after renaming, preserved stored rows, excluded personal/team calculations, accurate task totals and genuine work created on a person's behalf. Migration rehearsal checked 149 production-derived tables with no changes to any original fields; only the new metadata column is added. Production checks reconcile the named account's flag, every attendance period, staff queries and leaderboard membership while checking original stored data against a protected immediate backup.
