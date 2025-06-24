<?php

namespace App\Core;

class Router {
    private array $routes = [];
    private string $currentPath;
    private string $currentMethod;

    public function __construct() {
        $this->currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->currentMethod = $_SERVER['REQUEST_METHOD'];
    }

    public function get(string $path, string $handler): void {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, string $handler): void {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, string $handler): void {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }

    public function dispatch(): void {
        foreach ($this->routes as $route) {
            if ($route['method'] === $this->currentMethod && $this->matchPath($route['path'])) {
                [$controller, $method] = explode('@', $route['handler']);
                $controllerClass = "App\\Controllers\\{$controller}";
                $controllerInstance = new $controllerClass();
                $controllerInstance->$method();
                return;
            }
        }
        
        // 404 handler
        header("HTTP/1.0 404 Not Found");
        echo "404 Not Found";
    }

    private function matchPath(string $routePath): bool {
        $pattern = preg_replace('/\{([a-zA-Z]+)\}/', '([^/]+)', $routePath);
        $pattern = str_replace('/', '\/', $pattern);
        return (bool) preg_match('/^' . $pattern . '$/', $this->currentPath);
    }
} 