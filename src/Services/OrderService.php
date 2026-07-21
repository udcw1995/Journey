<?php

require_once __DIR__ . '/../OrderValidator.php';
require_once __DIR__ . '/../Repositories/OrderRepository.php';

class OrderService
{
	private OrderValidator $validator;
	private OrderRepository $repository;

	public function __construct(?OrderValidator $validator = null, ?OrderRepository $repository = null)
	{
		$this->validator = $validator ?? new OrderValidator();
		$this->repository = $repository ?? new OrderRepository();
	}

	/**
	 * Validate incoming order data, persist it, and return a standard response payload.
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

		$customerName = trim((string)($data['customer_name'] ?? ''));
		$product = trim((string)($data['product'] ?? ''));
		$quantity = (int)($data['quantity'] ?? 0);
		$unitPrice = (float)($data['unit_price'] ?? 0.0);
		$total = $quantity * $unitPrice;

		$order = $this->repository->create($customerName, $product, $quantity, $unitPrice, $total);

		return [
			'status' => 'success',
			'message' => 'Order created successfully',
			'order' => $order
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function all(): array
	{
		return $this->repository->findAll();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find(int $id): ?array
	{
		return $this->repository->findById($id);
	}
}

