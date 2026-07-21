<?php

class OrderValidator
{
	/**
	 * Validate order payload against required business rules.
	 */
	public function validate(array $data): array
	{
		$errors = [];

		$customerNameRaw = $data['customer_name'] ?? null;
		$productRaw = $data['product'] ?? null;
		$quantity = $data['quantity'] ?? null;
		$unitPrice = $data['unit_price'] ?? null;

		if ($customerNameRaw === null) {
			$errors['customer_name'] = 'customer_name is required.';
		} elseif (!is_string($customerNameRaw)) {
			$errors['customer_name'] = 'customer_name must be a string.';
		} elseif (trim($customerNameRaw) === '') {
			$errors['customer_name'] = 'customer_name is required.';
		}

		if ($productRaw === null) {
			$errors['product'] = 'product is required.';
		} elseif (!is_string($productRaw)) {
			$errors['product'] = 'product must be a string.';
		} elseif (trim($productRaw) === '') {
			$errors['product'] = 'product is required.';
		}

		if (!is_int($quantity) || $quantity <= 0) {
			$errors['quantity'] = 'quantity must be an integer greater than zero.';
		}

		if ((!is_int($unitPrice) && !is_float($unitPrice)) || $unitPrice <= 0) {
			$errors['unit_price'] = 'unit_price must be numeric and greater than zero.';
		}

		return [
			'is_valid' => empty($errors),
			'errors' => $errors
		];
	}
}

