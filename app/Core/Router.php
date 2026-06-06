<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Simple pattern router with parameter support.
 *   $router->get('/bounces/{id}', [Controller::class, 'show']);
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:mixed, middleware:array}> */
    private array $routes = [];
    private array $groupStack = [];

    public function get(string $pattern, $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function any(string $pattern, $handler, array $middleware = []): void
    {
        $this->add('ANY', $pattern, $handler, $middleware);
    }

    public function group(array $opts, callable $cb): void
    {
        $this->groupStack[] = $opts;
        $cb($this);
        array_pop($this->groupStack);
    }

    private function add(string $method, string $pattern, $handler, array $middleware): void
    {
        $prefix = '';
        $stackMiddleware = [];
        foreach ($this->groupStack as $g) {
            $prefix .= rtrim($g['prefix'] ?? '', '/');
            if (!empty($g['middleware'])) {
                $stackMiddleware = array_merge($stackMiddleware, (array)$g['middleware']);
            }
        }
        $fullPattern = '/' . trim($prefix . '/' . trim($pattern, '/'), '/');
        if ($fullPattern === '') $fullPattern = '/';

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $fullPattern,
            'handler'    => $handler,
            'middleware' => array_merge($stackMiddleware, $middleware),
        ];
    }

    public function dispatch(Request $req): void
    {
        $method = $req->method();
        $path = '/' . trim($req->path(), '/');
        if ($path === '') $path = '/';

        foreach ($this->routes as $r) {
            if ($r['method'] !== 'ANY' && $r['method'] !== $method) continue;
            $regex = $this->patternToRegex($r['pattern']);
            if (preg_match($regex, $path, $m)) {
                $params = array_filter($m, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
                $this->invoke($r, $req, $params);
                return;
            }
        }

        Response::notFound('No route matches ' . htmlspecialchars($path));
    }

    private function patternToRegex(string $pattern): string
    {
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#', function ($m) {
            $name = $m[1];
            $constraint = $m[2] ?? '[^/]+';
            return '(?P<' . $name . '>' . $constraint . ')';
        }, $pattern);
        return '#^' . $regex . '$#u';
    }

    private function invoke(array $route, Request $req, array $params): void
    {
        // Middleware
        foreach ($route['middleware'] as $mw) {
            if (is_callable($mw)) {
                $mw($req);
            } elseif (is_string($mw) && class_exists($mw)) {
                (new $mw())->handle($req);
            }
        }

        $handler = $route['handler'];
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = new $class();
            $controller->{$method}($req, ...array_values($params));
            return;
        }
        if (is_callable($handler)) {
            $handler($req, ...array_values($params));
            return;
        }
        throw new \RuntimeException('Invalid route handler.');
    }
}
