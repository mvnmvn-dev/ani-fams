# VerdeMart FAMS (Laravel)

Facilities & Administrative Management System, migrated from a custom PHP app to **Laravel 12**.

## Requirements

- PHP 8.2+ (XAMPP recommended)
- MySQL / MariaDB
- Composer
- Apache with `mod_rewrite`

## Setup

1. Import the schema if the database is empty:

```bash
mysql -u root < database/schema.sql
```

2. Copy env and set the app key (already done if you cloned this converted project):

```bash
copy .env.example .env
php artisan key:generate
```

3. Point `.env` at MySQL:

```
APP_URL=http://localhost/verdmart/public
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=facilities_admin_db
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=file
```

4. Install dependencies (if needed):

```bash
composer install
```

5. Open: [http://localhost/verdmart/public](http://localhost/verdmart/public)  
   (root `.htaccess` also rewrites to `public/`)

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
