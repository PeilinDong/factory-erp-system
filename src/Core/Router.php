<?php

declare(strict_types=1);

namespace Erp\Core;

final class Router
{
    /**
     * @var array<string, callable>
     */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET ' . $this->normalize($path)] = $handler;
    }

    public function dispatch(string $method, string $path): string
    {
        $key = strtoupper($method) . ' ' . $this->normalize($path);
        if (!isset($this->routes[$key])) {
            if (!headers_sent()) {
                http_response_code(404);
            }
            return '<h1>404 Not Found</h1>';
        }

        return (string) call_user_func($this->routes[$key]);
    }

    private function normalize(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
