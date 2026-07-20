<?php

class OrderValidator
{
	/**
	 * Validate order payload against required business rules.
	 */
	public function validate(array $data): array
	{
		$errors = [];

		$customerName = trim((string)($data['customer_name'] ?? ''));
		$product = trim((string)($data['product'] ?? ''));
		$quantity = $data['quantity'] ?? null;
		$unitPrice = $data['unit_price'] ?? null;

		if ($customerName === '') {
			$errors['customer_name'] = 'customer_name is required.';
		}

		if ($product === '') {
			$errors['product'] = 'product is required.';
		}

		if (filter_var($quantity, FILTER_VALIDATE_INT) === false || (int)$quantity <= 0) {
			$errors['quantity'] = 'quantity must be an integer greater than zero.';
		}

		if (!is_numeric($unitPrice) || (float)$unitPrice <= 0) {
			$errors['unit_price'] = 'unit_price must be numeric and greater than zero.';
		}

		return [
			'is_valid' => empty($errors),
			'errors' => $errors
		];
	}
}

