<?php

class Api
{
	/** @var array<string, array<string, callable>> */
	private array $routes;

	/**
	 * @param array<string, array<string, callable>> $routes
	 */
	public function __construct(array $routes)
	{
		$this->routes = $routes;
	}

	public function handle(string $requestMethod, string $requestUri): void
	{
		$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';

		if (!array_key_exists($requestPath, $this->routes)) {
			$this->respondError(404, 'Endpoint not found.');
			return;
		}

		$pathRoutes = $this->routes[$requestPath];
		if (!array_key_exists($requestMethod, $pathRoutes)) {
			$allowedMethods = array_keys($pathRoutes);
			sort($allowedMethods);

			$this->respondError(405, 'Method not allowed for this endpoint.', [
				'Allow: ' . implode(', ', $allowedMethods),
			]);
			return;
		}

		$pathRoutes[$requestMethod]();
	}

	/**
	 * @param list<string> $headers
	 */
	private function respondError(int $statusCode, string $message, array $headers = []): void
	{
		http_response_code($statusCode);

		foreach ($headers as $headerLine) {
			header($headerLine);
		}

		echo json_encode([
			'status' => 'error',
			'message' => $message,
		]);
	}
}

