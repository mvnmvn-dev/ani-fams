# pgAdmin 4 execution facts (official docs)

- Register the server with General (display name) and Connection (hostname, port 5432 default, maintenance database, username, password) fields; the server dialog includes SSL settings. Source: https://www.pgadmin.org/docs/pgadmin4/latest/server_dialog.html
- Query Tool supports executing arbitrary SQL and scripts; it can be opened from Tools > Query Tool or the database context menu, and the SQL editor can load a script file. Source: https://www.pgadmin.org/docs/pgadmin4/latest/query_tool.html
- Render official connection guidance: external URL is for connections from outside Render; get it through the database Connect menu and require TLS (`sslmode=require`). Source: https://render.com/docs/postgresql-creating-connecting
- pgAdmin backup flow documented via Backup dialog. Source: https://www.pgadmin.org/docs/pgadmin4/latest/backup_dialog.html


## Render credential rotation

- Render-managed credential rotation: create a new default PostgreSQL credential on database Info > Credentials; this becomes the new default. Keep old user until connected apps/services are updated and redeployed, then delete the old credential. Official source: https://render.com/docs/postgresql-credentials
- The connection host extracted from the user-provided URI (do not repeat the URI or password): `dpg-dat6i58473hc73ev3000-a.singapore-postgres.render.com`.
