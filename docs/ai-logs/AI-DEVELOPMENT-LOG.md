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

## 2026-11-14 — Real-game catalog refresh

### Request and approval

The user approved replacing the three fictional demo products with 30 real games, deleting the old products, reassigning and preserving the five demo offers, and using varied categories, platforms, and regions. IGDB cover references were requested, then deferred by the user until image access is available.

### Work performed

- Replaced the fictional product seed with 30 active game titles across 11 categories. Every seeded product has category mappings; the existing PC, PlayStation 5, and Xbox Series platform options and Global, Europe, and United States regions remain active.
- Reassigned the five existing demo offers to Cyberpunk 2077, Baldur's Gate 3, and Stardew Valley while preserving offer records and their sample price/compare-at values. The offers cover all three platform options and regions.
- Added seed guards that refuse to repurpose legacy offers with keys, order items, or cart items, then delete the old fictional product rows after offer reassignment.
- Removed the unused fictional SVG covers. All new products currently have `image_url = NULL`, as requested; IGDB references and attribution are deferred until credentials/image access are available.
- Removed user-facing demo-price and inventory-status labels from product cards and product details; backend inventory availability remains unchanged.
- Updated catalog/search tests and the API, database, and implementation-plan documentation.

### Validation

- Ran `php backend/bin/seed.php` twice. The second run remained idempotent.
- Database checks confirmed 30 active products, 11 used categories, five active offers across three games, three platforms, and three regions, no fictional products, no keys or orders, and no uncategorized products.
- `php backend/tests/catalog_endpoints.php` and `php backend/tests/search_and_product_endpoints.php` passed.
- PHP syntax checks, frontend production build, Oxlint, and `git diff --check` passed.

### Scope notes

- Cover image URLs are intentionally absent pending IGDB access; no credentials or runtime IGDB dependency were added.
- Demo offer prices are illustrative, and all offer inventory remains zero.

## 2026-11-15 — Expanded seller offers

### Request and approval

The user requested two or three offers for every active game, preservation of the existing five offers, varied sellers/prices/platforms/regions, and no inventory-status labels in the UI while retaining backend inventory rules.

### Work performed

- Retained the existing five offer rows and added 56 more on a clean seed, resulting in 61 active offers across 30 products. Every product has two or three offers from at least two sellers; on reruns, the seed adds offers only where coverage is missing.
- Added two seller profiles and assigned neutral seller names to all five profiles. Offers use EUR, compare-at prices, and a mix of PC, PlayStation 5, Xbox Series X|S, Global, Europe, and United States.
- Removed inventory-status text from product cards and product details; backend availability and special-deal inventory rules remain unchanged.
- Updated catalog/search tests and the current API, database, and implementation-plan documentation.

### Validation

- Repeated the seed and verified it creates no duplicate offers, including against a database already containing 61 active offers.
- Database checks confirmed 61 active offers, two or three offers per active game, multiple sellers per game, discounts, three platforms, three regions, and no missing catalog coverage.
- Backend integration tests, PHP syntax checks, frontend production build, Oxlint, and `git diff --check` passed.

### Scope notes

- No digital-key records were added. Availability remains derived from key inventory in the backend; the frontend does not show availability labels.
- Cover references remain deferred until IGDB access is available.

## 2026-11-15 — Populate homepage sections

### Request

The user reported empty best-seller, special-deal, and popular-category sections and asked to simulate content while keeping every section backend-driven. They requested a review of the documentation and a check of frontend fetching and rendering.

### Changes

- Added an idempotent backend seed for recent simulated paid-order activity, which drives the existing database rankings for best sellers and popular categories.
- Updated special-deal selection to return current active discounted offers even when no keys are seeded. The API continues to return the actual available-key count; it does not create fake inventory.
- Confirmed each homepage section uses its own frontend API request and independent loading, error, retry, and empty states.
- Updated API, database, and implementation-plan documentation and changed empty-state copy to reflect the current backend criteria.

### Validation

- Ran the seed twice and confirmed all four homepage endpoints return data: one banner and eight items each for best sellers, special deals, and popular categories.
- Verified the SPA and all four API requests through the Vite proxy.
- Backend catalog/homepage and search/product tests, PHP syntax checks, frontend build/lint, and `git diff --check` passed.

## 2026-11-15 — Homepage product carousels

### Request

The user asked for best sellers and special deals to use responsive carousels on desktop, tablet, and mobile.

### Changes

- Added a reusable product carousel using the existing product cards, horizontal touch scrolling, scroll snapping, previous/next controls, and reduced-motion support.
- Replaced the best-seller and special-deal grids with carousels. The track shows two cards on mobile, three on tablet, and five on desktop.
- Updated the architecture and implementation-plan docs to include the responsive carousel component.

### Validation

- Frontend production build and Oxlint passed.
- Verified the generated CSS includes mobile, tablet, and desktop carousel card widths and mandatory scroll snapping.
- `git diff --check` passed.

## 2026-11-15 — Authentication and inventory-independent cart

### Request

The user approved Phase 4 with the requirements that all 30 active games be addable through active offers without requiring digital-key inventory, cart prices be read from current server-side offer prices, and inactive offers be clearly handled.

### Changes

- Added session-backed registration, login, logout, and current-user routes with password hashing, opaque hashed session tokens, CSRF validation, rotation/revocation, and hashed-IP rate limits.
- Added authenticated cart endpoints and persistence that stores the selected offer ID and quantity. Cart add/update checks offer and related-record statuses but never consults key inventory.
- Recalculate cart line prices and currency subtotals from current offer prices on every read. Inactive cart offers remain visible with no chargeable line total and can be removed.
- Added account forms, protected cart route, responsive cart page, header account/cart navigation, and add-to-cart controls on product detail pages.
- Updated architecture, database, API, implementation-plan, and development-log documentation.

### Validation

- Applied the authentication rate-limit migration.
- Authentication/cart service tests cover session rotation/expiry/logout, CSRF, duplicate registration, rate limiting, cart ownership, current-price recalculation, inactive offers, and quantity bounds.
- Verified every one of the 30 active catalog products can be added to a cart through an active offer while available key inventory is zero.
- Existing catalog/homepage and search/product service checks passed.
- All backend PHP files passed syntax checks; frontend production build/type-check and Oxlint passed.
- HTTP smoke checks verified CSRF bootstrap, unauthenticated route guards, CSRF rejection, and JSON validation responses without creating accounts.

## 2026-11-15 — Allow guest cart access

### Request

The user clarified that cart browsing and editing must work before sign-in, with authentication checked only when proceeding to checkout. They selected a server-backed guest cart keyed by an anonymous cookie and merged into the account cart at sign-in.

### Changes

- Added a guest-cart cookie with an opaque random token; the database stores only its SHA-256 hash and enforces a single cart owner (account or guest).
- Made cart reads, add, update, and remove available to guests while retaining CSRF protection and current server-side offer pricing.
- Product details now allow guest add-to-cart, and the cart route/header remain available while signed out.
- Successful login/registration transactionally merges guest items into the account cart and expires the guest cookie. Quantity conflicts preserve the guest cart and return a clear conflict instead of discarding selections.
- Updated the Phase 4 API, database, architecture, and implementation-plan documentation. Checkout remains the authentication boundary for Phase 5.

### Validation

- Applied the guest-cart schema migration.
- Authentication/cart tests cover anonymous empty carts, zero-inventory guest additions for all 30 products, account merge and quantity combination, merge conflicts, current-price recalculation, and unavailable offers.
- Backend service, catalog/homepage, and search/product checks passed; PHP syntax checks and frontend build/lint passed.

