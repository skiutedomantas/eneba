# GameHub Marketplace

Full-stack digital marketplace foundation using React, TypeScript, Vite, Tailwind CSS, React Router, PHP 8.3+, PDO, and MySQL 8+.

## Requirements

- Node.js and npm
- PHP 8.3+ with the PDO MySQL extension
- MySQL 8+

## Local setup

1. Create a local database and a scoped development user (replace the example password):

   ```sh
   mysql -u root -p <<'SQL'
   CREATE DATABASE gamehub CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
   CREATE USER IF NOT EXISTS 'gamehub'@'localhost' IDENTIFIED BY 'replace-this-local-password';
   GRANT ALL PRIVILEGES ON gamehub.* TO 'gamehub'@'localhost';
   SQL
   ```

2. Export the database settings in the shell where PHP will run:

   ```sh
   export DB_HOST=localhost
   export DB_PORT=3306
   export DB_DATABASE=gamehub
   export DB_USERNAME=gamehub
   export DB_PASSWORD=replace-this-local-password
   ```

3. Run migrations and load deterministic starter catalog data:

   ```sh
   php backend/bin/migrate.php
   php backend/bin/seed.php
   ```

4. Start the PHP API from the repository root:

   ```sh
   php -S 127.0.0.1:8000 -t backend/public
   ```

5. In another terminal, install frontend packages and start Vite:

   ```sh
   cd frontend
   npm install
   npm run dev
   ```

Vite proxies `/api/v1` to the local PHP server. The API readiness endpoint is `GET /api/v1/health`.

The seed data contains fictional products and reference data only; it creates no user accounts or digital keys. Do not put production credentials, session secrets, or plaintext keys in source control.