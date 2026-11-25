<?php
require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Response.php';

class Router
{
    private static array $routes = [];
    private static array $groupAttributes = [];
    private static array $middlewareRegistry = [];

    public static function group(array $attributes, callable $callback): void
    {
        $previous = self::$groupAttributes;
        $prefix = $attributes['prefix'] ?? '';
        $middleware = $attributes['middleware'] ?? [];
        if (isset(self::$groupAttributes['prefix'])) {
            $prefix = self::$groupAttributes['prefix'] . $prefix;
        }
        if (isset(self::$groupAttributes['middleware'])) {
            $middleware = array_merge(self::$groupAttributes['middleware'], $middleware);
        }
        self::$groupAttributes = ['prefix' => $prefix, 'middleware' => $middleware];
        $callback();
        self::$groupAttributes = $previous;
    }

    public static function get(string $path, callable|array $handler, array $middleware = []): void
    {
        self::addRoute('GET', $path, $handler, $middleware);
    }

    public static function post(string $path, callable|array $handler, array $middleware = []): void
    {
        self::addRoute('POST', $path, $handler, $middleware);
    }

    public static function put(string $path, callable|array $handler, array $middleware = []): void
    {
        self::addRoute('PUT', $path, $handler, $middleware);
    }

    public static function patch(string $path, callable|array $handler, array $middleware = []): void
    {
        self::addRoute('PATCH', $path, $handler, $middleware);
    }

    public static function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        self::addRoute('DELETE', $path, $handler, $middleware);
    }

    private static function addRoute(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $prefix = self::$groupAttributes['prefix'] ?? '';
        $groupMiddleware = self::$groupAttributes['middleware'] ?? [];
        $fullPath = $prefix . $path;
        $allMiddleware = array_merge($groupMiddleware, $middleware);
        self::$routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => $allMiddleware,
        ];
    }

    public static function registerMiddleware(string $name, callable $handler): void
    {
        self::$middlewareRegistry[$name] = $handler;
    }

    public static function dispatch(): void
    {
        $request = new Request();
        $method = $request->method();
        $uri = $request->uri();

        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/');
        if ($uri === '') $uri = '/';

        foreach (self::$routes as $route) {
            $pattern = self::pathToRegex($route['path']);
            if ($route['method'] !== $method) continue;
            if (!preg_match($pattern, $uri, $matches)) continue;
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $request->setRouteParams($params);
            foreach ($route['middleware'] as $mw) {
                if (isset(self::$middlewareRegistry[$mw])) {
                    $result = call_user_func(self::$middlewareRegistry[$mw], $request);
                    if ($result === false) return;
                } else {
                    $mwInstance = new $mw();
                    $result = $mwInstance->handle($request);
                    if ($result === false) return;
                }
            }
            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $method] = $handler;
                $controller = new $class();
                call_user_func([$controller, $method], $request);
            } else {
                call_user_func($handler, $request);
            }
            return;
        }
        Response::json(['error' => 'Not Found'], 404);
    }

    private static function pathToRegex(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}
