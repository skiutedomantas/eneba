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

## 2026-11-14 — Phase 2A design system

### Request and approval

The user approved Phase 2A after requesting that all documentation be read and a proposal be presented first. All five files under `docs/` were read before implementation.

### Work performed

- Added Tailwind v4 semantic color tokens matching the supplied palette and global zero-radius styling.
- Built reusable header/navigation/search, promo banner, section panel, catalog controls, product card/grid, status message, and footer components.
- Added fictional catalog preview content with original CSS-based artwork; search, filters, and account controls remain presentation-only.
- Applied a responsive product grid with two columns by default, three at the medium breakpoint, and five at the large breakpoint.
- Removed the unused Vite starter stylesheet, which contained rounded-corner rules.

### Validation

- Frontend production build and TypeScript check passed.
- Oxlint passed.
- Temporary Vite preview returned HTTP 200; the design-token and homepage modules were served successfully.
- Source verification confirms the requested palette, global zero-radius rule, and responsive grid classes.

## 2026-11-14 — Phase 2B catalog and homepage

### Request and approval

The user approved Phase 2B after requesting a documentation-first proposal and then clarified the required endpoint documentation, truthful empty states, and exclusions. All files in `docs/` were read before implementation.

### Work performed

- Implemented PDO-backed catalog, lookup, and homepage services, with validated and bounded filters, sorting, pagination, and section limits.
- Added the product listing, category/platform/region lookups, and four independent homepage API endpoints. Documented endpoint parameters, response shapes, ordering, status behavior, and Phase 2B exclusions in `API.md`.
- Updated repeatable fictional seed data with price-bearing offers, a disabled demo seller required by offer relations, and original banner artwork. No digital keys or completed orders are seeded.
- Replaced static homepage product previews with independent API resources and per-section loading, retry, error, and empty states.
- Connected the responsive `/store` catalog filters, sorting, price bounds, and URL-backed pagination to the API.
- Added original SVG artwork and retained the approved palette and responsive 5/3/2 product grid.
- Added database-backed catalog and homepage endpoint tests and documented their invocation.

### Validation

- `php backend/tests/catalog_endpoints.php` passed, including validation, filters, sorting, pagination, lookup data, and truthful empty states.
- PHP syntax checks passed for all backend files.
- Frontend production build and Oxlint passed; `npm audit` reported zero vulnerabilities.
- Seed and migration reruns completed successfully. Database checks confirmed three active offers, one active banner, zero available digital keys, and zero paid orders.
- HTTP smoke checks covered catalog filters/pagination, invalid query handling, all lookup and homepage endpoints, and the Vite API proxy.
- `git diff --check` passed.

### Scope notes

- Authentication, global search, product detail, cart, checkout, orders, and digital-key delivery remain unimplemented.
- Best sellers, popular categories, and in-stock special deals are empty until qualifying paid sales or available key inventory exists.
- The temporary Vite smoke-test server was stopped; the pre-existing PHP server was left running.

## 2026-11-14 — Phase 3 search and seller comparison

### Request and approval

The user approved Phase 3 after reviewing the existing docs, frontend, API, and migration schema. They emphasized typo and partial-query quality and clear seller/price/region/availability comparison. Search request rate limiting was explicitly deferred to Phase 6.

### Work performed

- Implemented `GET /search` with Unicode NFKC normalization, case folding, validation, MySQL FULLTEXT natural/prefix candidates, and a bounded name-gram fallback. Catalog filters apply before title-similarity scoring, which is capped at 100 candidates.
- Added two/three-character gram generation and a rebuild service/CLI command; the repeatable seed refreshes the derived index.
- Reused catalog filtering and pagination for search results, with relevance sorting and response metadata describing the candidate limit/cap.
- Implemented `GET /products/{slug}` with active product details and active offers from approved sellers, including seller, platform, region, current/compare-at pricing, discount, and available stock.
- Added a search results page and made the global search bar navigate to URL-backed results. Added loading, retry, error, empty, filter, and pagination states.
- Made product cards link to detail pages. Added a comparison view where each offer visibly lists seller, price, platform, region, and availability; users can select an offer for display.
- Added two disabled demo seller accounts and comparable fictional offers for one product. No digital keys or completed orders were added.
- Updated API, database, and implementation-plan documentation with the implemented behavior and search-index lifecycle.

### Validation

- Backend PHP syntax checks passed.
- `php backend/tests/catalog_endpoints.php` passed.
- `php backend/tests/search_and_product_endpoints.php` passed exact, normalized, partial, mid-word partial, typo, no-result, filter, pagination, candidate-cap, active-product, offer-order, and no-inventory checks.
- Frontend production build and Oxlint passed.
- HTTP smoke checks returned the expected results for partial search, typo search, product detail, missing product (`404`), and invalid short query (`422`).
- Repeatable seed rebuilt the search index and added seller comparison offers without adding key inventory.

### Scope notes

- Search throttling remains deferred to Phase 6 as approved.
- Authentication, cart, checkout, orders, and digital-key delivery remain unimplemented.
- Product offer selection is presentational only; no inventory is reserved and no purchase action is exposed.

