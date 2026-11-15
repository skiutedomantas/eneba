# Deployment Guide

This is a same-origin, educational marketplace deployment: the Vite-built React app is served as static files, and Nginx forwards `/api/v1/` to PHP-FPM. MySQL or PostgreSQL remains on a private network. Do not expose the PHP source tree or root `.env` through the static web root.

> **Scope limitation:** this project has simulated payments, fictional non-redeemable key seeding for non-production environments, and no admin/seller operations or production key-upload workflow. It is suitable for a private educational preview, not real commercial sales or real digital keys.

## 1. Runtime requirements

- Node.js `20.19+` or `22.12+` on the build machine; Node is not required on the web server.
- PHP `8.3+` with `pdo_mysql` or `pdo_pgsql`, plus `sodium`, `intl`, and `mbstring`.
- MySQL `8+` using InnoDB and `utf8mb4` (default), or PostgreSQL `14+`.
- Nginx with PHP-FPM and a TLS certificate.
- A private network path from PHP-FPM to MySQL. Do not expose MySQL to the public internet.

Check PHP extensions with:

```sh
php -m
```

Confirm `PDO`, the selected PDO driver (`pdo_mysql` or `pdo_pgsql`), `sodium`, `intl`, and `mbstring` are present. The same extensions must be enabled for the PHP-FPM runtime and CLI migration runner.

## 2. Provision MySQL

Create a dedicated database using the collation expected by the migrations:

```sql
CREATE DATABASE gamehub
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;
```

Create a dedicated application account and restrict it to this database. Run schema migrations with a separate deployment account that has the required DDL privileges; the long-running PHP-FPM account should need only the DML privileges required by the application. Use your secret manager or deployment platform to supply credentials—do not put passwords in shell history or commit them.

Set `DB_DRIVER=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for both the migration process and PHP-FPM. Prefer a local socket/private network and enable database TLS when traffic leaves the host or trusted private segment.

### PostgreSQL alternative

For PostgreSQL, provision PostgreSQL 14 or newer and a dedicated database/user with the needed schema and runtime privileges. Set `DB_DRIVER=pgsql`, use the exact server host and database settings, and configure `DB_SSLMODE` (`require` or stricter verification when supported by the deployment). The default PostgreSQL port is `5432`; `DB_SSLMODE` defaults to `prefer`. PostgreSQL migrations are loaded from `backend/database/migrations/postgresql/` and use advisory locking plus one transaction per migration. MySQL remains the default; do not dual-write to both engines.

For a fresh database, apply migrations and seeds with the selected environment:

```sh
DB_DRIVER=pgsql php backend/bin/migrate.php
DB_DRIVER=pgsql php backend/bin/seed.php
```

Changing the driver only selects the schema/query dialect; it does not copy existing MySQL data. Follow the [database cutover plan](./DATABASE.md#safe-cutover-from-mysql) and rehearse a data conversion, search-index rebuild, sequence reset, key-ring check, and rollback before moving existing records.

## 3. Configure PHP secrets

The bootstrap reads the project-root `.env` when present. Keep it outside every web-served directory, restrict it to the PHP runtime/deployment owner (for example, mode `0600`), and exclude it from version control. Alternatively, inject variables through a protected process environment supported by PHP-FPM; ensure the FPM pool actually exposes those variables to PHP.

Configure at least:

```dotenv
APP_ENV=production
SESSION_COOKIE_SECURE=true
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gamehub
DB_USERNAME=gamehub_app
DB_PASSWORD=<provided-by-secret-manager>
# PostgreSQL only:
# DB_SSLMODE=require
KEY_ENCRYPTION_KEYS='{"1":"<base64-encoded-32-byte-secret>"}'
KEY_ENCRYPTION_KEY_VERSION=1
```

Generate a new key on a trusted machine with:

```sh
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

Put the result in your secret manager, not in source control or a deployment log. The key ring is a JSON object mapping version numbers to base64-encoded 32-byte keys. Back it up separately from MySQL, retain old versions while encrypted rows use them, and test key recovery before rotating. Production forces session cookies to `Secure`; TLS must terminate at the trusted reverse proxy, and the proxy must forward the original HTTPS status correctly.

The PHP API disables displayed errors and emits generic error responses with request IDs. Keep PHP error logging enabled, restrict log access, and avoid logging request bodies, cookies, authorization headers, or query strings. In particular, do not log key-delivery response bodies.

## 4. Migrate and seed

Run deployments against a staging database first. Apply migrations with the deployment account:

```sh
php backend/bin/migrate.php
```

For a private educational preview, load the catalog and its simulated sales data:

```sh
php backend/bin/seed.php
```

That seed does not add digital keys. The fictional-key seed is limited to local, development, and test environments and intentionally refuses production:

```sh
APP_ENV=development php backend/bin/seed-fictional-keys.php
```

Do not use the fictional-key seed in production. Real key ingestion and customer sales require separately designed, authorized operational workflows that are not included here. Run the application test scripts only against a disposable test database; never point them at production data.

## 5. Build the React application

On the build machine, from the repository root:

```sh
cd frontend
npm ci
npm run build
```

Publish only `frontend/dist/` as the static web root. The current API client uses the same-origin `/api/v1` path, so no browser-visible database or encryption settings belong in Vite environment variables. Rebuild and republish the frontend for each release.

## 6. Nginx and PHP-FPM example

Adjust paths, the PHP-FPM socket, TLS certificate paths, and host name for the target system:

```nginx
server {
    listen 443 ssl;
    server_name marketplace.example.com;

    root /srv/gamehub/frontend/dist;
    index index.html;
    autoindex off;

    ssl_certificate     /etc/letsencrypt/live/marketplace.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/marketplace.example.com/privkey.pem;

    add_header Strict-Transport-Security "max-age=31536000" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
    add_header Content-Security-Policy "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; img-src 'self' https: data:; style-src 'self'; script-src 'self'; connect-src 'self'" always;

    location ^~ /api/v1/ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /srv/gamehub/backend/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param REMOTE_ADDR $remote_addr;
        fastcgi_hide_header X-Powered-By;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

The PHP-FPM pool must be able to read the application code and protected environment configuration but must not be able to modify deployment files. Ensure PHP-FPM's `post_max_size` is larger than the API's 16 KiB JSON-body limit, while retaining the application's stricter request limit.

If another trusted proxy or CDN is in front of Nginx, configure it to accept forwarded client-address headers only from known proxy addresses. Rate limits use `REMOTE_ADDR`; do not trust arbitrary client-supplied `X-Forwarded-For` values.

Use the same HTTPS origin for the SPA and API. Do not add permissive CORS rules. Before enabling HSTS, verify that the hostname will remain HTTPS-only. Keep the Nginx access-log format free of query strings if search terms should not be retained.

## 7. Smoke checks and ongoing operations

After deployment, verify the API and database health:

```sh
curl --fail --silent --show-error https://marketplace.example.com/api/v1/health
```

Then exercise registration, login, cart updates, simulated checkout, order history, and key reveal in staging with fictional inventory. Check the browser network panel for same-origin requests, secure/HttpOnly/SameSite cookies, and absence of secrets in responses or logs. A key response must remain `Cache-Control: no-store`.

Back up the selected database and the encryption key ring as separate protected assets. For example, use a protected MySQL client option file rather than putting passwords on the command line:

```sh
mysqldump --defaults-extra-file=/secure/backup-client.cnf \
  --single-transaction --routines --triggers gamehub > gamehub.sql
```

For PostgreSQL, use a protected `PGPASSFILE` or platform secret mechanism:

```sh
pg_dump --format=custom --file=gamehub.pgdump gamehub
```

Restore a PostgreSQL custom-format backup with `pg_restore` into a provisioned, isolated database. Keep the engine-specific migrations and environment settings aligned with the application release.

For restoration, provision an isolated database, restore the dump with a privileged deployment account, install the matching encryption key ring (including every version referenced by stored keys), deploy the matching application build, then verify health and order/key delivery in staging before switching traffic. Keep backups encrypted and access-controlled; a database backup without its key ring cannot restore digital-key delivery.

Rate-limit counters are operational state. A daily maintenance job may remove expired rows:

```sql
DELETE FROM auth_rate_limits
WHERE window_started_at < UTC_TIMESTAMP(6) - INTERVAL 2 DAY;
```

For PostgreSQL:

```sql
DELETE FROM auth_rate_limits
WHERE window_started_at < CURRENT_TIMESTAMP - INTERVAL '2 days';
```

Do not run schema changes, seed scripts, or test suites against production without a separately reviewed release procedure and verified backup.
