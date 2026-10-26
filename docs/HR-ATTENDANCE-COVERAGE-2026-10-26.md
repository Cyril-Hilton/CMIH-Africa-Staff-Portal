# HR attendance roster coverage

26 October 2026.

The HR attendance view formerly derived its chart solely from clock-in records. That omitted staff without records and included exempt executives. Both the dashboard's HR Attendance tab and the private HR attendance route now use the same corrected service.

- All active internal staff are included, including those with zero clock-ins. CVO and Super Admin are excluded from the roster, chart and logs. Historical logs for inactive staff remain browsable, without guessing their employment end date or assigning missing days to them.
- Coverage cards show staff in view, staff with no clock-ins in the selected period, missing clock-in days and those not clocked in today. Each person has a current-day status, independently of the historical period being browsed.
- Missing-date lists are bounded by elapsed weekdays after the recorded employment start and known tracking start. Future dates, configured non-working dates and approved leave are excluded. Before 9 AM, today's clock-in is not yet due. A missing clock-in is not labelled proven absence.
- The earliest clock-in per person/day counts once for the punctuality chart. Its percentage explicitly measures on-time recorded arrival days; missing days remain separately visible. The Late / On Time filters select staff and detailed sessions without falsifying their full-period percentage.
- The attendance performance export also excludes CVO/Super Admin while retaining staff with no clock-ins. Weekly/monthly/yearly periods, name/department search, sorting, missing/no-clock-in filters and paginated detailed logs remain available. The 60-minute fixed break/lunch deduction is unchanged.
- No attendance records, accounts or reporting source values are changed by the report service. HR role protection is unchanged.

Verification: 646 PHP tests / 4,564 assertions passed, including executive exclusion, non-clockers, duplicate sessions, leave/holiday/start-date/future exclusions and current-day status while browsing history. Browser testing verified filtering, missing-date disclosure and actual viewport widths 320/768/1280 without form/document overflow or new console errors. Production checks reconcile active eligible staff IDs with the roster and check every period plus the private attendance route.
