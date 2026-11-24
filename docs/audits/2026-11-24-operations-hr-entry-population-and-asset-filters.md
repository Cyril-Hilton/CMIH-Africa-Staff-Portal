# Operations / HR entry population audit and asset filtering

## Coverage

The isolated database tests submit the actual rendered HTML forms, rather than testing only hand-written service payloads. They cover all **13 visible Operations reporting kinds**, for both ordinary staff and the HOD: create, pending review, approve/publish, display in the weekly dashboard and module, edit, and display the revised row. Retired Milestones and Campaign Tools remain retired. Separate existing tests cover handovers, execution plans, warehouse intake/review and issue/return accounting.

HR coverage includes **29 registers** (staff, office assets and all 27 configured register types), their creation, edit revisions and exact table edit links; activity creation and completion across all **11 current function tabs**, plus the retained Operations alias; and the existing tests for CVO approval, private documents, attendance, vendors, native assets, fleet connections, uploads and exports. Newly saved records are checked in the destination tables, not merely in dropdown options.

All synthetic writes and approvals were made in the isolated test database. Production checks render actual staff/HOD/HR views and query existing data without posting business entries or approving pending staff work.

## Issues reproduced and fixed

1. **HR pagination:** a successfully saved vendor could sort beyond page one. Register saves now identify the saved record in the destination and bring its row to the first page, within the existing permission scope.
2. **Stale HR activity filters:** saving a completed activity from a Not Started filter (or unrelated search) could hide it immediately. Save destinations now select the relevant period, clear contradictory filters and prioritise the saved activity before pagination.
3. **HR clock-in interruption:** the shared attendance gate could return a dashboard form to the dashboard without retaining its input. HR register/activity entries now retain their form data, open the relevant form and explicitly state that the entry has not yet been saved. Clock-in is still required and no attendance is fabricated. The user must re-select any attachment.
4. **HR form restoration:** old register data is restored only to the matching kind/record, preventing validation data for one form appearing in unrelated register forms.
5. **Asset sticker codes:** browser testing identified that generated QR sticker codes were not stored in the custom-tag field. Exact generated-code searches now resolve to the native asset ID, with a regression test.
6. **Asset filter gaps:** the embedded Operations/HR inventories lacked dedicated filters, and native type/condition/status choices did not cover all recorded values. The shared filtering now works on all matching assets before pagination; dropdowns use recorded values. HR asset entries also now capture Brand, so the filter has an entry source.

## Asset filters

Item name, asset tag or serial; brand; warehouse/current location; type/category; condition; status; person handling/assigned; activation/project/client name; assigned department. Conditions combine. Filters use native asset records, assignment, last handler, issued custody, warehouse allocations and campaign/project links. Pending requests alone do not make someone an asset custodian; returned/closed custody is excluded from the current-issued match.

The office asset manager, warehouse manager, Operations inventory and HR office asset register use the same filter service. Existing access scopes remain. Filter and period/search navigation retain selections, Clear actions reset filters, and warehouse inventory exports use the same asset constraints. Inventory is not changed by filtering. Overall stock cards retain their existing total-inventory meaning; the tables and pagination reflect the filters.

## Validation

Full application suite: **964 tests / 10,169 assertions passed**. Final focused native-form, filter and clock-recovery suite: **9 tests / 907 assertions passed**. Additional warehouse/filter checks: **14 tests / 140 assertions passed**. JavaScript: **82 passed**. Whitespace checks passed. The added controls reuse existing compiled styles and do not require an asset rebuild.

The matrix exercises current permissions and representative valid data for every entry type. It does not claim to cover every possible arbitrary value or every individual browser/device combination. Submitted Operations entries still require the configured HOD review; pending review is a saved state, not publication into approved totals.

## Production verification

Main release confirmed deployed, with 19 runtime files and no database migration. Read-only checks verified all 26 Operations role/form combinations, all 11 current HR tabs, 29 HR register kinds and all four asset-filter views. Combined filters retained their source office and warehouse items. Protected business records and full hashes of assets, vendors, HR registers/activities, Operations submissions and performance registers were unchanged. Backup: `cmih-operations-hr-assets-audit-20261124-20261124-194502`.

Browser verification confirmed the expanded Office Asset Manager controls; the sticker-code edge case was found through an actual filter submission and corrected before final sign-off.

The one-file sticker-code follow-up also passed the same live read-only checks, with business data unchanged. Backup: `cmih-operations-hr-assets-sticker-followup-20261124-20261124-195102`. Native Chrome then confirmed the printed code `CMIH-AST-00033` returns its matching asset (one row). The browser was left on the original dashboard tab. Operations inventory remains visible under its existing Operations membership scope; its embedded filter controls were verified using the actual Operations profiles in production.
