# REST API

## Conventions

- Base path: `/api/v1`; all endpoints return JSON.
- Browser calls use same-origin HTTPS. Authenticated endpoints (implemented in a later phase) use an `HttpOnly` session cookie.
- Money uses `{ "amountMinor": 1299, "currency": "EUR" }`.
- Paginated lists use `{ "data": [...], "meta": { "page": 1, "perPage": 24, "total": 42 } }`. Bounded section and lookup lists use `{ "data": [...], "meta": { "limit": 8 } }`.
- Errors use `{ "error": { "code": "VALIDATION_ERROR", "message": "...", "fields": {} , "requestId": "..." } }`. Omit `fields` when inapplicable. Never return SQL, stack traces, secrets, or key ciphertext.
- Use `400` for malformed input, `401` for missing/invalid authentication, `403` for insufficient permission/CSRF failure, `404` for missing resources, `409` for conflicts/insufficient inventory, `422` for semantic validation failures, and `429` for throttling.
- Apply bounded pagination and allowlisted sort fields/directions. Never interpolate unchecked request values into SQL.
- State-changing requests require CSRF validation. Do not enable permissive CORS.

## Phase 2B implementation scope

Phase 2B implements the health check, product catalog and reference-data lookups, and four independent read-only homepage endpoints below. Authentication, registration, login/logout, cart, checkout, orders, digital-key delivery, search, and individual product detail remain future-phase contracts and are not implemented in Phase 2B.

An empty result is a successful response (`200`) with `"data": []`. The API does not invent sales or inventory to populate a section. In particular, best sellers require completed orders in the recent period, and special deals require available digital-key inventory. The current demo seed intentionally creates neither.

## Health

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/health` | Verify API and database connectivity. |

Successful response (`200`):

```json
{
  "data": {
    "status": "ok",
    "database": "ok",
    "requestId": "..."
  }
}
```

Database configuration or availability errors return `503` with the standard error envelope.

## Authentication

Reserved for the authentication phase; not implemented in Phase 2B.

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/auth/register` | Create customer account; validate unique email and password policy. |
| `POST` | `/auth/login` | Verify password, rotate session identifier, create server-side session. |
| `POST` | `/auth/logout` | Revoke current session and expire cookie. |
| `GET` | `/auth/me` | Return current user or `401`. |
| `GET` | `/auth/csrf` | Issue/refresh a CSRF token bound to the current session; login/register bootstrap must be protected against login CSRF as well. |

## Homepage

Each section has an independent endpoint and can be requested, failed, and refreshed separately by the frontend. Responses currently use `Cache-Control: no-store`; cache policy can be revisited for public catalog data independently of this API contract.

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/home/hero-banners` | Active banners ordered by `sort_order`. |
| `GET` | `/home/best-sellers` | Products ranked by completed units sold in the last 30 days. |
| `GET` | `/home/special-deals` | Active, in-window offers with available inventory and `compare_at_price_minor > price_minor`, ordered by discount percentage descending. |
| `GET` | `/home/popular-categories` | Categories ranked by completed units sold in the last 30 days. |

Each accepts optional `limit` (integer, range `1..20`; default `3` for hero banners and `8` for other sections). Each endpoint has its own response and frontend loading/error state.

`GET /home/hero-banners` response:

```json
{
  "data": [
    {
      "id": 1,
      "title": "Explore your next adventure",
      "imageUrl": "/images/hero-banner.svg",
      "targetUrl": "/store",
      "sortOrder": 1
    }
  ],
  "meta": { "limit": 3 }
}
```

`GET /home/best-sellers` response:

```json
{
  "data": [
    {
      "id": 1,
      "slug": "starlight-odyssey",
      "name": "Starlight Odyssey",
      "description": "A fictional role-playing adventure.",
      "imageUrl": null,
      "unitsSold": 12,
      "lowestOffer": {
        "offerId": 1,
        "priceMinor": 1899,
        "compareAtPriceMinor": 4999,
        "currency": "EUR",
        "platform": { "code": "pc", "name": "PC" },
        "region": { "code": "global", "name": "Global" },
        "availableQuantity": 0,
        "discountPercent": 62
      }
    }
  ],
  "meta": { "limit": 8 }
}
```

`GET /home/special-deals` response:

```json
{
  "data": [
    {
      "offerId": 1,
      "product": {
        "id": 1,
        "slug": "starlight-odyssey",
        "name": "Starlight Odyssey",
        "imageUrl": null
      },
      "priceMinor": 1899,
      "compareAtPriceMinor": 4999,
      "currency": "EUR",
      "platform": { "code": "pc", "name": "PC" },
      "region": { "code": "global", "name": "Global" },
      "availableQuantity": 4,
      "discountPercent": 62
    }
  ],
  "meta": { "limit": 8 }
}
```

`discountPercent` is calculated by the API from integer minor-unit values as a whole percentage; the client does not supply it. Only offers with inventory greater than zero qualify for this endpoint.

`GET /home/popular-categories` response:

```json
{
  "data": [
    { "id": 1, "slug": "role-playing", "name": "Role-playing", "unitsSold": 12 }
  ],
  "meta": { "limit": 8 }
}
```

For best sellers and popular categories, only orders with `status="paid"` and `paid_at` within the rolling 30-day period count. With no matching paid sales the endpoint returns `{"data":[],"meta":{"limit":8}}`.

## Catalog, search, product

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/products` | Product catalog with bounded filters, sorting, and pagination. |
| `GET` | `/categories` | Active category filter options. |
| `GET` | `/platforms` | Active platform filter options. |
| `GET` | `/regions` | Active region filter options. |
| `GET` | `/search` | Global search. Query: `q`, optional catalog filters, `page`, `perPage`. |
| `GET` | `/products/{slug}` | Product details and active seller offers, including platform, region, currency, availability count, and compare-at price when present. |

`GET /products` query parameters:

- `page` (default `1`, positive integer)
- `perPage` (default `20`, integer `1..48`)
- `platform`, `region`, and `category` (active code/slug values)
- `currency` (three-letter code, default `EUR`)
- `minPriceMinor`, `maxPriceMinor` (non-negative integer minor units in the selected currency)
- `sort`: `newest` (default), `price_asc`, `price_desc`, or `popular`

The catalog lists active products. `lowestOffer` is the least expensive matching active offer in the selected currency and optional platform/region filters; it is `null` when the product has no matching active offer. An offer with no available keys is still represented with `availableQuantity: 0`. Price filters apply to the selected offer after currency/platform/region filters. Products are ordered deterministically within each sort. `popular` uses paid units from the last 30 days and breaks ties by newest product.

Paginated response:

```json
{
  "data": [
    {
      "id": 1,
      "slug": "starlight-odyssey",
      "name": "Starlight Odyssey",
      "description": "A fictional role-playing adventure.",
      "imageUrl": null,
      "lowestOffer": {
        "offerId": 1,
        "priceMinor": 1899,
        "compareAtPriceMinor": 4999,
        "currency": "EUR",
        "platform": { "code": "pc", "name": "PC" },
        "region": { "code": "global", "name": "Global" },
        "availableQuantity": 0,
        "discountPercent": 62
      }
    }
  ],
  "meta": { "page": 1, "perPage": 20, "total": 3, "sort": "newest", "currency": "EUR" }
}
```

Invalid, unsupported, or out-of-range query values return `422 VALIDATION_ERROR` with a `fields` map. An empty page returns `200`, an empty `data` array, and pagination metadata.

Lookup endpoints accept no query parameters and return active options. Categories are ordered by `sort_order` then name; platforms and regions are ordered by name:

```json
{ "data": [{ "id": 1, "slug": "action", "name": "Action" }], "meta": { "limit": 100 } }
{ "data": [{ "id": 1, "code": "pc", "name": "PC" }], "meta": { "limit": 100 } }
{ "data": [{ "id": 1, "code": "global", "name": "Global" }], "meta": { "limit": 100 } }
```

The three examples correspond to `/categories`, `/platforms`, and `/regions`, respectively.

`compareAtPriceMinor` is null when the offer has no advertised comparison price. The API calculates any displayed discount from the compare-at and current prices, in the offer's currency; it must not trust a client-supplied percentage. An offer is eligible for the special-deals section only when the compare-at price is greater than the current price and the offer is active, in its deal window, and has available inventory.

Search implementation:

1. Normalize the query (Unicode normalization, case folding, whitespace collapse; optionally remove punctuation) and validate a practical length limit.
2. Use MySQL full-text search for exact/related term relevance.
3. If result confidence/count is low, look up candidates from the derived trigram index, aggregate gram overlap, cap candidates (for example, top 100), and score title similarity with bounded edit distance in application code.
4. Merge/deduplicate, apply active product and requested filters, rank, and paginate. Do not compute edit distance across the entire catalog.
5. Add a minimum query length and rate limit to limit expensive fuzzy queries. Measure relevance and latency before tuning thresholds.

This hybrid is practical without another service at early scale. If catalog size, language requirements, or search latency outgrow it, migrate search behind the same endpoint to a dedicated engine such as Meilisearch or OpenSearch.

## Cart and checkout

Reserved for a later phase; not implemented in Phase 2B.

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/cart` | Return current user's cart with current offer data, stock, and recalculated totals. |
| `POST` | `/cart/items` | Add an offer and quantity; body `{ "offerId": 123, "quantity": 1 }`. |
| `PATCH` | `/cart/items/{id}` | Change quantity; validate positive bounded quantity. |
| `DELETE` | `/cart/items/{id}` | Remove an item. |
| `POST` | `/checkout` | Authenticated simulated checkout. Accept an idempotency key; server revalidates prices/currency and locks inventory. |

Checkout response on success: `201` with order ID, status, currency, totals, and purchase item summaries. It must not include plaintext keys in the initial response; users retrieve delivery through the order endpoint after the order is committed as paid.

## Orders and key delivery

Reserved for a later phase; not implemented in Phase 2B.

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/orders` | Current user's paginated order history. |
| `GET` | `/orders/{id}` | Current user's order and item summaries; enforce ownership. |
| `GET` | `/orders/{id}/keys` | Return plaintext purchased keys only after verifying ownership and paid status. Record an access audit event; do not cache the response. |

Set key-response cache headers to `Cache-Control: no-store`; do not include keys in URLs, analytics, client logs, or error reporting. Consider allowing repeat reveal by the owning user for MVP support, with each retrieval audited. One-time retrieval adds account-recovery and delivery-failure complexity and is not assumed here.

## Admin and seller operations

Admin catalog, banner, seller moderation, offer management, and key upload endpoints are deliberately not part of the customer MVP contract. If required for a usable seeded demo, add narrowly authorized admin routes in a separate phase. Never expose bulk key data in ordinary seller/catalog endpoints.

