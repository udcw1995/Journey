<?php

require_once __DIR__ . '/../src/OrderValidator.php';
require_once __DIR__ . '/../src/OrderService.php';

$validator = new OrderValidator();
$service = new OrderService($validator);

$tests = [
    [
        'name' => 'valid payload passes validation',
        'payload' => [
            'customer_name' => 'Alice',
            'product' => 'Keyboard',
            'quantity' => 2,
            'unit_price' => 49.99,
        ],
        'expectValid' => true,
        'expectErrors' => [],
    ],
    [
        'name' => 'customer_name is required',
        'payload' => [
            'customer_name' => '   ',
            'product' => 'Keyboard',
            'quantity' => 2,
            'unit_price' => 49.99,
        ],
        'expectValid' => false,
        'expectErrors' => ['customer_name'],
    ],
    [
        'name' => 'product is required',
        'payload' => [
            'customer_name' => 'Alice',
            'product' => '',
            'quantity' => 2,
            'unit_price' => 49.99,
        ],
        'expectValid' => false,
        'expectErrors' => ['product'],
    ],
    [
        'name' => 'quantity must be integer greater than zero',
        'payload' => [
            'customer_name' => 'Alice',
            'product' => 'Keyboard',
            'quantity' => 0,
            'unit_price' => 49.99,
        ],
        'expectValid' => false,
        'expectErrors' => ['quantity'],
    ],
    [
        'name' => 'quantity rejects non-integer values',
        'payload' => [
            'customer_name' => 'Alice',
            'product' => 'Keyboard',
            'quantity' => '2.5',
            'unit_price' => 49.99,
        ],
        'expectValid' => false,
        'expectErrors' => ['quantity'],
    ],
    [
        'name' => 'unit_price must be numeric and greater than zero',
        'payload' => [
            'customer_name' => 'Alice',
            'product' => 'Keyboard',
            'quantity' => 2,
            'unit_price' => 0,
        ],
        'expectValid' => false,
        'expectErrors' => ['unit_price'],
    ],
    [
        'name' => 'multiple errors returned together',
        'payload' => [
            'customer_name' => '',
            'product' => '',
            'quantity' => -3,
            'unit_price' => 'abc',
        ],
        'expectValid' => false,
        'expectErrors' => ['customer_name', 'product', 'quantity', 'unit_price'],
    ],
];

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    $validation = $validator->validate($test['payload']);

    $isValidMatches = $validation['is_valid'] === $test['expectValid'];
    $errorKeys = array_keys($validation['errors']);
    sort($errorKeys);
    $expectedErrorKeys = $test['expectErrors'];
    sort($expectedErrorKeys);
    $errorsMatch = $errorKeys === $expectedErrorKeys;

    if ($isValidMatches && $errorsMatch) {
        $passed++;
        echo "PASS: {$test['name']}\n";
    } else {
        $failed++;
        echo "FAIL: {$test['name']}\n";
        echo '  Expected valid: ' . ($test['expectValid'] ? 'true' : 'false') . "\n";
        echo '  Actual valid: ' . ($validation['is_valid'] ? 'true' : 'false') . "\n";
        echo '  Expected error fields: ' . json_encode($expectedErrorKeys) . "\n";
        echo '  Actual error fields: ' . json_encode($errorKeys) . "\n";
    }
}

// Service-level success check to ensure validated input is processed as expected.
$serviceResult = $service->process([
    'customer_name' => 'Bob',
    'product' => 'Mouse',
    'quantity' => 3,
    'unit_price' => 10,
]);

if (
    $serviceResult['status'] === 'success'
    && isset($serviceResult['data']['total_price'])
    && $serviceResult['data']['total_price'] === 30.0
) {
    $passed++;
    echo "PASS: service computes total price on valid input\n";
} else {
    $failed++;
    echo "FAIL: service computes total price on valid input\n";
}

echo "\nTotal Passed: {$passed}\n";
echo "Total Failed: {$failed}\n";

exit($failed > 0 ? 1 : 0);
