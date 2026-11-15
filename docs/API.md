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

## Implemented scope through Phase 3

Phases 2B and 3 implement the health check, product catalog and reference-data lookups, four independent homepage endpoints, product search, and product detail with seller-offer comparison. Authentication, registration, login/logout, cart, checkout, orders, and digital-key delivery remain future-phase contracts.

An empty result is a successful response (`200`) with `"data": []`. The API does not invent sales or inventory to populate a section. The current seed contains 30 active games and 61 active seller offers, with two or three offers per game. It creates no completed orders or digital keys, so best sellers and popular categories remain empty and special deals remain key-inventory-gated.

Products span 11 categories. Offers use five seller profiles, EUR prices, compare-at prices, PC/PlayStation 5/Xbox Series X|S, and Global/Europe/United States regions. The catalog and product comparison pages do not display inventory status labels; the API still reports key availability for backend flows. Game cover references are deferred, so seeded products currently return `imageUrl: null`.

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

Reserved for the authentication phase; not implemented through Phase 3.

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

The following response snippets show the response shape; example IDs, amounts, sales, and inventory are not current seed values.

`GET /home/best-sellers` response:

```json
{
  "data": [
    {
      "id": 1,
      "slug": "example-game",
      "name": "Example Game",
      "description": "Example product description.",
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
        "slug": "example-game",
        "name": "Example Game",
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
| `GET` | `/search` | Global search with typo and partial-word tolerance. |
| `GET` | `/products/{slug}` | Active product details and approved sellers' active offers for comparison. |

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
      "slug": "example-game",
      "name": "Example Game",
      "description": "Example product description.",
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
  "meta": { "page": 1, "perPage": 20, "total": 30, "sort": "newest", "currency": "EUR" }
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

`GET /search` accepts:

- `q` (required; 2–100 normalized letters/numbers; raw input up to 200 characters)
- `page` (default `1`, positive integer up to `10000`)
- `perPage` (default `20`, integer `1..48`)
- The same `platform`, `region`, `category`, `currency`, `minPriceMinor`, and `maxPriceMinor` filters as `/products`
- `sort`: `relevance` (default), `newest`, `price_asc`, `price_desc`, or `popular`

Queries are Unicode NFKC-normalized, case-folded, and reduced to letter/number tokens with collapsed whitespace. MySQL FULLTEXT natural-language and token-prefix matches are ranked first. When fewer than eight FULLTEXT candidates are found, the API merges up to 1,000 candidates from the derived product-name gram index. It applies the product's catalog filters before scoring title similarity in PHP, which is limited to 100 products; ordinary catalog requests do not invoke fuzzy scoring.

Search response:

```json
{
  "data": [
    {
      "id": 1,
      "slug": "example-game",
      "name": "Example Game",
      "description": "Example product description.",
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
  "meta": {
    "q": "example game",
    "page": 1,
    "perPage": 20,
    "total": 1,
    "sort": "relevance",
    "currency": "EUR",
    "candidateLimit": 100,
    "capped": false
  }
}
```

`meta.total` describes the filtered, ranked results in the bounded scored set. `capped` is true when 100 eligible scoring candidates (or the 1,000-result lexical pool) are reached; in that case, more matches may exist beyond this page set. Invalid or too-short queries return `422 VALIDATION_ERROR`; an unmatched query returns `200` with empty `data`. Search-specific request rate limiting is deferred to Phase 6; query length, pagination, and candidate count are bounded in this phase.

`GET /products/{slug}` returns `404 NOT_FOUND` when the product is missing or inactive. Only active products and active offers from approved sellers with active platform/region options are exposed. Offers include an `availableQuantity` value derived from digital-key records and are grouped by currency, then ordered by price and offer ID within each currency; amounts in different currencies are not treated as directly comparable. A product with no qualifying offers returns an empty `offers` list. The frontend does not show availability labels.

```json
{
  "data": {
    "product": {
      "id": 1,
      "slug": "example-game",
      "name": "Example Game",
      "description": "Example product description.",
      "imageUrl": null
    },
    "offers": [
      {
        "offerId": 1,
        "seller": { "id": 1, "name": "Pixel Vault" },
        "priceMinor": 1899,
        "compareAtPriceMinor": 4999,
        "currency": "EUR",
        "platform": { "code": "pc", "name": "PC" },
        "region": { "code": "global", "name": "Global" },
        "availableQuantity": 0,
        "discountPercent": 62
      },
      {
        "offerId": 4,
        "seller": { "id": 2, "name": "Arcade Exchange" },
        "priceMinor": 2199,
        "compareAtPriceMinor": 3999,
        "currency": "EUR",
        "platform": { "code": "pc", "name": "PC" },
        "region": { "code": "global", "name": "Global" },
        "availableQuantity": 0,
        "discountPercent": 45
      }
    ]
  }
}
```

The browser can select an offer for display, but does not reserve inventory or create a cart in Phase 3.

The catalog and product detail UI show seller pricing, platform, region, and discounts without displaying inventory status labels.

This hybrid is practical without another service at early scale. If catalog size, language requirements, or search latency outgrow it, migrate search behind the same endpoint to a dedicated engine such as Meilisearch or OpenSearch.

## Cart and checkout

Reserved for Phase 4; not implemented through Phase 3.

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/cart` | Return current user's cart with current offer data, stock, and recalculated totals. |
| `POST` | `/cart/items` | Add an offer and quantity; body `{ "offerId": 123, "quantity": 1 }`. |
| `PATCH` | `/cart/items/{id}` | Change quantity; validate positive bounded quantity. |
| `DELETE` | `/cart/items/{id}` | Remove an item. |
| `POST` | `/checkout` | Authenticated simulated checkout. Accept an idempotency key; server revalidates prices/currency and locks inventory. |

Checkout response on success: `201` with order ID, status, currency, totals, and purchase item summaries. It must not include plaintext keys in the initial response; users retrieve delivery through the order endpoint after the order is committed as paid.

## Orders and key delivery

Reserved for Phase 5; not implemented through Phase 3.

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/orders` | Current user's paginated order history. |
| `GET` | `/orders/{id}` | Current user's order and item summaries; enforce ownership. |
| `GET` | `/orders/{id}/keys` | Return plaintext purchased keys only after verifying ownership and paid status. Record an access audit event; do not cache the response. |

Set key-response cache headers to `Cache-Control: no-store`; do not include keys in URLs, analytics, client logs, or error reporting. Consider allowing repeat reveal by the owning user for MVP support, with each retrieval audited. One-time retrieval adds account-recovery and delivery-failure complexity and is not assumed here.

## Admin and seller operations

Admin catalog, banner, seller moderation, offer management, and key upload endpoints are deliberately not part of the customer MVP contract. If required for a usable seeded setup, add narrowly authorized admin routes in a separate phase. Never expose bulk key data in ordinary seller/catalog endpoints.

