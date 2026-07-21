<?php

/**
 * Endpoint-level integration tests for order API behavior.
 */

$projectRoot = dirname(__DIR__);
$publicDir = $projectRoot . '/public';

[$port, $socket] = reserveFreePort();
fclose($socket);

$command = sprintf('php -S 127.0.0.1:%d -t %s', $port, escapeshellarg($publicDir));
$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($command, $descriptorSpec, $pipes, $projectRoot);
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
        'name' => 'GET /orders returns 405 with Allow: POST',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'GET', '/orders', null);
            $allow = strtolower($response['headers']['allow'] ?? '');

            $ok = $response['status'] === 405 && $allow === 'post';
            return [$ok, 'Expected HTTP 405 and Allow: POST for GET /orders.'];
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
        'name' => 'successful response matches required contract exactly',
        'assert' => static function (string $baseUrl): array {
            $response = requestJson($baseUrl, 'POST', '/orders', [
                'customer_name' => 'Nimal Perera',
                'product' => 'Wireless Mouse',
                'quantity' => 2,
                'unit_price' => 3500,
            ]);

            $actualBody = json_decode($response['body'], true);
            $expectedBody = [
                'message' => 'Order created successfully',
                'order' => [
                    'customer_name' => 'Nimal Perera',
                    'product' => 'Wireless Mouse',
                    'quantity' => 2,
                    'unit_price' => 3500,
                    'total' => 7000,
                ],
            ];

            $ok = $response['status'] === 201 && $actualBody === $expectedBody;
            return [$ok, 'Expected HTTP 201 and exact success response contract.'];
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
