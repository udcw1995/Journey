<?php

class OrderValidator
{
	private const MAX_NAME_LENGTH = 150;
	private const MAX_PRODUCT_LENGTH = 150;

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
		} elseif (self::length($customerNameRaw) > self::MAX_NAME_LENGTH) {
			$errors['customer_name'] = 'customer_name must not exceed ' . self::MAX_NAME_LENGTH . ' characters.';
		}

		if ($productRaw === null) {
			$errors['product'] = 'product is required.';
		} elseif (!is_string($productRaw)) {
			$errors['product'] = 'product must be a string.';
		} elseif (trim($productRaw) === '') {
			$errors['product'] = 'product is required.';
		} elseif (self::length($productRaw) > self::MAX_PRODUCT_LENGTH) {
			$errors['product'] = 'product must not exceed ' . self::MAX_PRODUCT_LENGTH . ' characters.';
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

	/**
	 * Count string length using mb_strlen() when the mbstring extension is
	 * available, falling back to strlen() otherwise.
	 */
	private static function length(string $value): int
	{
		return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
	}
}

