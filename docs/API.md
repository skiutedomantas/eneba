# REST API

## Conventions

- Base path: `/api/v1`; all endpoints return JSON.
- Browser calls use same-origin HTTPS and an `HttpOnly` session cookie.
- Money uses `{ "amountMinor": 1299, "currency": "EUR" }`.
- List responses use `{ "data": [...], "meta": { "page": 1, "perPage": 24, "total": 42 } }`.
- Errors use `{ "error": { "code": "VALIDATION_ERROR", "message": "...", "fields": {} , "requestId": "..." } }`. Omit `fields` when inapplicable. Never return SQL, stack traces, secrets, or key ciphertext.
- Use `400` for malformed input, `401` for missing/invalid authentication, `403` for insufficient permission/CSRF failure, `404` for missing resources, `409` for conflicts/insufficient inventory, `422` for semantic validation failures, and `429` for throttling.
- Apply bounded pagination and allowlisted sort fields/directions. Never interpolate unchecked request values into SQL.
- State-changing requests require CSRF validation. Do not enable permissive CORS.

## Authentication

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/auth/register` | Create customer account; validate unique email and password policy. |
| `POST` | `/auth/login` | Verify password, rotate session identifier, create server-side session. |
| `POST` | `/auth/logout` | Revoke current session and expire cookie. |
| `GET` | `/auth/me` | Return current user or `401`. |
| `GET` | `/auth/csrf` | Issue/refresh a CSRF token bound to the current session; login/register bootstrap must be protected against login CSRF as well. |

## Homepage

Each section has an independent endpoint and can be requested, cached, failed, and refreshed separately by the frontend.

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/home/hero-banners` | Active banners ordered by `sort_order`. |
| `GET` | `/home/best-sellers` | Products ranked by recent completed units sold. |
| `GET` | `/home/special-deals` | Active, in-window offers with available inventory and `compare_at_price_minor > price_minor`, ordered by calculated discount/value policy. Return the current price, compare-at price, and server-calculated discount percentage with each offer. |
| `GET` | `/home/popular-categories` | Categories ranked by recent completed sales. |

Each endpoint accepts optional bounded `limit` and returns a distinct response shape appropriate to the section. The SPA calls them independently rather than depending on one all-or-nothing homepage response.

## Catalog, search, product

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/products` | Product catalog. Query: `page`, `perPage`, `platform`, `region`, `minPrice`, `maxPrice`, `currency`, `category`, `sort` (allowlisted: `relevance`, `price_asc`, `price_desc`, `newest`, `popular`). |
| `GET` | `/search` | Global search. Query: `q`, optional catalog filters, `page`, `perPage`. |
| `GET` | `/products/{slug}` | Product details and active seller offers, including platform, region, currency, availability count, and compare-at price when present. |

`compareAtPrice` is omitted or null when the offer has no advertised comparison price. The API calculates any displayed discount from the compare-at and current prices, in the offer's currency; it must not trust a client-supplied percentage. An offer is eligible for the special-deals section only when the compare-at price is greater than the current price and the offer is active, in its deal window, and has available inventory.

Search implementation:

1. Normalize the query (Unicode normalization, case folding, whitespace collapse; optionally remove punctuation) and validate a practical length limit.
2. Use MySQL full-text search for exact/related term relevance.
3. If result confidence/count is low, look up candidates from the derived trigram index, aggregate gram overlap, cap candidates (for example, top 100), and score title similarity with bounded edit distance in application code.
4. Merge/deduplicate, apply active product and requested filters, rank, and paginate. Do not compute edit distance across the entire catalog.
5. Add a minimum query length and rate limit to limit expensive fuzzy queries. Measure relevance and latency before tuning thresholds.

This hybrid is practical without another service at early scale. If catalog size, language requirements, or search latency outgrow it, migrate search behind the same endpoint to a dedicated engine such as Meilisearch or OpenSearch.

## Cart and checkout

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/cart` | Return current user's cart with current offer data, stock, and recalculated totals. |
| `POST` | `/cart/items` | Add an offer and quantity; body `{ "offerId": 123, "quantity": 1 }`. |
| `PATCH` | `/cart/items/{id}` | Change quantity; validate positive bounded quantity. |
| `DELETE` | `/cart/items/{id}` | Remove an item. |
| `POST` | `/checkout` | Authenticated simulated checkout. Accept an idempotency key; server revalidates prices/currency and locks inventory. |

Checkout response on success: `201` with order ID, status, currency, totals, and purchase item summaries. It must not include plaintext keys in the initial response; users retrieve delivery through the order endpoint after the order is committed as paid.

## Orders and key delivery

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/orders` | Current user's paginated order history. |
| `GET` | `/orders/{id}` | Current user's order and item summaries; enforce ownership. |
| `GET` | `/orders/{id}/keys` | Return plaintext purchased keys only after verifying ownership and paid status. Record an access audit event; do not cache the response. |

Set key-response cache headers to `Cache-Control: no-store`; do not include keys in URLs, analytics, client logs, or error reporting. Consider allowing repeat reveal by the owning user for MVP support, with each retrieval audited. One-time retrieval adds account-recovery and delivery-failure complexity and is not assumed here.

## Admin and seller operations

Admin catalog, banner, seller moderation, offer management, and key upload endpoints are deliberately not part of the customer MVP contract. If required for a usable seeded demo, add narrowly authorized admin routes in a separate phase. Never expose bulk key data in ordinary seller/catalog endpoints.

