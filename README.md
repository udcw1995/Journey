# Order API

## Purpose

A small plain-PHP API demonstrating routing, JSON request handling,
validation, a service/repository architecture backed by MySQL via PDO,
and both unit and integration testing. It exposes endpoints to create,
list, and retrieve orders, persisting them in a MySQL database.

## Requirements

- PHP 8.0 or newer
- The `pdo_mysql` PHP extension enabled
- A MySQL (or compatible) server

## Directory structure

```
config/
  Database.php              PDO connection factory (loads .env, builds PDO)
database/
  migrations/
    001_create_orders_table.sql   Schema for the orders table
  migrate.php                     PHP migration runner (plain + --fresh)
public/
  index.php                       Front controller / entry point
src/
  Api.php                         Router + central error handling (404/405/500)
  OrderValidator.php               Request payload validation rules
  Repositories/
    OrderRepository.php            SQL access (prepared statements only)
  Services/
    OrderService.php                Orchestrates validation + persistence
  routes/
    OrderRoute.php                  Route definitions for /orders endpoints
tests/
  bootstrap.php                    Points tests at the test database
  OrderValidationTest.php          Unit tests for OrderValidator/OrderService
  OrderRepositoryTest.php          Repository tests (real test DB)
  OrderEndpointTest.php            HTTP integration tests (real test DB)
.env.example                       Template for local environment variables
.env                                Your local environment variables (git-ignored)
```

## Database setup

1. Create the database:

   ```sql
   CREATE DATABASE backend_journey
       CHARACTER SET utf8mb4
       COLLATE utf8mb4_unicode_ci;
   ```

2. Run the migration to create the `orders` table.

   If you have the `mysql` CLI installed:

   ```
   mysql -u root -p backend_journey < database/migrations/001_create_orders_table.sql
   ```

   Otherwise, use the included PHP migration runner (uses the same
   `Database::connect()` PDO connection and your `.env` settings):

   ```
   php database/migrate.php
   ```

   Both approaches run every `*.sql` file in `database/migrations/` once,
   in order. Since the table has no `IF NOT EXISTS` guard, running it
   again after the table already exists will fail with a "table already
   exists" error — this is expected.

   To drop and recreate all migrated tables from scratch (e.g. after
   changing a migration file), run it fresh instead:

   ```
   php database/migrate.php --fresh
   ```

   This drops each table found in `database/migrations/*.sql` (in
   reverse order, derived from each file's `create_<name>_table.sql`
   naming) and then re-runs every migration file in order.
   **This is destructive — all data in those tables is lost.**

3. Create a separate test database, used exclusively by the automated
   test suite so tests never touch development data:

   ```sql
   CREATE DATABASE backend_journey_test
       CHARACTER SET utf8mb4
       COLLATE utf8mb4_unicode_ci;
   ```

   Then migrate it the same way, pointing the runner at the test
   database:

   ```
   $env:DB_NAME='backend_journey_test'; php database/migrate.php --fresh
   ```

## Environment variables

Copy `.env.example` to `.env` and fill in your local database credentials:

```
cp .env.example .env
```

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=backend_journey
DB_USER=your_username
DB_PASS=your_password
```

`config/Database.php` automatically loads values from `.env` into the
process environment (without overriding any variable already set
externally) and uses them to build the PDO connection. `.env` is
git-ignored — never commit real credentials.

To test the connection directly:

```
php -r "require 'config/Database.php'; var_dump(Database::connect()->query('SELECT 1')->fetchColumn());"
```

A successful connection prints `string(1) "1"`. If it fails, PHP throws
a `PDOException` with the connection error (e.g. wrong credentials,
wrong port, or the database/server not being available).

## Start the application

```
php -S localhost:8000 -t public
```

## Available endpoints

| Method | Path          | Description             |
|--------|---------------|--------------------------|
| POST   | `/orders`     | Create a new order       |
| GET    | `/orders`     | List all orders          |
| GET    | `/orders/{id}`| Retrieve a single order   |

Unknown paths return `404`. Known paths called with an unsupported
method return `405` with an `Allow` header listing the supported
methods. Unexpected server-side failures (e.g. a database outage)
return a safe `500` response instead of leaking internal error details.

### Create an order — `POST /orders`

Request:

```
curl -X POST http://localhost:8000/orders \
  -H "Content-Type: application/json" \
  -d '{
    "customer_name": "Nimal Perera",
    "product": "Wireless Mouse",
    "quantity": 2,
    "unit_price": 3500
  }'
```

Success response:

```
HTTP/1.1 201 Created
Location: /orders/1

{
  "message": "Order created successfully",
  "order": {
    "id": 1,
    "customer_name": "Nimal Perera",
    "product": "Wireless Mouse",
    "quantity": 2,
    "unit_price": 3500,
    "total": 7000,
    "created_at": "2026-07-21 15:30:00"
  }
}
```

Validation failure (e.g. missing/invalid fields):

```
HTTP/1.1 422 Unprocessable Entity

{
  "status": "error",
  "message": "Validation failed.",
  "errors": {
    "quantity": "quantity must be an integer greater than zero."
  }
}
```

Malformed JSON body:

```
HTTP/1.1 400 Bad Request

{
  "status": "error",
  "message": "Invalid JSON payload."
}
```

### List orders — `GET /orders`

```
HTTP/1.1 200 OK

{
  "orders": [
    {
      "id": 1,
      "customer_name": "Nimal Perera",
      "product": "Wireless Mouse",
      "quantity": 2,
      "unit_price": 3500,
      "total": 7000,
      "created_at": "2026-07-21 15:30:00"
    }
  ]
}
```

When there are no orders:

```
{
  "orders": []
}
```

### Retrieve one order — `GET /orders/{id}`

```
HTTP/1.1 200 OK

{
  "order": {
    "id": 1,
    "customer_name": "Nimal Perera",
    "product": "Wireless Mouse",
    "quantity": 2,
    "unit_price": 3500,
    "total": 7000,
    "created_at": "2026-07-21 15:30:00"
  }
}
```

When the order does not exist, or `{id}` is not a positive integer
(e.g. `abc`, `-1`, or `0`):

```
HTTP/1.1 404 Not Found

{
  "status": "error",
  "message": "Order not found."
}
```

## Validation rules

- `customer_name`: required, non-empty string, maximum 150 characters
  (measured with `mb_strlen()` when available, so multi-byte
  characters count as one character each).
- `product`: required, non-empty string, maximum 150 characters (same
  length rule as above).
- `quantity`: required, integer, greater than zero.
- `unit_price`: required, integer or float, greater than zero.
- Malformed JSON in the request body returns `400`.
- Valid JSON with values that fail the rules above returns `422` with
  a per-field `errors` object.

## Run tests

Automated tests run against a dedicated `backend_journey_test`
database (see "Database setup" above) — never against
development/production data. `tests/bootstrap.php` forces `DB_NAME` to
the test database before any connection is made.

```
php tests/OrderValidationTest.php
php tests/OrderRepositoryTest.php
php tests/OrderEndpointTest.php
```

- `OrderValidationTest.php` — unit tests for `OrderValidator` rules
  (required fields, types, max length, booleans rejected as numbers,
  etc.) plus one service-level success check.
- `OrderRepositoryTest.php` — exercises `OrderRepository` directly
  against the test database: insert, `findById()` (found and missing),
  `findAll()`, and that a product name containing a quote
  (`Men's Wireless Mouse`) round-trips correctly, proving prepared
  statements are used rather than string concatenation.
- `OrderEndpointTest.php` — full HTTP integration tests. Spins up the
  PHP built-in server against `public/`, exercises `POST /orders`,
  `GET /orders`, `GET /orders/{id}`, invalid IDs, unknown routes,
  unsupported methods, malformed JSON, validation failures, and a
  simulated database outage (pointed at a non-existent database) to
  confirm a safe `500` response is returned instead of a raw SQL error.

## Key design decisions

- **Layered architecture**: `Api` (routing + error boundary) →
  `OrderRoute` (HTTP-facing handlers) → `OrderService` (validation +
  orchestration) → `OrderRepository` (SQL) → `Database` (PDO factory).
  Each layer has one responsibility, which keeps validation, HTTP
  concerns, and SQL isolated from each other.
- **Prepared statements everywhere**: all SQL in `OrderRepository` uses
  `PDO::prepare()` with bound named parameters — request data is never
  concatenated into a SQL string.
- **Lazy database connection**: `OrderRepository` defers calling
  `Database::connect()` until the first query, instead of connecting
  in its constructor. Route definitions (and therefore
  `new OrderService()` / `new OrderRepository()`) are built once per
  request before `Api::handle()` runs, so an eager connection would
  happen outside of `Api::handle()`'s error boundary and could crash
  the request with a raw PHP error instead of a safe `500` response.
- **Central error handling**: `Api::handle()` wraps route dispatch in a
  single `try`/`catch (\Throwable $e)`. Internal errors are logged with
  `error_log($e->getMessage())` for developers, while the client only
  ever receives `{"status":"error","message":"An internal server error
  occurred."}` with a `500` status — database errors are never exposed.
- **Storing `total` at creation time**: `total` (`quantity × unit_price`)
  is computed once and persisted, rather than calculated on every read.
  This is acceptable here because it records the order's total *as it
  was at the time of purchase*. If `unit_price` were ever edited later
  (e.g. a price correction), a stored `total` stays historically
  accurate, whereas a derived total would silently change past orders.
  The trade-off is that `total` can drift out of sync with
  `quantity × unit_price` if a row is ever updated directly without
  recomputing it — there is currently no update endpoint, so this risk
  doesn't yet apply, but it would need to be revisited if orders become
  editable.
