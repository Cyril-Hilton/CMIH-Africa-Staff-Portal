# Database Operations

## Why phpMyAdmin May Look Empty

phpMyAdmin only manages MySQL or MariaDB databases. If the portal is configured to use SQLite, the live database is a file on the server, usually under the Laravel `database` directory or whatever `DB_DATABASE` points to. That SQLite file will not appear as a normal database inside phpMyAdmin.

If production should be manageable from phpMyAdmin, provision a MySQL/MariaDB database in DirectAdmin, update the production environment to use the `mysql` connection, run migrations, and import the current data. Do not delete the SQLite file until a verified export/import and login test are complete.

## SQLite Backup

The app now includes a scheduled backup command for SQLite:

```bash
php artisan db:backup-sqlite
```

It creates timestamped backups in:

```text
storage/app/backups/database
```

The scheduler runs it daily at `02:10` Africa/Accra time and keeps the latest 14 backups by default. If SQLite write-ahead logging files exist, the command also copies the `-wal` and `-shm` sidecar files.

## Recommended Production Setup

Use MySQL/MariaDB for long-term production if multiple admins need phpMyAdmin access, web-host backups, and easier server-side inspection. SQLite can still work for a small staff portal, but backups must be treated as application file backups rather than phpMyAdmin database backups.
