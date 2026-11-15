# Implementation Plan

## Guiding sequence

Build a thin vertical slice early, then extend it with tested business flows. Keep the approved React/PHP/MySQL stack; do not introduce a payment or search service before requirements justify it.

## Phase 0 — Confirm boundaries

- Review and approve the architecture and assumptions in these documents.
- Confirm MVP deployment origin, currencies, seller/admin scope, and how local seed users and inventory will be configured.
- Keep checkout explicitly simulated. Decide separately before adding any real payment integration.

**Exit:** agreed scope and architecture; no app stack initialized before approval.

## Phase 1 — Project foundation and data model

- Initialize React/TypeScript/Vite/Tailwind/React Router and the PHP API only after approval.
- Add environment configuration examples, PHP front controller, API response/error conventions, and same-origin local proxy configuration.
- Add MySQL migrations for identity, catalog, offers, cart, orders, encrypted key inventory, and banners; add reproducible seed data.
- Add smoke tests for API startup, DB connection, migrations, and health/status endpoint.

**Exit:** repeatable local setup and migrations; no secrets committed.

## Phase 2 — Design system, catalog, and homepage

### 2A — Establish the design system

- Add semantic CSS custom properties and Tailwind mappings for the approved palette: background `#4518AC`, sections `#311178`, cards/footer `#1F0A4D`, text `#FFFFFF`, savings `#83E916`, and region labels `#E66A8C`.
- Apply square geometry globally: zero border radius for page sections, cards, controls, badges, banners, and footer elements.
- Define shared type, spacing, border, surface, and focus styles alongside the supplied palette so pages do not invent isolated visual values.
- Establish responsive grid breakpoints with two columns on mobile, three on tablet, and five on desktop.
- Implement reusable layout and UI components: prominent navigation/search, rectangular promo banner, section container, product card/grid, price and discount display, region label, catalog controls, seller offer row, footer, and loading/empty/error states.
- Design blueprints for the remaining store flows (search, product detail, authentication, cart, checkout, confirmation, and order history) so they can use the same tokens and shell in their later implementation phases.
- Check text and focus contrast on each surface; use lime and pink as indicators on dark purple surfaces where needed for readability.

**Exit:** the token layer and shared components implement the supplied visual direction; the reusable product grid is verified at representative mobile, tablet, and desktop viewport widths.

### 2B — Catalog and homepage

- Implement PDO repositories/services for products, categories, platforms, regions, hero banners, sales summaries, and eligible special deals.
- Implement `GET /products`, `GET /categories`, `GET /platforms`, `GET /regions`, and the four independent homepage endpoints. Document every implemented endpoint and response format in `docs/API.md`.
- Connect catalog filter, sort, price range, and pagination controls to the API; keep each homepage section's loading, error, retry, and empty states independent.
- Build the responsive homepage and catalog using the shared components: large rectangular promotional banners, clearly separated sections, and dense dark product cards with artwork, prices, discounts, and region labels. Present homepage best sellers and special deals in touch-friendly carousels with responsive 2/3/5-card views and keyboard-operable controls.
- Seed 30 active game listings across varied categories, five seller profiles, and 61 EUR offers (two or three per game) with platform, region, and compare-at price variation. Add repeatable simulated recent sales records so best sellers and popular categories are populated; surface discounted offers from the backend without inventing digital-key inventory. Keep cover image references deferred until IGDB assets can be retrieved.
- Keep authentication, checkout, orders, and digital-key delivery out of this phase.
- Add request validation and focused API tests for filters, paging, empty states, and inactive records.

**Exit:** homepage sections are served independently from the backend; catalog filters are server-enforced; homepage and catalog use the approved design system and responsive 5/3/2-column grids.

## Phase 3 — Search and product comparison

- Implement `GET /search` using Unicode-normalized input, MySQL FULLTEXT natural/prefix matching, a bounded gram-index fallback, and relevance-first pagination. Apply catalog filters before scoring no more than 100 candidates.
- Maintain the derived name-gram index during seeding and provide an explicit rebuild command for catalog name/status changes. Cap fuzzy candidates at 100 and apply search request rate limiting in Phase 6.
- Implement `GET /products/{slug}` with active product details and active offers from approved sellers, including seller, platform, region, compare-at/current price, discount, and available inventory.
- Maintain multiple seller profiles and comparable offers on active game products. Product cards and comparison pages show price, platform, region, and discounts without inventory status labels.
- Connect the global search bar to a URL-backed results page with loading, error, empty, retry, filter, and pagination states. Make product cards link to detail pages.
- Build a seller comparison view where price, seller, platform, region, and discounts are visible together without inventory status labels; allow a customer to select an offer for display without implementing cart/checkout behavior.
- Document API request/response contracts and the search index lifecycle. Test exact, partial, typo, no-result, validation, filters, pagination, candidate bounds, product not-found, seller ordering, and inactive records.

**Exit:** exact, partial, and typo-tolerant queries return relevant active products within the candidate bound; product details clearly compare active seller prices, platforms, and regions.

## Phase 4 — Authentication and cart

- Implemented password hashing, opaque 14-day server-side sessions, HttpOnly cookie handling, CSRF double-submit/session binding, session rotation/revocation, and hashed-IP rate limits.
- Implemented registration/login/logout/current-user routes, account forms, and cart endpoints available to guests and signed-in users.
- Persist guest carts server-side behind an HttpOnly guest token; merge them transactionally into the account cart on successful sign-in/registration, with an explicit conflict if duplicate quantities exceed the limit.
- Cart items persist the selected offer ID; add/update validate offer and related-record status but do not require or reserve key inventory. Reads calculate prices and totals from current database offer prices; Phase 5 checkout now revalidates current prices and inventory.
- Unavailable cart offers remain visible with no chargeable line total and can be removed; invalid add/update attempts receive explicit validation/conflict responses.
- Added responsive guest/account cart UI, cart navigation/count, and per-offer Add to cart controls on product details. The cart route itself does not require sign-in.
- Checkout requires authentication before proceeding; both the frontend route and API enforce this in Phase 5.
- Validate guest/session CSRF, guest-cart persistence/merge, unauthenticated checkout protection, session expiry/logout, rate limits, cart ownership, zero-inventory add/update, current-price recalculation, unavailable offers, and quantity bounds.

**Exit:** account carts remain private, guest carts are bound to an opaque cookie, and checkout (when implemented) requires API-side authentication.

## Phase 5 — Simulated checkout and secure delivery

- Implemented authenticated checkout with a required per-user idempotency key. Offer status and current database prices are revalidated, one currency is required, and cart, offer, and key rows are locked in deterministic order.
- Selected encrypted keys are authenticated against the configured key ring before order creation. Order snapshots, simulated paid status, key assignments, and cart clearing commit in one transaction. Failures roll back writes and preserve cart contents and quantities; deadlock/lock-timeout retries are bounded.
- Kept checkout fully simulated with no card collection or payment provider. Local/test-only tooling adds visibly fictional, non-redeemable keys; normal catalog seeds and production remain free of those keys.
- Encrypted keys with Sodium authenticated encryption, a fresh nonce, and a versioned environment key ring. Decryption is restricted to the owner-checked paid-order delivery service, which sends `Cache-Control: no-store` and audits reveals without logging keys.
- Implemented paginated order history/details and checkout, confirmation, and order-history UI. Guests are redirected to sign in before checkout without losing the guest cart.
- Tested cart preservation, corrupt-key rejection, insufficient inventory, mixed currencies, current-price snapshots, concurrent final-key claims, idempotent retries, order ownership, unpaid-key rejection, and repeat delivery audits.

**Exit:** successful simulated purchases atomically consume exactly the required keys and clear the cart; failures leave it intact; retries do not duplicate orders; fictional keys are revealed only to the owner of a paid order.

## Phase 6 — Hardening and release readiness

- Enforced a 60-request/minute search limit per hashed client IP and a 16 KiB ceiling for JSON request bodies; kept the existing login/registration limits on the same fixed-window limiter.
- Ensured production session cookies remain Secure, added defensive API security headers, disabled PHP error display, and removed exception messages from logs and generic service responses.
- Added a transactional registration-to-cart-to-simulated-checkout-to-key-delivery user-journey test, plus regression checks for rate limiting, oversized JSON bodies, and inventory-aware test fixtures.
- Added practical deployment instructions for the Vite SPA, PHP-FPM API, MySQL migrations/seeds, secret management, TLS/reverse proxy, health checks, and backup/restore.
- Reviewed the complete customer flow for keyboard/focus behavior, labels, live feedback, reduced motion, and React's escaped rendering; kept production commerce limitations explicit.
- Ran focused and full backend suites, frontend production build/type-check and lint, PHP syntax checks, and deployment configuration checks.

**Exit:** reproducible deployment configuration, documented limitations, and a passing registration-to-key-delivery workflow.

## Priority order

1. Schema, catalog, inventory model, and API error/auth conventions.
2. Independently fetched homepage sections and usable product catalog.
3. Product comparison and typo-tolerant search.
4. Authentication/session/CSRF and protected account state.
5. Cart and simulated transactional checkout.
6. Encrypted digital-key delivery and order history.
7. Admin tooling, seller lifecycle, real payments, refunds, tax, and advanced merchandising only after scope approval.

## Risk-driven acceptance checks

- All prices and inventory are recomputed server-side.
- Checkout is atomic and safe to retry; insufficient inventory is an explicit conflict.
- An order cannot be read or delivered by another user.
- Key endpoints do not reveal content before paid status and do not cache it.
- Home sections load independently; a failure in one does not hide the others.
- Search fuzzy matching is bounded, measurable, and does not degrade ordinary catalog browsing.
- Search request throttling is implemented at 60 requests/minute per hashed client IP; Phase 3 bounds query size, page size, and fuzzy candidates.
- The specified palette and zero-radius geometry are applied consistently through reusable components.
- Homepage and catalog grids render two columns on mobile, three on tablet, and five on desktop.

