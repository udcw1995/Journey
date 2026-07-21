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

		$pathRoutes = null;
		$params = [];

		foreach ($this->routes as $pattern => $methods) {
			$matchParams = $this->match($pattern, $requestPath);
			if ($matchParams !== null) {
				$pathRoutes = $methods;
				$params = $matchParams;
				break;
			}
		}

		if ($pathRoutes === null) {
			$this->respondError(404, 'Endpoint not found.');
			return;
		}

		if (!array_key_exists($requestMethod, $pathRoutes)) {
			$allowedMethods = array_keys($pathRoutes);
			sort($allowedMethods);

			$this->respondError(405, 'Method not allowed for this endpoint.', [
				'Allow: ' . implode(', ', $allowedMethods),
			]);
			return;
		}

		try {
			$pathRoutes[$requestMethod]($params);
		} catch (\Throwable $e) {
			error_log($e->getMessage());
			$this->respondError(500, 'An internal server error occurred.');
		}
	}

	/**
	 * Match a route pattern (e.g. "/orders/{id}") against a request path,
	 * returning captured named parameters, or null when it doesn't match.
	 *
	 * @return array<string, string>|null
	 */
	private function match(string $pattern, string $path): ?array
	{
		$regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);

		if (preg_match('#^' . $regex . '$#', $path, $matches) !== 1) {
			return null;
		}

		$params = [];
		foreach ($matches as $key => $value) {
			if (is_string($key)) {
				$params[$key] = $value;
			}
		}

		return $params;
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

