# HR staff records

HR & Admin → Open staff register lets full-access HR managers and existing super administrators view internal staff records, including inactive staff. External merchandiser and promoter accounts remain in their separate portals.

- Click a name to inspect personal information, employment dates, contact details, next of kin, identity, pay and banking, documents, account metadata and recorded HR history.
- Export selected staff on the current page, all filtered staff across pages, or all internal staff. Choose individual fields, all fields, or the birthday preset. Record ID, name and staff ID are always included, so exported data can be imported even when a staff ID has not yet been assigned.
- CSV files open in Excel. Full record ZIP downloads contain profile fields, available profile documents and history CSVs; a document manifest identifies missing files. Passwords, authentication tokens and private messages are excluded.
- Import updates existing staff only. Export the desired fields, edit the CSV, and upload it on Import staff updates. Record ID, Staff ID or Email identifies each person; multiple identifiers must agree. Blank cells preserve existing values. Unknown/ambiguous staff, duplicate rows and invalid values block the entire import.
- Preview lists before/after values; confirmation expires after 30 minutes. Confirmation rechecks authorization and record versions and applies all updates in one transaction. A concurrent edit cancels the whole import. Roles, job levels, identifiers, file paths and history are read-only. Changes that alter privileged access are rejected.
- Use YYYY-MM-DD dates and UTF-8 CSV, up to 1,000 rows / 5 MB. Preserve leading zeros in spreadsheet phone/account/ID columns. Spreadsheet formula characters are escaped on export and reversed by the importer.

No database migration or new package is required. Full archives require PHP's ZIP extension. Build assets with `npm ci && npm run build` before deploying. Preserve production `.env`, database and storage.

Verification: `php artisan test --filter=HrStaffRecordsTest` covers access controls, selected/all/filtered exports, archive contents, document access, preview/confirmation, validation, stale record rollback and privilege protection. Existing boundary and identity tests also pass. Two pre-existing adjacent failures are the absent signed payslip route and a merchandiser salary advance test in this staff-only app; both were reproduced against original routing.
