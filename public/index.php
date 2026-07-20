<?php
require_once __DIR__ . '/../src/OrderService.php';

// 1. Set JSON response headers
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // 2. Read raw JSON payload from the request body
    $raw_input = file_get_contents("php://input");
    $data = json_decode($raw_input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid JSON payload."
        ]);
        exit;
    }

    $service = new OrderService();
    $result = $service->process(is_array($data) ? $data : []);

    http_response_code($result['status'] === 'success' ? 201 : 422);
    echo json_encode($result);
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["message" => "Only POST requests allowed."]);
}
?>
