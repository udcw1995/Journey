<?php

/**
 * Endpoint-level integration tests for order API behavior.
 *
 * Runs against a dedicated test database (see tests/bootstrap.php) via
 * the PHP built-in server, so tests never touch production/development
 * data.
 */

require_once __DIR__ . '/bootstrap.php';

Database::connect()->exec('TRUNCATE TABLE orders');

$projectRoot = dirname(__DIR__);
$publicDir = $projectRoot . '/public';

$testEnv = getenv();
$testEnv['DB_NAME'] = getenv('DB_NAME') ?: 'backend_journey_test';

[$port, $socket] = reserveFreePort();
fclose($socket);

$command = sprintf('php -S 127.0.0.1:%d -t %s', $port, escapeshellarg($publicDir));
$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($command, $descriptorSpec, $pipes, $projectRoot, $testEnv);
if (!is_resource($process)) {
    fwrite(STDERR, "Failed to start PHP built-in server.\n");
    exit(1);
}

$baseUrl = sprintf('http://127.0.0.1:%d', $port);
if (!waitForServer($baseUrl, 30, 100000)) {
    $stderr = stream_get_contents($pipes[2]);
    cleanupProcess($process, $pipes);
    fwrite(STDERR, "Server did not become ready.\n" . $stderr . "\n");
    exit(1);
}

$tests = [
    [
        'name' => 'valid POST /orders returns 201',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'POST', '/orders', [
                'customer_name' => 'Nimal Perera',
                'product' => 'Wireless Mouse',
                'quantity' => 2,
                'unit_price' => 3500,
            ]);

            $ok = $response['status'] === 201;
            return [$ok, 'Expected HTTP 201 for valid order request.'];
        },
    ],
    [
        'name' => 'missing fields return 422',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'POST', '/orders', [
                'customer_name' => 'Nimal Perera',
            ]);

            $ok = $response['status'] === 422;
            return [$ok, 'Expected HTTP 422 when required fields are missing.'];
        },
    ],
    [
        'name' => 'malformed JSON returns 400',
        'assert' => static function (string $baseUrl): array {
            $response = requestRaw($baseUrl, 'POST', '/orders', '{"customer_name":"Nimal"');

            $ok = $response['status'] === 400;
            return [$ok, 'Expected HTTP 400 for malformed JSON payload.'];
        },
    ],
    [
        'name' => 'DELETE /orders returns 405 with Allow header',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'DELETE', '/orders', null);
            $allow = strtolower($response['headers']['allow'] ?? '');

            $ok = $response['status'] === 405 && str_contains($allow, 'get') && str_contains($allow, 'post');
            return [$ok, 'Expected HTTP 405 and Allow header listing GET, POST for DELETE /orders.'];
        },
    ],
    [
        'name' => 'POST /anything returns 404',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'POST', '/anything', [
                'customer_name' => 'Nimal Perera',
                'product' => 'Wireless Mouse',
                'quantity' => 2,
                'unit_price' => 3500,
            ]);

            $ok = $response['status'] === 404;
            return [$ok, 'Expected HTTP 404 for unknown endpoint.'];
        },
    ],
    [
        'name' => 'boolean quantity returns 422',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'POST', '/orders', [
                'customer_name' => 'Nimal Perera',
                'product' => 'Wireless Mouse',
                'quantity' => true,
                'unit_price' => 3500,
            ]);

            $ok = $response['status'] === 422;
            return [$ok, 'Expected HTTP 422 when quantity is boolean.'];
        },
    ],
    [
        'name' => 'array customer_name returns 422',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'POST', '/orders', [
                'customer_name' => ['Nimal', 'Perera'],
                'product' => 'Wireless Mouse',
                'quantity' => 2,
                'unit_price' => 3500,
            ]);

            $ok = $response['status'] === 422;
            return [$ok, 'Expected HTTP 422 when customer_name is array.'];
        },
    ],
    [
        'name' => 'object customer_name returns 422',
        'assert' => static function (string $baseUrl): array {
            $response = requestRaw(
                $baseUrl,
                'POST',
                '/orders',
                json_encode([
                    'customer_name' => ['first' => 'Nimal', 'last' => 'Perera'],
                    'product' => 'Wireless Mouse',
                    'quantity' => 2,
                    'unit_price' => 3500,
                ])
            );

            $ok = $response['status'] === 422;
            return [$ok, 'Expected HTTP 422 when customer_name is object.'];
        },
    ],
    [
        'name' => 'successful response matches required contract',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'POST', '/orders', [
                'customer_name' => 'Nimal Perera',
                'product' => 'Wireless Mouse',
                'quantity' => 2,
                'unit_price' => 3500,
            ]);

            $body = json_decode($response['body'], true);
            $order = $body['order'] ?? [];

            $ok = $response['status'] === 201
                && ($body['message'] ?? null) === 'Order created successfully'
                && isset($order['id'])
                && $order['customer_name'] === 'Nimal Perera'
                && $order['product'] === 'Wireless Mouse'
                && $order['quantity'] === 2
                && $order['unit_price'] === 3500
                && $order['total'] === 7000
                && isset($order['created_at']);

            return [$ok, 'Expected HTTP 201 and success response contract with id/total/created_at.'];
        },
    ],
    [
        'name' => 'successful creation returns a Location header',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'POST', '/orders', [
                'customer_name' => 'Nimal Perera',
                'product' => 'Wireless Mouse',
                'quantity' => 2,
                'unit_price' => 3500,
            ]);

            $body = json_decode($response['body'], true);
            $id = $body['order']['id'] ?? null;
            $location = $response['headers']['location'] ?? '';

            $ok = $response['status'] === 201 && $id !== null && $location === "/orders/{$id}";
            return [$ok, "Expected Location header \"/orders/{$id}\", got \"{$location}\"."];
        },
    ],
    [
        'name' => 'created order can be retrieved with GET /orders/{id}',
        'assert' => static function (string $baseUrl): array {
            $created = requestJson($baseUrl, 'POST', '/orders', [
                'customer_name' => 'Kasun Silva',
                'product' => 'Mechanical Keyboard',
                'quantity' => 1,
                'unit_price' => 12000,
            ]);
            $id = json_decode($created['body'], true)['order']['id'] ?? null;

            $response = requestJson($baseUrl, 'GET', "/orders/{$id}", null);
            $order = json_decode($response['body'], true)['order'] ?? [];

            $ok = $response['status'] === 200
                && $order['id'] === $id
                && $order['customer_name'] === 'Kasun Silva'
                && $order['total'] === 12000;

            return [$ok, 'Expected the created order to be retrievable via GET /orders/{id}.'];
        },
    ],
    [
        'name' => 'GET /orders returns a list including created orders',
        'assert' => static function (string $baseUrl): array {
            requestJson($baseUrl, 'POST', '/orders', [
                'customer_name' => 'Amara Fernando',
                'product' => 'USB Cable',
                'quantity' => 3,
                'unit_price' => 500,
            ]);

            $response = requestJson($baseUrl, 'GET', '/orders', null);
            $orders = json_decode($response['body'], true)['orders'] ?? null;

            $ok = $response['status'] === 200 && is_array($orders) && count($orders) > 0;
            return [$ok, 'Expected GET /orders to return a 200 with a non-empty "orders" list.'];
        },
    ],
    [
        'name' => 'GET /orders/{id} returns 404 for a missing order',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'GET', '/orders/999999', null);
            $body = json_decode($response['body'], true);

            $ok = $response['status'] === 404 && ($body['message'] ?? '') === 'Order not found.';
            return [$ok, 'Expected HTTP 404 for a missing order id.'];
        },
    ],
    [
        'name' => 'GET /orders/abc returns 404 (non-numeric id)',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'GET', '/orders/abc', null);
            $ok = $response['status'] === 404;
            return [$ok, 'Expected HTTP 404 for non-numeric order id.'];
        },
    ],
    [
        'name' => 'GET /orders/-1 returns 404 (negative id)',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'GET', '/orders/-1', null);
            $ok = $response['status'] === 404;
            return [$ok, 'Expected HTTP 404 for negative order id.'];
        },
    ],
    [
        'name' => 'GET /orders/0 returns 404 (zero id)',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'GET', '/orders/0', null);
            $ok = $response['status'] === 404;
            return [$ok, 'Expected HTTP 404 for order id 0.'];
        },
    ],
];

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    [$ok, $message] = $test['assert']($baseUrl);

    if ($ok) {
        $passed++;
        echo "PASS: {$test['name']}\n";
    } else {
        $failed++;
        echo "FAIL: {$test['name']}\n";
        echo "  {$message}\n";
    }
}

cleanupProcess($process, $pipes);

// Database failure returns a safe 500 response (not raw SQL/driver errors).
// Uses a second server instance pointed at a non-existent database so any
// query fails at connection/execution time.
[$failPort, $failSocket] = reserveFreePort();
fclose($failSocket);

$failEnv = getenv();
$failEnv['DB_NAME'] = 'backend_journey_does_not_exist';

$failCommand = sprintf('php -S 127.0.0.1:%d -t %s', $failPort, escapeshellarg($publicDir));
$failProcess = proc_open($failCommand, $descriptorSpec, $failPipes, $projectRoot, $failEnv);

if (!is_resource($failProcess)) {
    $failed++;
    echo "FAIL: database failure returns a safe 500 response\n";
    echo "  Could not start the failure-mode server.\n";
} else {
    $failBaseUrl = sprintf('http://127.0.0.1:%d', $failPort);

    if (!waitForServer($failBaseUrl, 30, 100000)) {
        $failed++;
        echo "FAIL: database failure returns a safe 500 response\n";
        echo "  Failure-mode server did not become ready.\n";
    } else {
        $response = requestJson($failBaseUrl, 'POST', '/orders', [
            'customer_name' => 'Nimal Perera',
            'product' => 'Wireless Mouse',
            'quantity' => 2,
            'unit_price' => 3500,
        ]);
        $body = json_decode($response['body'], true);

        $ok = $response['status'] === 500
            && ($body['status'] ?? '') === 'error'
            && ($body['message'] ?? '') === 'An internal server error occurred.'
            && stripos($response['body'], 'SQLSTATE') === false;

        if ($ok) {
            $passed++;
            echo "PASS: database failure returns a safe 500 response\n";
        } else {
            $failed++;
            echo "FAIL: database failure returns a safe 500 response\n";
            echo "  Got status {$response['status']} with body: {$response['body']}\n";
        }
    }

    cleanupProcess($failProcess, $failPipes);
}

echo "\nTotal Passed: {$passed}\n";
echo "Total Failed: {$failed}\n";

exit($failed > 0 ? 1 : 0);

/**
 * @return array{0:int,1:resource}
 */
function reserveFreePort(): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($socket === false) {
        throw new RuntimeException('Failed to reserve port: ' . $errstr);
    }

    $name = stream_socket_get_name($socket, false);
    if ($name === false) {
        throw new RuntimeException('Failed to resolve reserved port.');
    }

    $parts = explode(':', $name);
    $port = (int)end($parts);

    return [$port, $socket];
}

function waitForServer(string $baseUrl, int $maxAttempts, int $sleepMicros): bool
{
    for ($i = 0; $i < $maxAttempts; $i++) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true,
                'timeout' => 1,
            ],
        ]);

        $result = @file_get_contents($baseUrl . '/orders', false, $context);
        if ($result !== false || !empty($http_response_header)) {
            return true;
        }

        usleep($sleepMicros);
    }

    return false;
}

/**
 * @param array<string,mixed>|null $payload
 * @return array{status:int,headers:array<string,string>,body:string}
 */
function requestJson(string $baseUrl, string $method, string $path, ?array $payload): array
{
    $body = $payload === null ? '' : json_encode($payload);
    return requestRaw($baseUrl, $method, $path, $body);
}

/**
 * @return array{status:int,headers:array<string,string>,body:string}
 */
function requestRaw(string $baseUrl, string $method, string $path, string $body): array
{
    $headers = [
        'Content-Type: application/json',
        'Connection: close',
    ];

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 3,
        ],
    ]);

    $result = @file_get_contents($baseUrl . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];

    return [
        'status' => parseStatusCode($responseHeaders),
        'headers' => parseHeaders($responseHeaders),
        'body' => $result === false ? '' : $result,
    ];
}

/**
 * @param list<string> $headers
 */
function parseStatusCode(array $headers): int
{
    if (empty($headers)) {
        return 0;
    }

    if (preg_match('/HTTP\/\S+\s+(\d{3})/', $headers[0], $matches) === 1) {
        return (int)$matches[1];
    }

    return 0;
}

/**
 * @param list<string> $headers
 * @return array<string,string>
 */
function parseHeaders(array $headers): array
{
    $result = [];

    foreach ($headers as $headerLine) {
        if (strpos($headerLine, ':') === false) {
            continue;
        }

        [$name, $value] = explode(':', $headerLine, 2);
        $result[strtolower(trim($name))] = trim($value);
    }

    return $result;
}

/**
 * @param array<int, resource> $pipes
 */
function cleanupProcess($process, array $pipes): void
{
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
}
