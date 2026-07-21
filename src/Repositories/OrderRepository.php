<?php

require_once __DIR__ . '/../../config/Database.php';

class OrderRepository
{
	private ?PDO $pdo;

	/**
	 * The PDO connection is not established here. It is deferred until the
	 * first query (see connection()) so that constructing the repository
	 * (which happens while defining routes, before request dispatch) never
	 * triggers a database connection outside of Api::handle()'s error
	 * boundary.
	 */
	public function __construct(?PDO $pdo = null)
	{
		$this->pdo = $pdo;
	}

	private function connection(): PDO
	{
		return $this->pdo ??= Database::connect();
	}

	/**
	 * Insert a new order using a parameterized statement and return the
	 * persisted row (including the generated id and created_at).
	 *
	 * @return array<string, mixed>
	 */
	public function create(string $customerName, string $product, int $quantity, float $unitPrice, float $total): array
	{
		$statement = $this->connection()->prepare(
			'INSERT INTO orders (customer_name, product, quantity, unit_price, total) '
			. 'VALUES (:customer_name, :product, :quantity, :unit_price, :total)'
		);

		$statement->execute([
			'customer_name' => $customerName,
			'product' => $product,
			'quantity' => $quantity,
			'unit_price' => $unitPrice,
			'total' => $total,
		]);

		return $this->findById((int)$this->connection()->lastInsertId()) ?? [];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function findAll(): array
	{
		$statement = $this->connection()->query(
			'SELECT id, customer_name, product, quantity, unit_price, total, created_at '
			. 'FROM orders ORDER BY id ASC'
		);

		return array_map([$this, 'format'], $statement->fetchAll());
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findById(int $id): ?array
	{
		$statement = $this->connection()->prepare(
			'SELECT id, customer_name, product, quantity, unit_price, total, created_at '
			. 'FROM orders WHERE id = :id'
		);
		$statement->execute(['id' => $id]);

		$row = $statement->fetch();

		return $row === false ? null : $this->format($row);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function format(array $row): array
	{
		return [
			'id' => (int)$row['id'],
			'customer_name' => $row['customer_name'],
			'product' => $row['product'],
			'quantity' => (int)$row['quantity'],
			'unit_price' => (float)$row['unit_price'],
			'total' => (float)$row['total'],
			'created_at' => $row['created_at'],
		];
	}
}
