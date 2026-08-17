# VerdeMart FAMS (Laravel)

Facilities & Administrative Management System, migrated from a custom PHP app to **Laravel 12**.

## Requirements

- PHP 8.2+ (XAMPP recommended)
- PostgreSQL
- Composer
- Apache with `mod_rewrite`

## Setup

### 1. Install PostgreSQL

Install PostgreSQL 15+ for Windows and note the `postgres` user password you set during install.

Create the database:

```bash
createdb -U postgres facilities_admin_db
```

Or in pgAdmin: create database `facilities_admin_db`.

### 2. Enable PHP PostgreSQL extensions (XAMPP)

Edit `C:\xampp\php\php.ini` and uncomment:

```
extension=pdo_pgsql
extension=pgsql
```

Restart Apache.

Verify:

```bash
C:\xampp\php\php.exe -m | findstr pgsql
```

### 3. Import schema

**Option A — one-command setup (recommended):**

```powershell
cd C:\xampp\htdocs\ani-fams
.\setup-postgres.ps1
```

Use `-ForceRecreateDb` for a clean reimport, or `-PgPassword "your_password"` to skip the prompt.

**Option B — manual:**

```bash
C:\xampp\php\php.exe database/convert_schema_to_postgres.php
"C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -d facilities_admin_db -f database/schema.postgres.sql
```

Or double-click `database/apply_schema_postgres.bat` (edit paths inside if needed).

### 4. Configure Laravel

```bash
copy .env.example .env
C:\xampp\php\php.exe artisan key:generate
```

Set `.env`:

```
APP_URL=http://localhost/ani-fams/public
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=facilities_admin_db
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password
SESSION_DRIVER=database
QUEUE_CONNECTION=sync
```

Clear config cache after changing `.env`:

```bash
C:\xampp\php\php.exe artisan config:clear
```

### 5. Install dependencies (if needed)

```bash
composer install
```

### 6. Open the app

[http://localhost/ani-fams/public](http://localhost/ani-fams/public)

## Testing

Automated tests still use SQLite in-memory (`phpunit.xml`) and do not require PostgreSQL:

```bash
C:\xampp\php\php.exe artisan test
```

Manual smoke test against PostgreSQL:

1. Log in as `admin@facilitiesadmin.local` / `Password123!`
2. Open Dashboard, Reservations, Visitors, Documents, Legal, Contracts, Retention
3. Create a reservation request as employee; approve it as staff/admin
4. Confirm no errors in `storage/logs/laravel.log`

Quick DB connectivity check:

```bash
C:\xampp\php\php.exe artisan tinker --execute="echo DB::connection()->getDriverName();"
```

Expected output: `pgsql`

## Demo accounts

Password for all: **Password123!**

| Role     | Email                         |
|----------|-------------------------------|
| Admin    | admin@facilitiesadmin.local   |
| Staff    | staff@facilitiesadmin.local   |
| Employee | employee@facilitiesadmin.local|

## Stack mapping

| Legacy            | Laravel                         |
|-------------------|---------------------------------|
| `public/index.php` routes | `routes/web.php`          |
| `Core\Auth` + RBAC | Session auth + `permission:` middleware |
| PDO repositories  | `app/Repositories` + Eloquent models |
| PHP views         | Blade (`resources/views`)       |
| `config/config.php` | `.env`                        |

Legacy source is kept under `_legacy/` for reference.
