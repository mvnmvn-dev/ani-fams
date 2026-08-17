# Database setup

## Quick setup (Windows)

From the project root:

```powershell
.\setup-postgres.ps1
```

This creates the database (if needed), generates `schema.postgres.sql`, imports it, and configures `.env`.

## Clean reimport (recommended after schema updates)

1. Drop/recreate the PostgreSQL database:

```bash
dropdb facilities_admin_db;
createdb facilities_admin_db;
```

2. Generate the PostgreSQL-compatible schema:

```bash
php database/convert_schema_to_postgres.php
```

3. Import **only** `database/schema.postgres.sql`
   (pgAdmin → Run script, or `psql -U <user> -d facilities_admin_db -f database/schema.postgres.sql`).

4. Do **not** run the files in `database/patches/` after a full schema import — those are for upgrading an existing DB in place.

## Default accounts

Password for all seeded users: `Password123!`

| Email | Role |
|-------|------|
| admin@facilitiesadmin.local | Administrator |
| staff@facilitiesadmin.local | Staff |
| employee@facilitiesadmin.local | Employee |

## App `.env` reminders

- `DB_CONNECTION=pgsql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=5432`
- `DB_DATABASE=facilities_admin_db`
- `DB_USERNAME=postgres`
- `SESSION_DRIVER=database`
- `QUEUE_CONNECTION=sync`

## PHP extension (XAMPP)

Enable the PostgreSQL PDO driver in `C:\xampp\php\php.ini`:

```
extension=pdo_pgsql
extension=pgsql
```

Restart Apache after editing `php.ini`.

## Legacy MySQL patches

Files under `database/patches/` are **MySQL-only** upgrade scripts. For PostgreSQL, always do a clean reimport of `schema.postgres.sql` instead of running patches.
