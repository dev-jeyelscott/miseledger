# MiseLedger

MiseLedger is an organization-aware operations platform for inventory-led businesses. It supports inventory master data and stock movements, purchasing and goods receipts, suppliers and supplier pricing, recipe costing, locations and organizations, and subscription billing.

## Stack

- Laravel with PostgreSQL and Redis
- Inertia.js and React
- Vite and Tailwind CSS
- Docker Compose for the local application, worker, scheduler, Vite, PostgreSQL, and Redis services

## Prerequisites

- Docker Engine with Docker Compose

The local development image supplies PHP, Composer, Node.js, and npm.

## Local setup

Build the development image, install project dependencies, create the local environment file when needed, generate an application key, migrate the database, and build frontend assets:

```bash
docker compose build --pull
docker compose run --rm app composer setup
docker compose up -d
```

The application is available at `http://localhost:8002` by default. Vite runs on port `5174` for hot-module replacement.

For later sessions, start the stack with:

```bash
docker compose up -d
```

Follow application, worker, scheduler, and Vite logs:

```bash
docker compose logs -f app worker scheduler vite
```

Stop services while retaining local PostgreSQL and Redis data:

```bash
docker compose down
```

## Validation

Run these from the project root while the stack is running:

```bash
docker compose exec app composer lint:check
docker compose exec app npm run lint:check
docker compose exec app npm run format:check
docker compose exec app npm run types:check
docker compose exec app composer test
docker compose exec app npm run build
```

`composer ci:check` combines frontend linting, formatting and type checks, an asset build, and the test suite.

## Contributing

Read [AGENTS.md](AGENTS.md) before making changes. It contains the project's implementation conventions, testing expectations, and contributor guidance.
