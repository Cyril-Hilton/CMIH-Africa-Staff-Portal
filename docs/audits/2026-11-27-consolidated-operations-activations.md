# One consolidated Operations Activations table

Removed the three Overview breakdown tables (Activations by Brand, Location and Project Lead). The existing Activations register under Projects & Activations is the single record table; no rows were copied or duplicated. Overview retains its four charts and statistic cards.

The consolidated register now has simultaneous Brand, Location and Project Lead filters, combined with full-result search, an optional advanced column/value filter, sorting and pagination. Owner sorting is labelled Project Lead. The new constraints apply before pagination, and the activation export snapshot reads the same query, so Excel/PDF requests use the matching selection and remain subject to HOD approval. Other Operations table controls remain unchanged.

Validation: **25 tests / 428 assertions passed**, covering both standalone Operations and weekly dashboard rendering, the absence of duplicate Overview tables, exactly one activation-table container, combined filtering with data beyond page one, ordering, matching export rows, and the existing approval/import/attendance/reporting flows. No business data is written by this change.

Production verification pending receipt.
