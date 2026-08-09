# Database setup

## Clean reimport (recommended after schema updates)

1. Open phpMyAdmin → SQL tab (or MySQL CLI).
2. Run:

```sql
DROP DATABASE IF EXISTS facilities_admin_db;
```

3. Import **only** `database/schema.sql`  
   (phpMyAdmin → Import, or `mysql -u root < database/schema.sql`).

4. Do **not** run the files in `database/patches/` after a full schema import — those are for upgrading an existing DB in place.

## Default accounts

Password for all seeded users: `password`

| Email | Role |
|-------|------|
| admin@facilitiesadmin.local | Administrator |
| staff@facilitiesadmin.local | Staff |
| employee@facilitiesadmin.local | Employee |

## App `.env` reminders

- `DB_DATABASE=facilities_admin_db`
- `SESSION_DRIVER=database`
- `QUEUE_CONNECTION=sync`
