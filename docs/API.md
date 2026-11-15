# REST API

## Conventions

- Base path: `/api/v1`; all endpoints return JSON.
- Browser calls use same-origin HTTPS. Authenticated endpoints use an `HttpOnly` server-side session cookie and state-changing requests require a CSRF token.
- Money uses `{ "amountMinor": 1299, "currency": "EUR" }`.
- Paginated lists use `{ "data": [...], "meta": { "page": 1, "perPage": 24, "total": 42 } }`. Bounded section and lookup lists use `{ "data": [...], "meta": { "limit": 8 } }`.
- Errors use `{ "error": { "code": "VALIDATION_ERROR", "message": "...", "fields": {} , "requestId": "..." } }`. Omit `fields` when inapplicable. Never return SQL, stack traces, secrets, or key ciphertext.
- Use `400` for malformed input, `401` for missing/invalid authentication, `403` for insufficient permission/CSRF failure, `404` for missing resources, `409` for conflicts/insufficient inventory, `413` for oversized request bodies, `422` for semantic validation failures, and `429` for throttling.
- Apply bounded pagination and allowlisted sort fields/directions. Never interpolate unchecked request values into SQL.
- State-changing requests require CSRF validation. Do not enable permissive CORS.
- Mutating JSON requests must use `Content-Type: application/json` and are limited to 16 KiB.

## Implemented scope through Phase 5

Phases 2B and 3 implement the health check, product catalog and reference-data lookups, four independent homepage endpoints, product search, and product detail with seller-offer comparison. Phase 4 adds customer registration, login/logout, session-backed identity, and a guest/account cart. Phase 5 adds authenticated simulated checkout, order history, and encrypted fictional-key delivery. No real payment provider is integrated.

An empty result is a successful response (`200`) with `"data": []`. The frontend receives all homepage section data from these independent backend endpoints; it does not embed product, deal, or category lists. The repeatable seeds create 30 active games, 61 active seller offers, recent simulated paid-order activity for sales-based rankings, and discounted offers for the deals section. Normal catalog seeding adds no digital keys; a separate local/test-only command creates fictional checkout inventory.

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

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/auth/csrf` | Issue/refresh a CSRF token, bound to the current session when signed in. |
| `POST` | `/auth/register` | Create a customer account and authenticated session. |
| `POST` | `/auth/login` | Verify credentials, rotate the prior session, and create a server-side session. |
| `POST` | `/auth/logout` | Revoke the current session and expire session/CSRF cookies. |
| `GET` | `/auth/me` | Return current user or `401`. |

Authentication endpoints use same-origin cookies named `gamehub_session` and `gamehub_csrf`, scoped to `/api/v1`, `SameSite=Lax`, and `HttpOnly`. The session cookie is always Secure when `APP_ENV=production`; `SESSION_COOKIE_SECURE` can override transport detection outside production. Session tokens are 256-bit random values; only their SHA-256 hashes are stored. Sessions expire after 14 days. The CSRF endpoint returns a token in JSON for the frontend to keep in memory; mutating requests must send the same token in `X-CSRF-Token` and the CSRF cookie. For signed-in sessions it is also checked against the session's stored CSRF-token hash. Login and registration use the anonymous double-submit token too.

Registration accepts `{ "email": "...", "password": "...", "displayName": "..." }`. Email addresses are normalized to lowercase, display names must be 2–80 bytes, and passwords must be 12–72 bytes. Login accepts `{ "email": "...", "password": "..." }`. Successful responses return `{ "data": { "user": { "id": 1, "email": "...", "displayName": "..." }, "csrfToken": "..." } }`; registration returns `201`, login `200`. The CSRF bootstrap response is `{ "data": { "csrfToken": "..." } }`; `/auth/me` returns `{ "data": { "user": ... } }`. Authentication attempts are rate-limited per hashed client IP: 10 login attempts per 15 minutes and 5 registrations per hour. `409 EMAIL_ALREADY_REGISTERED`, `401 INVALID_CREDENTIALS`, `403 CSRF_VALIDATION_FAILED`, and `429 RATE_LIMITED` are explicit errors.

If a guest cart exists, successful registration/login merges it into the new/current account cart in the same transaction. Matching offer quantities are combined; if a combined quantity would exceed 99, the operation returns `409 CART_MERGE_CONFLICT` without losing the guest cart or replacing an existing session. After a successful merge the guest cart is deleted and its cookie is expired.

The SPA does not protect the cart route: guest and authenticated users can view and edit their respective carts. Checkout is the point at which authentication is required. Any frontend guard is only a navigation convenience; the checkout API must independently resolve and authorize the session.

## Homepage

Each section has an independent endpoint and can be requested, failed, and refreshed separately by the frontend. Responses currently use `Cache-Control: no-store`; cache policy can be revisited for public catalog data independently of this API contract.

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/home/hero-banners` | Active banners ordered by `sort_order`. |
| `GET` | `/home/best-sellers` | Products ranked by completed units sold in the last 30 days. |
| `GET` | `/home/special-deals` | Active, in-window offers with `compare_at_price_minor > price_minor`, ordered by discount percentage descending. |
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

`discountPercent` is calculated by the API from integer minor-unit values as a whole percentage; the client does not supply it. Deals do not require seeded key inventory, so the endpoint can return an eligible price promotion with `availableQuantity: 0`. The quantity remains the actual count of available keys and must be checked independently before any purchase.

`GET /home/popular-categories` response:

```json
{
  "data": [
    { "id": 1, "slug": "role-playing", "name": "Role-playing", "unitsSold": 12 }
  ],
  "meta": { "limit": 8 }
}
```

For best sellers and popular categories, only orders with `status="paid"` and `paid_at` within the rolling 30-day period count. The current repeatable seed inserts simulated paid-order activity for its internal disabled account, so these sections have data on a fresh setup. If there are no qualifying orders, the endpoint returns `{"data":[],"meta":{"limit":8}}`.

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

`compareAtPriceMinor` is null when the offer has no advertised comparison price. The API calculates any displayed discount from the compare-at and current prices, in the offer's currency; it must not trust a client-supplied percentage. An offer is eligible for the special-deals section only when the compare-at price is greater than the current price and the offer is active and in its deal window. Eligibility does not imply available key inventory; the response carries the actual inventory count separately.

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

`meta.total` describes the filtered, ranked results in the bounded scored set. `capped` is true when 100 eligible scoring candidates (or the 1,000-result lexical pool) are reached; in that case, more matches may exist beyond this page set. Invalid or too-short queries return `422 VALIDATION_ERROR`; an unmatched query returns `200` with empty `data`. Search allows 60 requests per minute per hashed client IP and returns `429 RATE_LIMITED` after the limit; query length, pagination, and candidate count are also bounded.

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

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/cart` | Return the current account or guest cart with current offer data and recalculated totals. |
| `POST` | `/cart/items` | Add an offer and quantity; body `{ "offerId": 123, "quantity": 1 }`. |
| `PATCH` | `/cart/items/{id}` | Set a cart item's quantity. |
| `DELETE` | `/cart/items/{id}` | Remove an item, including one whose offer is no longer available. |
| `POST` | `/checkout` | Authenticated simulated checkout. Requires `Idempotency-Key`; server revalidates current prices/currency and locks inventory. |

Cart endpoints support both guests and authenticated users; mutations require CSRF validation. `GET /cart` returns the current account cart when signed in, otherwise the guest cart associated with the guest cookie (or an empty cart if none exists). The `gamehub_guest_cart` cookie is a 256-bit random, HttpOnly, SameSite=Lax token scoped to `/api/v1`; only its SHA-256 hash is stored. The cookie expires after 14 days. Guest cart contents are server-backed, so current offer details and prices are still read from the database.

The cart stores the selected `offerId`, not a client-provided price. Adding the same offer again increments its quantity. Quantities must be integers from 1 to 99. The server validates that the offer, product, platform, and region are active and the seller is approved for add/update. A missing offer returns `404 OFFER_NOT_FOUND`; an inactive/unavailable offer returns `409 OFFER_UNAVAILABLE`; invalid fields or quantities return `422 VALIDATION_ERROR`. `POST` returns `201`; `PATCH` and `DELETE` return `200`. Successful mutations return the updated cart in the same envelope as `GET /cart`.

Cart editing does not depend on digital-key inventory. Guests and signed-in users can add any active catalog offer even when its reported `availableQuantity` is zero, and add/update never queries key rows. Inventory is authoritative at checkout only. Each cart read joins the current database offer and recalculates line totals and per-currency subtotals from its current `price_minor`. If an offer becomes unavailable while in the cart, the item remains visible with `offerAvailable: false`, null price/total/currency, and an explanation; it can be removed, but cannot be updated or purchased until removed or made active again.

`GET /cart` response:

```json
{
  "data": {
    "items": [
      {
        "id": 17,
        "offerId": 42,
        "product": { "slug": "example-game", "name": "Example Game" },
        "seller": { "name": "Pixel Vault" },
        "platform": { "name": "PC" },
        "region": { "name": "Global" },
        "quantity": 2,
        "offerAvailable": true,
        "unavailableReason": null,
        "unitPriceMinor": 1899,
        "lineTotalMinor": 3798,
        "currency": "EUR"
      }
    ],
    "totals": [{ "currency": "EUR", "subtotalMinor": 3798 }],
    "itemCount": 2
  }
}
```

`itemCount` includes all cart quantities, including unavailable lines. Unavailable lines are excluded from payable subtotals. Multiple currencies are returned as separate totals rather than combined.

`POST /checkout` requires a signed-in account and CSRF token. Send an `Idempotency-Key` header containing 8–128 letters, digits, periods, underscores, colons, or hyphens. The server locks the account cart, rechecks offer eligibility and current database prices, requires all items to use one currency, and locks the required available key rows in offer/key ID order. It verifies each selected ciphertext can be authenticated and decrypted with the configured key ring, then creates a paid order with purchase snapshots, assigns keys, and clears the cart in one database transaction. This is a fully simulated purchase: there is no payment provider, card data, or real payment.

A successful first request returns `201` with `{ "data": { "orderId": 123, "status": "paid", "currency": "EUR", "subtotalMinor": 3798, "totalMinor": 3798 } }`. A retry using the same account and idempotency key returns `200` with the original order and does not create another purchase. The response never includes keys.

Failures return explicit `409` codes such as `CART_EMPTY`, `OFFER_UNAVAILABLE`, `MIXED_CURRENCY_CART`, and `INSUFFICIENT_INVENTORY`; invalid/missing idempotency keys return `422 VALIDATION_ERROR`. Failed checkout leaves the cart, order rows, and key inventory unchanged. The frontend displays the failure and keeps the customer’s selections available for correction or retry.

## Orders and key delivery

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/orders?page=1&perPage=20` | Current user's paginated order history (`perPage` 1–50). |
| `GET` | `/orders/{id}` | Current user's order and item summaries; enforce ownership. |
| `GET` | `/orders/{id}/keys` | Return plaintext purchased keys only after verifying ownership and paid status. Record an access audit event; do not cache the response. |

All order endpoints require a signed-in account and return `404 ORDER_NOT_FOUND` for orders owned by another user. Key retrieval also requires `status="paid"` and returns `409 ORDER_NOT_PAID` otherwise. The keys endpoint returns `{ "data": { "orderId": 123, "items": [{ "orderItemId": 456, "productName": "Example Game", "keys": ["GAMEHUB-DEMO-NOT-REDEEMABLE-..."] }] } }`. Repeated retrieval by the owner is supported and each successful reveal is audited.

Fictional, visibly non-redeemable keys can be added to local/test databases with `APP_ENV=development php backend/bin/seed-fictional-keys.php` after configuring the encryption key ring. The seed keeps at least two available fictional keys per active offer and refuses to run outside local, development, or test environments. No fake keys are added by the catalog seed or to production. Key responses use `Cache-Control: no-store`; never include keys in URLs, application logs, analytics, or error reports.

`digital_keys.ciphertext` is encrypted with PHP Sodium `secretbox`; its random nonce and key version are stored alongside it. Configure `KEY_ENCRYPTION_KEYS` as a JSON object mapping version numbers to base64-encoded 32-byte keys, and set `KEY_ENCRYPTION_KEY_VERSION` to the active version. Keep prior versions in the key ring while ciphertext uses them. The application fails closed when configuration is absent, invalid, or unable to decrypt a record.

## Admin and seller operations

Admin catalog, banner, seller moderation, offer management, and key upload endpoints are deliberately not part of the customer MVP contract. If required for a usable seeded setup, add narrowly authorized admin routes in a separate phase. Never expose bulk key data in ordinary seller/catalog endpoints.

