# Database Design

## Conventions

- MySQL 8, InnoDB, `utf8mb4`, and UTC timestamps.
- Use `BIGINT UNSIGNED` identifiers (or consistently adopted UUIDs) and foreign keys for relational integrity.
- Store money as `BIGINT` integer minor units with an ISO 4217 currency code. Never use floating-point columns for prices.
- Use `created_at` and `updated_at` where records change; use explicit statuses instead of deleting records whose history matters.
- Store password hashes from `password_hash()` only. Never store plaintext passwords, session tokens, or plaintext digital keys.

## Tables

The following is a logical schema; migrations should define exact lengths, nullability, constraints, and indexes.

### Identity

| Table | Important columns | Notes |
|---|---|---|
| `users` | `id`, `email` (unique), `password_hash`, `display_name`, `role`, `status`, timestamps | `role` is `customer` or a tightly controlled `admin`; `status` can disable access. |
| `sessions` | `id`, `user_id`, `token_hash` (unique), `csrf_token_hash`, `expires_at`, `created_at`, `last_seen_at`, `revoked_at` | Store only a hash of the random cookie token. Session rows permit revocation and expiry. |
| `seller_profiles` | `id`, `user_id` (unique), `display_name`, `status`, timestamps | A seller is a user with a seller profile; approval/status is server controlled. |

### Catalog and offers

| Table | Important columns | Notes |
|---|---|---|
| `products` | `id`, `slug` (unique), `name`, `description`, `image_url`, `status`, timestamps | Represents a catalog item; offers carry sale-specific platform and region details. |
| `categories` | `id`, `slug` (unique), `name`, `status`, `sort_order` | A product may belong to multiple categories. |
| `product_categories` | `product_id`, `category_id` | Composite primary key; many-to-many join. |
| `platforms` | `id`, `code` (unique), `name`, `status` | Examples: PC, PlayStation, Xbox. |
| `regions` | `id`, `code` (unique), `name`, `status` | Examples: global, EU, US. |
| `offers` | `id`, `product_id`, `seller_id`, `platform_id`, `region_id`, `price_minor`, `compare_at_price_minor` (nullable), `currency`, `status`, `deal_starts_at`, `deal_ends_at`, timestamps | `seller_id` references `seller_profiles`; an offer identifies what is being sold and by whom. `price_minor` is the current selling price. `compare_at_price_minor` is the reference price used to calculate and display a discount; it is null when no comparison price is advertised. |
| `digital_keys` | `id`, `offer_id`, `ciphertext`, `nonce`, `key_version`, `status`, `order_item_id` (nullable foreign key), timestamps | `status`: `available`, `sold`, or `disabled`. Ciphertext is authenticated-encrypted; each key row can be assigned to at most one order item. Never expose this table directly. |

### Shopping and order history

| Table | Important columns | Notes |
|---|---|---|
| `carts` | `id`, `user_id` (unique), timestamps | One active cart per user in the MVP. |
| `cart_items` | `id`, `cart_id`, `offer_id`, `quantity`, timestamps | Unique `(cart_id, offer_id)`; quantity is positive and bounded by available keys at checkout. |
| `orders` | `id`, `user_id`, `status`, `currency`, `subtotal_minor`, `total_minor`, `idempotency_key`, `created_at`, `paid_at` | Unique `(user_id, idempotency_key)` when an idempotency key is supplied. Simulated payment status is recorded here. |
| `order_items` | `id`, `order_id`, `offer_id`, `product_name_snapshot`, `seller_name_snapshot`, `platform_name_snapshot`, `region_name_snapshot`, `quantity`, `unit_price_minor`, `currency` | Immutable purchase-time snapshots; offer may later change or be disabled. |
| `key_access_logs` | `id`, `user_id`, `order_item_id`, `accessed_at`, `request_id` | Audit metadata only. Never store the returned plaintext key in logs. |

### Homepage content

| Table | Important columns | Notes |
|---|---|---|
| `hero_banners` | `id`, `title`, `image_url`, `target_url`, `sort_order`, `starts_at`, `ends_at`, `status` | Only active, in-window banners are returned. |

Best sellers, special deals, and popular categories are computed from order/offer data in the MVP rather than duplicated into mutable homepage tables. If editorial curation or query load later warrants it, add a materialized/cache table with an explicit refresh strategy.

An offer qualifies as a special deal only when it is active, within its configured deal window, has available key inventory, and has a non-null `compare_at_price_minor` greater than `price_minor`. Calculate the discount from the two integer amounts in the same currency; do not persist a separate percentage that can become stale. Treat a null comparison price (or one not greater than the selling price) as no advertised discount.

### Search support

| Table | Important columns | Notes |
|---|---|---|
| `product_search_grams` | `product_id`, `gram`, `weight` | Optional derived trigram index for typo-tolerant fallback; unique `(product_id, gram)`, index `(gram, product_id)`. Regenerate when a product name changes. |

MySQL `FULLTEXT` over product name and description can provide the first-pass relevance search. Trigram rows are derived data and can be rebuilt from active product names. See `API.md` for query strategy.

## Relationships

- `users` 1—0..1 `seller_profiles`: customers may also have an approved seller profile.
- `users` 1—many `sessions`: one user can have multiple devices/sessions; revocation is per session.
- `products` many—many `categories` through `product_categories`.
- `products`, `seller_profiles`, `platforms`, and `regions` each have a one—many relationship to `offers`.
- `offers` 1—many `digital_keys`: each key is stock for one specific offer.
- `users` 1—1 active `carts`; `carts` 1—many `cart_items`; each cart item references one offer.
- `users` 1—many `orders`; `orders` 1—many `order_items`; each item references its original offer and stores purchase snapshots.
- `order_items` 1—many `digital_keys` after purchase (one per purchased unit); each key row's nullable `order_item_id` foreign key can refer to only one order item, while allowing multiple keys for an item with quantity greater than one.
- `users` and `order_items` 1—many `key_access_logs` rows provide an access audit trail.
- `products` 1—many derived `product_search_grams` rows.

## Constraints and indexes

Add indexes for:

- Unique `users.email`, `products.slug`, category/platform/region codes, `sessions.token_hash`, and seller profile `user_id`.
- `offers(product_id, status)`, `offers(platform_id, region_id, status, price_minor)`, and `offers(seller_id, status)`.
- `digital_keys(offer_id, status, id)` for availability/locking and `digital_keys(order_item_id)` for order delivery.
- `cart_items(cart_id, offer_id)` unique; `orders(user_id, created_at)`; `order_items(order_id)`.
- `product_categories(category_id, product_id)` and gram lookup `(gram, product_id)`.
- A MySQL full-text index on active-search fields where supported by the chosen query design.

Use foreign-key deletion rules deliberately: restrict deletes for products/offers referenced in order history; soft-disable catalog and seller records instead. Cascading deletion is appropriate only for disposable derived data such as search grams.

## Key inventory and checkout consistency

Available inventory is the count of `digital_keys` with `status='available'`, not a client-provided stock value. During checkout, lock the required available key rows (for example with `SELECT ... FOR UPDATE`) in a transaction. Assign keys and create order records atomically. A unique assignment plus transaction rollback prevents double sale. Use a consistent lock order and bounded retries for deadlock errors.

The simulated payment operation must not report success until the order and all requested keys have been committed as sold. If a future real payment provider is introduced, do not hold a database transaction open during a network call: add a reservation/expiry and payment-confirmation workflow, then fulfill idempotently after verified provider confirmation.

