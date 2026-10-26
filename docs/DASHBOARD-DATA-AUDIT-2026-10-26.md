# Production dashboard data reconciliation — 26 October 2026

The audit compares original production weekly records with the upgraded dashboard projections. Source records are read directly; no historical values, dates or approvals were invented or rewritten.

| Department | Original weekly records | Reconciliation |
|---|---:|---|
| Brands / Marketing | 7 | All seven workstreams render with original activity names, owners, supporting roles, briefs and embedded KPI tables. |
| Client Relations | 116 | Original weekly reports remain in the searchable, paginated archive, separate from workbook-derived financial projects. |
| Operations / Projects | 6 | Six saved entries accounted for; existing narrative reports are available alongside the structured execution registers. |
| Creative | 11 | All eleven weekly source IDs have corresponding Creative workflow source mappings. |
| Finance | 5 | Four entries in the latest saved period; the fifth remains in history. Raw narratives stay restricted. |
| HR / Admin | 10 | Eight entries overlap the latest saved period; all ten remain in history. Confidential narratives require full HR access. |

Total: 155 original weekly entries. The production reconciliation reported no invalid source date ranges, missing Creative weekly mappings or displayed saved-entry count mismatches. No missing lead-staff account references were found in the original snapshot.

## Corrections

- A department with no current-week submissions opens its latest saved period with a visible date notice. Explicit user-selected periods are respected.
- Department tab navigation selects the destination department's own default period rather than retaining another department's automatic dates.
- Old Client Relations weekly reports are no longer excluded from the archive. They are not converted into invented sales figures or duplicated into the pipeline.
- Saved reports retain formatted tables, headings, emphasis, lists, supported alignment and images in the upgraded presentation. Display HTML uses an explicit safe allow-list.
- Responsibilities and KPI Commitments are permanent visible sections in Brands workstreams.
- Narrative fields use the shared CKEditor configuration. Structured dates, amounts and status fields remain structured. Creative brief editors synchronise their contents before submission.
- Private HR narratives require the existing full-HR permission. Finance narratives remain restricted to Finance and executive users.

## Distinctions retained

Legacy team-reported status is not independent approval. Imported proposal values, purchase orders, invoices, recorded revenue and collected cash remain distinct. Free-form KPI tables remain reported tables; their cells are not automatically converted into verified performance observations.

The workbook archive contains 2,560 source rows and 139 pipeline snapshots. Twenty-five uncertain target facts remain flagged for the Client Relations team. No correction was invented.

## Verification

- Automated persistence tests preserve formatted weekly content across Brands, Operations, Finance, HR and Creative entry flows.
- Rendering tests cover tables, lists, alignment, images and rejection of active content.
- Production source reconciliation covers all six departments and compares selected-period counts against displayed counts.
- Browser checks confirmed original Brands tables, permanently visible responsibilities/KPIs, department-specific period navigation and no page overflow on the tested phone layout.
- Production editor toolbar visibly includes tables, lists, superscript, subscript and image insertion.

File evidence upload screening remains a separate hosting dependency. Adding an image-link control does not enable unscreened file uploads.
