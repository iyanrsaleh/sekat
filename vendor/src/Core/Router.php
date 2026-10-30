<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /**
     * @var array<int, array{method: string, pattern: string, regex: string, handler: callable|array, middleware: array<int, Middleware>}>
     */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler, array $middleware = []): self
    {
        return $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable|array $handler, array $middleware = []): self
    {
        return $this->add('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, callable|array $handler, array $middleware = []): self
    {
        return $this->add('PUT', $pattern, $handler, $middleware);
    }

    public function patch(string $pattern, callable|array $handler, array $middleware = []): self
    {
        return $this->add('PATCH', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, callable|array $handler, array $middleware = []): self
    {
        return $this->add('DELETE', $pattern, $handler, $middleware);
    }

    public function any(string $pattern, callable|array $handler, array $middleware = []): self
    {
        return $this->add('*', $pattern, $handler, $middleware);
    }

    public function add(string $method, string $pattern, callable|array $handler, array $middleware = []): self
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => $this->compile($pattern),
            'handler' => $handler,
            'middleware' => $middleware,
        ];

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path(), $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== '*' && $route['method'] !== $request->method()) {
                $allowed[] = $route['method'];
                continue;
            }

            $params = array_filter(
                $matches,
                static fn (int|string $key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY
            );

            foreach ($route['middleware'] as $middleware) {
                $response = $middleware->handle($request);

                if ($response instanceof Response) {
                    return $response;
                }
            }

            return $this->invoke($route['handler'], $params, $request);
        }

        if ($allowed !== []) {
            return Response::error(
                'METHOD_NOT_ALLOWED',
                'Metode ' . $request->method() . ' tidak didukung untuk endpoint ini',
                405
            );
        }

        return Response::error('NOT_FOUND', 'Endpoint tidak ditemukan', 404);
    }

    private function compile(string $pattern): string
    {
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $pattern
        );

        return '#^' . $regex . '$#';
    }

    private function invoke(callable|array $handler, array $params, Request $request): Response
    {
        if (is_array($handler) && is_object($handler[0])) {
            $result = $handler[0]->{$handler[1]}($params, $request);
        } elseif (is_array($handler)) {
            [$class, $method] = $handler;

            if (!class_exists($class)) {
                throw new HttpException(500, 'CONTROLLER_NOT_FOUND', 'Controller tidak ditemukan: ' . $class);
            }

            $controller = new $class();

            if (!method_exists($controller, $method)) {
                throw new HttpException(500, 'ACTION_NOT_FOUND', 'Action tidak ditemukan: ' . $class . '::' . $method);
            }

            $result = $controller->{$method}($params, $request);
        } else {
            $result = $handler($params, $request);
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        return Response::json(null);
    }
}