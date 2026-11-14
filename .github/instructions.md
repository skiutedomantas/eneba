# GameHub Marketplace - Development Instructions

## Project overview

Build a full-stack digital products marketplace inspired by Eneba.

## Technology

- React + TypeScript
- Vite
- Tailwind CSS
- PHP 8.3+
- MySQL 8+
- PDO
- REST API

## Architecture

- Separate frontend and backend.
- Use reusable React components.
- Use TypeScript interfaces for API data.
- Use repositories for database operations.
- Keep frontend API requests in service modules.
- Use environment variables for configuration.

## Design

- Eneba-inspired dark theme.
- Purple and violet accents.
- Responsive layouts.
- Reusable product cards.
- Consistent typography and spacing.

## Security

- Use password_hash() and password_verify().
- Use prepared SQL statements.
- Validate all backend requests.
- Protect private endpoints.
- Never expose digital keys before successful payment.
- Never trust frontend prices.
- Use database transactions for checkout.

## Collaboration rules

- Explain important code changes.
- Explain modified files.
- Do not implement everything in one task.
- Ask before making major architectural changes.
- Do not introduce unnecessary dependencies.
- Run tests after important changes.
- Never claim something works without verifying it.

## Assignment requirements

The final application must include:

- Homepage with independently fetched backend sections.
- Product catalog with filters and sorting.
- Global typo-tolerant search.
- Product pages with multiple seller offers.
- Authentication.
- Shopping cart.
- Simulated checkout.
- Digital key delivery.
- Order history.
