# Order API

## Purpose

A small plain-PHP API demonstrating routing, JSON request handling,
validation, services, HTTP responses, and integration testing.

## Requirements

- PHP 8.0 or newer

## Start the application

php -S localhost:8000 -t public

## Endpoint

POST /orders

## Example request

curl -X POST http://localhost:8000/orders \
  -H "Content-Type: application/json" \
  -d '{
    "customer_name": "Nimal Perera",
    "product": "Wireless Mouse",
    "quantity": 2,
    "unit_price": 3500
  }'

## Run tests

php tests/OrderValidationTest.php
php tests/OrderEndpointTest.php