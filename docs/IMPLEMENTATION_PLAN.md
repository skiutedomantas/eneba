# Implementation Plan

## Guiding sequence

Build a thin vertical slice early, then extend it with tested business flows. Keep the approved React/PHP/MySQL stack; do not introduce a payment or search service before requirements justify it.

## Phase 0 — Confirm boundaries

- Review and approve the architecture and assumptions in these documents.
- Confirm MVP deployment origin, currencies, seller/admin scope, and how demo users and inventory will be seeded.
- Keep checkout explicitly simulated. Decide separately before adding any real payment integration.

**Exit:** agreed scope and architecture; no app stack initialized before approval.

## Phase 1 — Project foundation and data model

- Initialize React/TypeScript/Vite/Tailwind/React Router and the PHP API only after approval.
- Add environment configuration examples, PHP front controller, API response/error conventions, and same-origin local proxy configuration.
- Add MySQL migrations for identity, catalog, offers, cart, orders, encrypted key inventory, and banners; seed reproducible demo data.
- Add smoke tests for API startup, DB connection, migrations, and health/status endpoint.

**Exit:** repeatable local setup and migrations; no secrets committed.

## Phase 2 — Catalog and homepage

- Implement product/category/platform/region repositories and catalog filter/sort/pagination endpoints.
- Implement the four independent homepage endpoints and independent frontend loading/error states.
- Build shared product cards, offer/price display, responsive home sections, and catalog pages.
- Add request validation and focused API tests for filters, paging, empty states, and inactive records.

**Exit:** homepage sections are served independently from the backend; catalog filters are server-enforced.

## Phase 3 — Search and product comparison

- Implement full-text search, normalized query handling, bounded trigram fallback, and relevance sorting.
- Add product detail and active multi-seller offer comparison.
- Build global search UI with loading, no-results, and error states.
- Test typo cases, filter interactions, pagination, and candidate caps against seeded products.

**Exit:** exact and near-match queries return relevant active products without unbounded scans.

## Phase 4 — Authentication and cart

- Implement hashed passwords, opaque server-side sessions, CSRF protection, session rotation/revocation, and rate limits on authentication.
- Add registration/login/logout/me flows, protected route UX, and authenticated cart endpoints.
- Recalculate cart prices and totals from current server-side offers on every read/checkout.
- Test unauthenticated access, CSRF failures, session expiry/logout, and cart quantity validation.

**Exit:** private data is bound to the authenticated user; frontend route guards complement but do not replace API authorization.

## Phase 5 — Simulated checkout and secure delivery

- Implement transactional, idempotent checkout with locked available keys, purchase snapshots, and consistent order state.
- Implement order history/details and owner-checked key retrieval after successful simulated payment only.
- Encrypt keys at ingestion, restrict decryption to delivery service, disable key-response caching, and add audit logging.
- Test concurrent attempts against low inventory, retries with the same idempotency key, rollback paths, and unauthorized key access.

**Exit:** no key can be assigned twice or disclosed before a committed paid order.

## Phase 6 — Hardening and release readiness

- Review all endpoints for authorization, validation, SQL parameterization, CSRF, output encoding, and rate limiting.
- Verify responsive accessibility, loading/empty/error states, and complete order journey.
- Add structured server logging without secrets, production error handling, backup/restore notes, and operational configuration.
- Run targeted suites, then the full test/build/type-check/lint commands that exist in the initialized project.

**Exit:** reproducible deployment configuration, documented limitations, and verified customer workflows.

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

