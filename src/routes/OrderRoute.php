<?php

require_once __DIR__ . '/../Services/OrderService.php';

class OrderRoute
{
	/**
	 * @return array<string, array<string, callable>>
	 */
	public static function definitions(): array
	{
		return [
			'/orders' => [
				'POST' => static function (): void {
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

					$service = new OrderService();
					$result = $service->process(is_array($data) ? $data : []);

					$isSuccess = ($result['status'] ?? '') === 'success';
					http_response_code($isSuccess ? 201 : 422);

					if ($isSuccess) {
						unset($result['status']);
					}

					echo json_encode($result);
				},
			],
		];
	}
}

