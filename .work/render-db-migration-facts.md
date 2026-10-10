# Render PostgreSQL migration facts (official docs)

- Render Postgres uses an internal URL for same-account/same-region Render services; use the **external URL** from a local Windows machine. Render Dashboard exposes both in the database Connect menu / Info page. Official source: https://render.com/docs/postgresql-creating-connecting
- Render external Postgres connections use Render-managed TLS and reject `sslmode=disable`; set `PGSSLMODE=require` to require TLS. Use the full DNS hostname, not an IP.
- Render's official backup docs say Free compute databases do not receive Render-managed logical backups or PITR. Before migration, use local `pg_dump` with the external DB connection. Official source: https://render.com/docs/postgresql-backups
- User screenshot shows database service `ani-fams-db`, PostgreSQL 18, Free plan, and a dashboard warning that it will expire Oct 28, 2026. Running a schema migration does not preserve/extend the database's lifetime.
- Local device check: `psql` is not available on PATH. Official Windows PostgreSQL installers: https://www.postgresql.org/download/windows/
- Project migration to apply after backup: `database/patches/add_revision_2026_10_updates.postgres.sql` (additive and designed to be re-runnable).
