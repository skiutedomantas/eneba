# Database Design

## Conventions

- MySQL 8 (default) or PostgreSQL 14+, with PDO and UTC timestamps. The application connects to one database engine per deployment.
- Use `BIGINT` identifiers (unsigned on MySQL) or consistently adopted UUIDs, with foreign keys for relational integrity.
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
| `auth_rate_limits` | `action`, `subject_hash`, `window_started_at`, `attempt_count`, `updated_at` | Per-IP fixed-window login, registration, and search limits; store a hash of the client IP, not the raw address. |
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
| `digital_keys` | `id`, `offer_id`, `ciphertext`, `nonce`, `key_version`, `status`, `order_item_id` (nullable foreign key), timestamps | `status`: `available`, `sold`, or `disabled`. Key plaintext is encrypted with Sodium `secretbox`; each row has a fresh nonce and encryption-key version and can be assigned to at most one order item. Never expose this table directly. |

### Shopping and order history

| Table | Important columns | Notes |
|---|---|---|
| `carts` | `id`, `user_id` (nullable unique), `guest_token_hash` (nullable unique), timestamps | Exactly one owner is set: an account or a guest token. Guest identity is stored only as a hash; login merges the guest cart into the account cart. |
| `cart_items` | `id`, `cart_id`, `offer_id`, `quantity`, timestamps | Unique `(cart_id, offer_id)`; stores the selected offer and quantity, not a price snapshot. Cart add/update do not require key inventory; checkout validates availability and quantity. |
| `orders` | `id`, `user_id`, `status`, `currency`, `subtotal_minor`, `total_minor`, `idempotency_key`, `created_at`, `paid_at` | Unique `(user_id, idempotency_key)` makes checkout retries safe. Simulated purchases transition to `paid` only as part of the transaction that assigns all purchased keys. |
| `order_items` | `id`, `order_id`, `offer_id`, `product_name_snapshot`, `seller_name_snapshot`, `platform_name_snapshot`, `region_name_snapshot`, `quantity`, `unit_price_minor`, `currency` | Immutable purchase-time snapshots; offer may later change or be disabled. |
| `key_access_logs` | `id`, `user_id`, `order_item_id`, `accessed_at`, `request_id` | Audit metadata only. Never store the returned plaintext key in logs. |

### Homepage content

| Table | Important columns | Notes |
|---|---|---|
| `hero_banners` | `id`, `title`, `image_url`, `target_url`, `sort_order`, `starts_at`, `ends_at`, `status` | Only active, in-window banners are returned. |

Best sellers, special deals, and popular categories are computed from order/offer data in the MVP rather than duplicated into mutable homepage tables. If editorial curation or query load later warrants it, add a materialized/cache table with an explicit refresh strategy.

An offer qualifies as a special deal when it is active, within its configured deal window, and has a non-null `compare_at_price_minor` greater than `price_minor`. Deal merchandising is independent of key inventory so discounted offers can be presented without seeding fake keys; the API still reports the actual available-key count, and checkout must validate inventory separately. Calculate the discount from the two integer amounts in the same currency; do not persist a separate percentage that can become stale. Treat a null comparison price (or one not greater than the selling price) as no advertised discount.

The repeatable catalog seed creates 30 active games across 11 categories and 61 active offers, with two offers per game except for Cyberpunk 2077, which has three. Five seller profiles provide EUR price comparisons using PC, PlayStation 5, Xbox Series X|S, and Global, Europe, and United States regions. The five existing offer rows are retained; the seed adds offers only as needed to provide two or three offers per game, making it safe to rerun against an already populated catalog. A separate seed adds recent simulated paid-order activity for homepage rankings. A separate local/test-only command seeds fictional, non-redeemable encrypted keys so simulated purchases can complete; it is not part of the normal catalog seed and refuses production environments. The frontend hides inventory labels, while checkout treats actual digital-key rows as authoritative. The catalog seed aborts rather than repurposing a legacy offer if it has keys, order items, or cart items.

`products.image_url` is nullable and already supports external HTTPS references. Game cover references are deferred; products currently have no cover images. The intended source is IGDB's [cover image API](https://api-docs.igdb.com/#images), with visible attribution and rights confirmation before any commercial deployment. No IGDB credentials belong in the repository or runtime application.

### Search support

| Table | Important columns | Notes |
|---|---|---|
| `product_search_grams` | `product_id`, `gram`, `weight` | Derived two/three-character gram index for typo-tolerant fallback; unique `(product_id, gram)`, index `(gram, product_id)`. Rebuild when product names or active status change. |

MySQL `FULLTEXT` over product name and description or PostgreSQL full-text search (`simple` configuration with a GIN expression index) provides first-pass relevance and token-prefix matching. Both engines use the same normalized name-gram fallback for typo and mid-word partial matching. Gram rows are derived data generated from normalized active product names. The repeatable seed refreshes them, and `php backend/bin/rebuild-search-index.php` rebuilds them after catalog name/status changes. Search normalization uses PHP `intl` and `mbstring`; these extensions must be enabled in the backend runtime. See `API.md` for query bounds, fallback behavior, and response metadata.

## Relationships

- `users` 1—0..1 `seller_profiles`: customers may also have an approved seller profile.
- `users` 1—many `sessions`: one user can have multiple devices/sessions; revocation is per session.
- `auth_rate_limits` uses one row per action and hashed client IP; it is operational state, not an identity relationship.
- `products` many—many `categories` through `product_categories`.
- `products`, `seller_profiles`, `platforms`, and `regions` each have a one—many relationship to `offers`.
- `offers` 1—many `digital_keys`: each key is stock for one specific offer.
- `users` 1—0..1 account `carts`; each guest-token hash 1—0..1 guest `carts`; `carts` 1—many `cart_items`; each cart item references one offer.
- `users` 1—many `orders`; `orders` 1—many `order_items`; each item references its original offer and stores purchase snapshots.
- `order_items` 1—many `digital_keys` after purchase (one per purchased unit); each key row's nullable `order_item_id` foreign key can refer to only one order item, while allowing multiple keys for an item with quantity greater than one.
- `users` and `order_items` 1—many `key_access_logs` rows provide an access audit trail.
- `products` 1—many derived `product_search_grams` rows.

## Constraints and indexes

Add indexes for:

- Unique `users.email`, `products.slug`, category/platform/region codes, `sessions.token_hash`, and seller profile `user_id`.
- `offers(product_id, status)`, `offers(platform_id, region_id, status, price_minor)`, and `offers(seller_id, status)`.
- `digital_keys(offer_id, status, id)` for availability/locking and `digital_keys(order_item_id)` for order delivery.
- Unique `carts.guest_token_hash`; `cart_items(cart_id, offer_id)` unique; `auth_rate_limits(action, subject_hash)` primary key; `orders(user_id, created_at)`; `order_items(order_id)`.
- `product_categories(category_id, product_id)` and gram lookup `(gram, product_id)`.
- MySQL full-text index or PostgreSQL GIN full-text expression index, depending on the selected database.

The rate-limit action enum includes `login`, `register`, and `search`. A scheduled maintenance task should delete rows whose `window_started_at` is older than two days; counters are operational state and can be safely rebuilt.

Use foreign-key deletion rules deliberately: restrict deletes for products/offers referenced in order history; soft-disable catalog and seller records instead. Cascading deletion is appropriate only for disposable derived data such as search grams.

## Key inventory and checkout consistency

Available inventory is the count of `digital_keys` with `status='available'`, not a client-provided stock value. Checkout locks the account cart, cart items, active offers, and the required available key rows in deterministic offer/key ID order. It validates one currency and current prices, then creates the paid order and snapshots, assigns keys, and clears the cart in one transaction. Any failure rolls back every write and leaves the cart intact. The `(user_id, idempotency_key)` unique key protects retries; assigned keys change from `available` to `sold` and reference one order item. Use bounded retries for deadlocks without changing the idempotency key.

Cart rows are not reservations and do not hold key inventory. A guest cart is identified by an HttpOnly random cookie; only its hash is stored in `carts.guest_token_hash`. The owner constraint ensures a cart belongs to exactly one account or guest token. On login/registration, guest items are merged transactionally into the account cart and the guest cart is deleted. If matching quantities would exceed the per-offer limit, the merge fails without discarding either cart.

The current selling price is always read from `offers.price_minor` when rendering cart contents and is revalidated again during checkout. Soft-disabled offers remain joinable so the cart can clearly mark them unavailable and allow removal without losing the selected-offer reference. Cart updates require an active offer, active product/platform/region, and approved seller, but do not query or reserve `digital_keys`. Guests may browse and edit their cart; checkout must require an authenticated user.

The simulated purchase operation has no real payment provider and marks the order paid only in the same transaction that commits all assigned keys. Keys are fictional and explicitly non-redeemable. Key plaintext is encrypted at insertion with Sodium `secretbox`, using a random nonce; only the delivery service decrypts after checking order ownership and paid status. Configure a JSON key ring in `KEY_ENCRYPTION_KEYS` and select the active version with `KEY_ENCRYPTION_KEY_VERSION`; retain older key versions for decryption during rotation. A future real payment provider would require a separately designed reservation/expiry and payment-confirmation workflow, not an open database transaction around a network call.

## Database portability and migration plan

The backend supports one selected engine at a time through `DB_DRIVER=mysql` (the default) or `DB_DRIVER=pgsql`. Both use PDO prepared statements and the same repositories/services. Do not configure both engines for dual writes.

| Concern | MySQL implementation | PostgreSQL implementation |
|---|---|---|
| Schema | Existing migrations in `backend/database/migrations/`; InnoDB, unsigned auto-increment IDs, enum columns, `VARBINARY`, and `utf8mb4` | Separate migrations in `backend/database/migrations/postgresql/`; identity IDs, checked `VARCHAR` status columns, `BYTEA`, and standard UTF-8 |
| Migration runner | MySQL advisory named lock; DDL follows MySQL's implicit-commit behavior | PostgreSQL advisory lock; each migration and its migration-history insert run in a transaction |
| Idempotent inserts | `ON DUPLICATE KEY UPDATE` and `INSERT IGNORE` | `ON CONFLICT` |
| Inserted IDs | PDO `lastInsertId()` | `INSERT ... RETURNING id` |
| Time | Connection session set to UTC; MySQL date intervals are confined to its rate-limit SQL | Connection session set to UTC; bounded report cutoffs are passed as UTC parameters |
| Search | MySQL FULLTEXT natural/boolean token-prefix search | PostgreSQL `to_tsvector`/`plainto_tsquery`/`to_tsquery` with a GIN expression index |
| Encrypted bytes | Binary columns | `BYTEA`; the PDO layer base64-encodes bind values into PostgreSQL `decode()` and decodes selected values after `encode()` |
| Lock/error handling | `FOR UPDATE`; MySQL deadlock/lock timeout and duplicate-key codes | `FOR UPDATE`; SQLSTATE handling for deadlock, serialization failure, lock-unavailable, and unique violation |

Both schema variants preserve the same table and API contracts, foreign-key deletion rules, price/inventory constraints, and checkout atomicity. PostgreSQL uses a trigger to maintain the same `updated_at` behavior as MySQL's `ON UPDATE CURRENT_TIMESTAMP`. Database time calculations use UTC session settings; application-computed date cutoffs are passed as prepared parameters to avoid engine-specific interval expressions.

### Safe cutover from MySQL

1. **Rehearse on isolated databases.** Keep the live MySQL database unchanged. Create a disposable PostgreSQL database and set `DB_DRIVER=pgsql`, `DB_PORT=5432`, and `DB_SSLMODE` to the required deployment value.
2. **Create schema and reference data.** Run `php backend/bin/migrate.php`, then `php backend/bin/seed.php` against the empty PostgreSQL database. The runner selects the PostgreSQL migration directory from the PDO driver. Do not run test suites or fictional-key seeding against production.
3. **Migrate existing records separately.** This repository does not yet include a MySQL-to-PostgreSQL data-copy command. If preserving live data, use a reviewed ETL/export-import process that maps unsigned IDs to PostgreSQL `BIGINT`, converts enum values and timestamps, preserves digital-key ciphertext/nonce bytes exactly, imports foreign-key dependencies in order, and resets identity sequences above each imported maximum ID. Preserve user sessions only if their token hashes and expiration policy are intentionally being carried over; otherwise require users to sign in again.
4. **Rebuild derived search data and verify counts.** Run `php backend/bin/rebuild-search-index.php`, compare per-table counts and key relationships, and verify that every encrypted key can be authenticated using the separately backed-up key ring. Rehearse restore and checkout/key delivery on staging.
5. **Run both-engine regression suites.** Run every backend test against a disposable PostgreSQL database and rerun them against MySQL. The checkout/concurrency and user-journey tests are required before cutover.
6. **Switch once, with rollback available.** Back up MySQL and the encryption key ring, stop writes during the final data copy, switch the PHP environment/credentials to PostgreSQL, and perform health plus customer-flow smoke tests. Do not dual-write. If validation fails, restore the previous application configuration and MySQL backup; retain PostgreSQL read-only for diagnosis until reconciliation is complete.

The schema migrations create a fresh database; they do not automatically copy existing MySQL records. PostgreSQL cutover of an active marketplace therefore requires a separately rehearsed data migration and consistency check. Both engines must use the same application encryption key ring to preserve key decryption.

