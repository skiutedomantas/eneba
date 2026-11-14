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

