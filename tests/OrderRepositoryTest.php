<?php

/**
 * Repository-level tests for OrderRepository, run against a dedicated
 * test database (see tests/bootstrap.php) so they never touch
 * production/development data.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Repositories/OrderRepository.php';

$pdo = Database::connect();
$pdo->exec('TRUNCATE TABLE orders');

$repository = new OrderRepository($pdo);

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $message = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "PASS: {$name}\n";
        return;
    }

    $failed++;
    echo "FAIL: {$name}\n";
    if ($message !== '') {
        echo "  {$message}\n";
    }
}

// 1. Creating an order inserts a database row.
$created = $repository->create('Alice', 'Keyboard', 2, 49.99, 99.98);
check(
    'creating an order inserts a database row',
    isset($created['id']) && $created['id'] > 0
);

$rowCount = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
check('orders table has exactly one row after one create() call', $rowCount === 1);

// 2. findById() returns the correct order.
$found = $repository->findById($created['id']);
check(
    'findById() returns the correct order',
    $found !== null
        && $found['customer_name'] === 'Alice'
        && $found['product'] === 'Keyboard'
        && $found['quantity'] === 2
        && $found['unit_price'] === 49.99
        && $found['total'] === 99.98
);

// 3. findById() returns null for a missing order.
$missing = $repository->findById(999999);
check('findById() returns null for a missing order', $missing === null);

// 4. findAll() returns all inserted orders.
$second = $repository->create('Bob', 'Mouse', 1, 25.5, 25.5);
$all = $repository->findAll();
check(
    'findAll() returns all inserted orders',
    count($all) === 2
        && $all[0]['id'] === $created['id']
        && $all[1]['id'] === $second['id']
);

// 5. Product text containing quotes does not break SQL (prepared statements).
$quoted = $repository->create("Nimal Perera", "Men's Wireless Mouse", 1, 10.0, 10.0);
$refetched = $repository->findById($quoted['id']);
check(
    "product text containing quotes does not break SQL (\"Men's Wireless Mouse\")",
    $refetched !== null && $refetched['product'] === "Men's Wireless Mouse"
);

echo "\nTotal Passed: {$passed}\n";
echo "Total Failed: {$failed}\n";

exit($failed > 0 ? 1 : 0);
