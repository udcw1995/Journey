<?php

require_once __DIR__ . '/OrderValidator.php';

class OrderService
{
	private OrderValidator $validator;

	public function __construct(?OrderValidator $validator = null)
	{
		$this->validator = $validator ?? new OrderValidator();
	}

	/**
	 * Process incoming order data and return a standard response payload.
	 */
	public function process(array $data): array
	{
		$validation = $this->validator->validate($data);
		if (!$validation['is_valid']) {
			return [
				'status' => 'error',
				'message' => 'Validation failed.',
				'errors' => $validation['errors']
			];
		}

		$customerName = htmlspecialchars(trim((string)($data['customer_name'] ?? '')));
		$product = htmlspecialchars(trim((string)($data['product'] ?? '')));
		$quantity = (int)($data['quantity'] ?? 0);
		$unitPrice = (float)($data['unit_price'] ?? 0.0);

		return [
			'status' => 'success',
			'message' => 'Order processed successfully.',
			'data' => [
				'customer_name' => $customerName,
				'product' => $product,
				'quantity' => $quantity,
				'unit_price' => $unitPrice,
				'total_price' => $quantity * $unitPrice
			]
		];
	}
}

