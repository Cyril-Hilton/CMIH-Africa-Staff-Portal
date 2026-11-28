# Operations: remove Suppliers and Production & execution

Removed both table components and their Add buttons from Operations rendering. Retired register types are filtered before building table queries and checked again by the shared table template, so an older configuration or legacy table payload cannot restore these sections. Procurement and Fabrication remain available. Existing records are retained.

Validation: **12 tests / 127 assertions passed**, including staff and HOD access, standalone and weekly dashboards, older settings, direct table rendering, retained tables and retained records. Production verification passed **20 role/tab/surface checks**, confirmed both entry buttons and tables are absent, and verified the older-settings guard. Business-data fingerprints were unchanged. Three runtime files deployed; cached views/configuration refreshed. Temporary verification schedule removed.

Backup: `cmih-operations-retired-tables-20261128-20261128-083802`.
