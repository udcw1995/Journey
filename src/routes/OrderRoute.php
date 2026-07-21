<?php

require_once __DIR__ . '/../Services/OrderService.php';

class OrderRoute
{
	/**
	 * @return array<string, array<string, callable>>
	 */
	public static function definitions(): array
	{
		$service = new OrderService();

		return [
			'/orders' => [
				'POST' => static function () use ($service): void {
					$rawInput = file_get_contents('php://input');
					$data = json_decode($rawInput, true);

					if (json_last_error() !== JSON_ERROR_NONE) {
						http_response_code(400);
						echo json_encode([
							'status' => 'error',
							'message' => 'Invalid JSON payload.'
						]);
						return;
					}

					$result = $service->process(is_array($data) ? $data : []);
					$isSuccess = ($result['status'] ?? '') === 'success';

					if ($isSuccess) {
						unset($result['status']);
						http_response_code(201);
						header('Location: /orders/' . $result['order']['id']);
					} else {
						http_response_code(422);
					}

					echo json_encode($result);
				},
				'GET' => static function () use ($service): void {
					http_response_code(200);
					echo json_encode(['orders' => $service->all()]);
				},
			],
			'/orders/{id}' => [
				'GET' => static function (array $params) use ($service): void {
					$rawId = $params['id'] ?? '';
					$isValidId = ctype_digit($rawId) && (int)$rawId > 0;
					$order = $isValidId ? $service->find((int)$rawId) : null;

					if ($order === null) {
						http_response_code(404);
						echo json_encode([
							'status' => 'error',
							'message' => 'Order not found.'
						]);
						return;
					}

					http_response_code(200);
					echo json_encode(['order' => $order]);
				},
			],
		];
	}
}

