# AI Development Log

## 2026-11-14 — Initial architecture proposal

### Request

Propose a realistic full-stack digital marketplace architecture and create the architecture, database, API, implementation plan, and AI development log documents. The user explicitly requested no React/PHP initialization, no dependency installation, and approval before implementation.

### Work performed

- Reviewed the repository's development instructions and existing documentation layout.
- Recorded a same-origin React SPA and PHP REST API design backed by MySQL.
- Defined the logical schema and relationships for users/sessions, catalog/offers, encrypted digital-key inventory, carts/orders, and homepage content.
- Specified independent homepage services, catalog/search/product/auth/cart/checkout/order endpoints, and a practical bounded MySQL full-text plus trigram search strategy.
- Defined the simulated checkout transaction and key-delivery security boundaries.
- Created an approval-first, phased implementation plan.

### Decisions and rationale

- Keep all required technologies and avoid dependencies or services not requested.
- Use opaque server-side sessions with secure cookies and CSRF protection for the browser API.
- Use integer minor-unit amounts and server-calculated prices.
- Encrypt digital keys at the application layer, store only ciphertext in MySQL, and decrypt only for authorized order delivery.
- Use a transaction and row locks for inventory assignment; idempotency protects checkout retries.
- Fetch homepage sections through four independent endpoints so failures and caching are isolated.
- Use MySQL full-text search plus a bounded trigram fallback for typo tolerance at MVP scale; preserve the endpoint boundary if a dedicated engine becomes necessary.
- Document payment processing as simulated; do not imply that this design implements real payment collection.

### Validation and constraints

- No application code was written.
- No dependencies were installed.
- No React or PHP project was initialized.
- Documentation content has not yet been reviewed or approved by the user; implementation should wait for that approval.

## 2026-11-14 — Compare-at offer price

### Request

After approving the architecture, the user requested a nullable `compare_at_price_minor` field on offers for special-deal calculations and asked that documentation be updated without initializing the application or installing dependencies.

### Changes

- Added the field and its meaning to the offer schema in `DATABASE.md`.
- Defined deal eligibility and calculation behavior in `DATABASE.md` and `API.md`.
- Documented that the API returns the comparison price and computes the displayed discount; the browser does not supply or control the percentage.

### Validation and constraints

- Documentation only; no application code or dependencies were changed.
- No React/PHP application was initialized.

## 2026-11-14 — Phase 1 foundation

### Request and approval

The user approved Phase 1 and npm after reviewing the proposed sequence. The user had requested that the full documentation folder be read before any changes; all five documentation files were read before work began.

### Work performed

- Scaffolded the React/TypeScript/Vite frontend and added Tailwind CSS and React Router.
- Added a dark-theme startup page, browser routing, Tailwind's Vite plugin, API proxy configuration, and frontend build/lint scripts.
- Added the framework-free PHP API bootstrap, PDO connection, JSON response helper, health endpoint, and request IDs.
- Added the initial MySQL schema migration, idempotent migration runner, and repeatable fictional catalog/reference-data seed.
- Added the root environment example, ignore rules, and local setup instructions.
- Added `compare_at_price_minor` to the migration. Corrected the database documentation so one order item may reference multiple digital keys (one per purchased unit), while each key row can be assigned to only one order item.

### Validation

- Frontend production build and TypeScript check passed.
- Frontend lint passed.
- Frontend npm audit reported zero vulnerabilities.
- PHP syntax checks passed for all backend PHP files.
- Created the local `gamehub` database, applied the migration, and loaded the seed; rerunning the migration and seed completed safely.
- Direct API health request and Vite-proxied health request both returned `200` with database status `ok`; an unknown API route returned the expected JSON `404`.
- The schema check confirmed `offers.compare_at_price_minor` and `digital_keys.order_item_id`; starter seed data contains three products and three categories.

### Scope notes

- No application features beyond the Phase 1 startup foundation were implemented.
- No real users, offers, or digital keys were seeded.
- The local API and Vite smoke-test servers were temporary.

## 2026-11-14 — Load root environment file

### Request

The user reported that `/api/v1/health` returned an internal server error and requested a Composer-free root `.env` loader that supports blank assignments, surrounding whitespace, and quoted values.

### Changes

- Added a small environment-file parser and invoked it from the shared backend bootstrap, so HTTP requests and CLI migration/seed commands use the same configuration.
- Load only the repository-root `.env`; a missing file is allowed when the process environment is configured.
- Support blank values, `export KEY=VALUE`, single/double-quoted values, and trimmed whitespace. Preserve process environment values (including explicitly blank ones) over `.env`; do not execute PHP, expand variables, or interpret inline comments.
- Added explicit `503` handling for absent database configuration in the health endpoint and documented `.env` behavior in the root README.

### Validation

- Reproduced the reported `500` before the change.
- Unit-style parser checks passed for blank, trimmed, quoted, `=`-containing, exported, and process-overridden values.
- PHP syntax checks passed; bootstrap reports all database variable names as set without printing values.
- The running API health endpoint now returns `200` with database status `ok`.
- Frontend build and lint remain passing.

