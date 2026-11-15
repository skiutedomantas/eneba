# Marketplace Architecture

## Scope and assumptions

This design describes an MVP digital-goods marketplace inspired by Eneba. It uses React, TypeScript, Vite, Tailwind CSS, React Router, PHP 8.3+, PDO, and MySQL 8+. It does not initialize either application or add dependencies.

Assumptions to keep the first implementation bounded:

- The marketplace sells digital game products through multiple seller offers.
- An offer is for one product, platform, region, and currency. Prices are stored as integer minor units; an order contains offers in one currency.
- Checkout is simulated. There is no card collection, payment gateway, or claim of real payment processing.
- The first release is a single deployment and catalog. Seller onboarding, seller payouts, refunds, tax calculation, and a production payment integration are future work.
- The browser and API are deployed on the same origin (or behind a reverse proxy presenting one origin). This simplifies secure cookie authentication and CSRF protection.

## System overview

```text
Browser
  React SPA (Vite build)
    pages -> feature components -> typed API services
              |
              | same-origin HTTPS /api/v1
              v
  PHP REST API
    routing/controllers -> validation/session auth -> application services
                                            -> repositories (PDO)
                                                   |
                                                   v
                                               MySQL 8
```

The PHP API owns authorization, validation, pricing, availability, order state transitions, and digital-key access. The browser renders server-returned data and must never be treated as authoritative for prices, inventory, order status, or ownership.

Use explicit boundaries:

- **Controllers** parse HTTP input, invoke a service, and map results to HTTP responses.
- **Services** implement use cases and transaction boundaries (checkout, search, home sections).
- **Repositories** perform parameterized PDO reads and writes.
- **Validators/DTOs** normalize and validate request/response data.
- **Session/auth handling** resolves server-side sessions, enforces CSRF on state-changing requests, and applies authorization.
- **Frontend API services** own URL construction, typed payloads, and common error handling; components do not issue ad hoc fetch calls.

Keep the API stateless at the HTTP-request level, but use an opaque server-side session for browser identity. Avoid business logic in route files and SQL in React components.

## Proposed folder structure

```text
.
├── docs/
│   ├── ARCHITECTURE.md
│   ├── DATABASE.md
│   ├── API.md
│   ├── IMPLEMENTATION_PLAN.md
│   └── ai-logs/
│       └── AI-DEVELOPMENT-LOG.md
├── frontend/                         # React/Vite app, to be created after approval
│   ├── index.html
│   ├── src/
│   │   ├── app/                      # router, providers, app shell
│   │   ├── components/
│   │   │   ├── layout/               # header, navigation, page sections, footer
│   │   │   └── ui/                   # buttons, fields, cards, badges, feedback
│   │   ├── contexts/                 # authenticated user and cart state
│   │   ├── features/
│   │   │   ├── auth/
│   │   │   ├── cart/
│   │   │   ├── catalog/
│   │   │   ├── checkout/
│   │   │   ├── home/
│   │   │   ├── orders/
│   │   │   ├── product/
│   │   │   └── search/
│   │   ├── hooks/
│   │   ├── lib/                      # API client, formatting, shared utilities
│   │   ├── pages/
│   │   ├── styles/
│   │   │   ├── tokens.css            # semantic visual tokens
│   │   │   └── global.css            # global defaults and resets
│   │   ├── types/                    # shared API/domain response types
│   │   └── main.tsx
│   └── public/
├── backend/                          # PHP API, to be created after approval
│   ├── public/
│   │   └── index.php                 # front controller
│   ├── routes/
│   │   └── api.php
│   ├── src/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   ├── DTO/
│   │   ├── Validation/
│   │   ├── Services/
│   │   ├── Repositories/
│   │   ├── Security/
│   │   └── Support/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeds/
│   ├── tests/
│   └── config/
├── .env.example                      # names/placeholders only; never secrets
└── README.md
```

The structure is intentionally conventional and feature-oriented. It separates presentation, transport, business rules, and persistence without introducing extra services or a framework decision before approval.

## Design system

Phase 2 will establish and apply a consistent GameHub visual system using the palette and direction supplied by the user. These are semantic tokens, implemented as CSS custom properties and mapped to Tailwind utilities:

| Token | Value | Use |
|---|---|---|
| `--color-background` | `#4518AC` | Main page background |
| `--color-section` | `#311178` | Large content sections and navigation surfaces |
| `--color-card` | `#1F0A4D` | Product cards and footer |
| `--color-text` | `#FFFFFF` | Primary text |
| `--color-savings` | `#83E916` | Savings/discount text and indicators |
| `--color-region` | `#E66A8C` | Region labels |

Use sharp rectangular geometry throughout: zero border radius on cards, buttons, inputs, section containers, labels, and banners. Use borders, spacing, and tonal surfaces to express hierarchy rather than rounded shapes. Keep text readable on each surface; use the savings and region colors as foreground indicators on dark purple surfaces rather than pairing them with low-contrast white text.

The shared shell has prominent navigation and search. Homepage promotions use large rectangular banners; homepage sections are visually distinct rectangular surfaces. Product cards use dark surfaces and consistently place game artwork, title, current price, optional compare-at price/discount, and region label. The catalog and homepage product grids use five columns on desktop, three on tablet, and two on mobile. Define and reuse the responsive breakpoints rather than setting per-page grid counts independently.

Reusable React components should own consistent styling and behavior: site header/navigation, search bar, promotional banner, section container, responsive product grid/carousel and card, price and discount display, region label, catalog filters/sort controls, seller offer row, footer, and common loading/empty/error states. Feature pages compose these components instead of restyling them individually. Use original GameHub identity, copy, and licensed or original artwork; the palette and common marketplace patterns do not require copying Eneba's brand assets or exact page composition.

## Runtime and configuration

- Serve the built SPA and `/api/v1` through HTTPS on the same origin. In development, use Vite's proxy to forward API requests to PHP.
- Configure database credentials, session settings, a versioned digital-key encryption key ring, and environment-specific options through environment variables. Keep real secrets outside version control.
- Return JSON for API responses, use UTC timestamps, and centralize error formatting and request IDs.
- Add migrations and deterministic seed data before relying on manually edited databases.
- For production, disable verbose PHP errors in responses and log them server-side with request IDs.

## Authentication and cart

The browser receives an opaque random session token in an `HttpOnly`, `SameSite=Lax` cookie. The API stores only its SHA-256 hash in `sessions`, enforces expiry/revocation on every protected request, and rotates the token after login or registration. A separate CSRF cookie/token is double-submitted in a request header and additionally bound to authenticated sessions by hash. Login and registration also require the bootstrapped anonymous CSRF token. Rate-limit counters use hashed client IPs and fixed windows.

The React auth context loads the current user and cart count. The cart is intentionally available to anonymous visitors as well as signed-in users, so it is not wrapped in an auth guard. Checkout is the point where sign-in is required; that authorization must be enforced by both route UX and the checkout API, with the API as the security boundary.

Anonymous carts are server-backed and keyed by a random HttpOnly guest cookie whose SHA-256 hash is stored in the database. Cart entries store only `cart_id`, selected `offer_id`, and quantity. Cart add/update validate current offer eligibility but do not consult digital-key inventory. Every cart read joins current offer data and recalculates totals from its current price; an offer that becomes inactive stays visible as unpriced and removable. On sign-in/registration, merge guest entries into the account cart transactionally and clear the guest cookie.

Checkout requires an authenticated user and CSRF validation. It revalidates current offers and prices, requires one currency, then locks the cart, offers, and enough available key rows in a consistent order. The order snapshots, simulated paid status, key assignments, and cart clear commit atomically; any failure preserves the cart. Per-user idempotency keys make retries return the original order. There is no payment-provider integration. Local/test-only fictional keys are explicitly non-redeemable and encrypted with Sodium `secretbox` using a versioned environment key ring. Key reveal checks paid status and ownership, records an audit event, and returns a no-store response; plaintext is never logged.

## Error and observability conventions

Use consistent HTTP status codes and a JSON error envelope (see `API.md`). Validation failures should identify fields; unexpected failures should not expose SQL, stack traces, session identifiers, or key material. Log authentication events, checkout outcomes, and key reveal events without logging passwords, cookies, or plaintext keys.

## Decisions and rationale

| Decision | Rationale |
|---|---|
| One PHP API and one MySQL database for MVP | Fits the required technologies and avoids distributed-system complexity. |
| Same-origin API and SPA | Makes secure cookie sessions and CSRF controls straightforward. |
| Service/repository separation | Keeps transaction-heavy checkout and independently maintained homepage queries testable. |
| Integer minor-unit prices plus currency code | Avoids floating-point rounding errors; currency is explicit. |
| Opaque server-side sessions | Supports logout/revocation and avoids long-lived browser bearer tokens. |
| CSRF token bound to the session | Prevents cross-site state changes while keeping the session token inaccessible to browser JavaScript. |
| Cart references offers, not client prices | Allows price comparison and fresh totals on every read without trusting browser-submitted amounts. |
| Cart editing independent of key inventory | Keeps active demo offers usable without inventing key stock; checkout remains the stock authority. |
| Database-backed key inventory with application-layer encryption | Keeps inventory and orders transactionally consistent while limiting the value of a database-only leak. |
| Search on MySQL plus a bounded typo-tolerant fallback | Meets the current scale and avoids requiring a separate search service prematurely. |
| No real payment integration in this design | The requested checkout is simulated; payment processing requires separate security, compliance, and product decisions. |
| Semantic design tokens and shared React components | Keeps the specified color palette and sharp geometry consistent across responsive pages and makes later visual changes local. |

