<?php
require_once __DIR__ . '/../src/Api.php';
require_once __DIR__ . '/../src/routes/OrderRoute.php';

header("Content-Type: application/json; charset=UTF-8");

$api = new Api(OrderRoute::definitions());
$api->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
?>
