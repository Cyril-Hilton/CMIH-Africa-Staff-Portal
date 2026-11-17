# Operations workbook import

The department dashboard and Operations module use the same Operations registers. The import adds records to those registers without changing earlier weekly reports, existing campaigns, warehouse stock, or the source files.

| Workbook | Worksheet coverage | Destination | Records |
| --- | --- | --- | ---: |
| Fabrication Activity | Fabrication Activity | Procurement & Execution → Fabrication Work Plan | 11 |
| Project Allocation | All ten September, October and November weekly worksheets | Weekly Workstream → Project Team Allocation | 44 |
| Activation Tracker Main | Activation Tracker | Projects & Activations → Activations | 94 |
| Activation Tracker Main | POSM Deployment | Assets & POSM → POSM deployment & returns | 28 |
| Activation Tracker Main | Dashboard, Lists (hidden), POSM Dashboard | Operations module → Source Workbooks | 0 transactional records |

All 16 worksheets are available to managers in **Source Workbooks**, including cell addresses, formula text, available cached values and source cell notes. The original workbook links retain formatting, charts and validation settings. The archive includes template rows, totals and partial records, which are not counted as separate activities.

- Activation dates are retained as reported. A calculated spreadsheet status based only on date does not verify execution. The three explicitly recorded completion statuses are retained. The report-shared flag is imported independently of completion; a share date is not inferred.
- Project allocations use the 2026 schedule year and retain the original date strings alongside resolved dates. Explicit dates and the source sheet determine the reporting range. Staff names are linked only when identifiable; original names and support roles remain available in the row.
- Twenty-six POSM rows have no deployment date, including two brand-only entries. These appear separately in Assets & POSM. They do not enter period totals and do not change stock or movement history. Source serial numbers are not warehouse asset codes.
- Fabrication entries without a planned start fall in the report's preparation week. Original dates and blanks are retained in the row.
- This is a point-in-time import. The source workbook links remain live. There is no background synchronisation, and reapplying the same snapshot does not overwrite edited portal records.

To rehearse a snapshot, save the three source XLSX files with their Drive IDs as filenames in a restricted directory, then run:

```sh
python3 scripts/extract_operations_workbooks.py /private/source-directory /private/snapshot.json --allocation-year 2026
php artisan operations:import-workbooks /private/snapshot.json
```

Review the counts, source hashes and owner matches before using `--apply`. Review changes to workbook row identities before importing a later revision, particularly allocation and fabrication rows whose references are derived from their original worksheet positions.
