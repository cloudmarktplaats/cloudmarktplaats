<?php

namespace App\Core;

use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\AdminMiddleware;

class App
{
    private Router $router;
    private string $basePath;

    private array $middlewareMap = [
        'csrf' => CsrfMiddleware::class,
        'auth' => AuthMiddleware::class,
        'admin' => AdminMiddleware::class,
    ];

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->router = new Router();
    }

    public function run(): void
    {
        // Boot
        Config::load($this->basePath);
        Session::start();

        // Configure error reporting
        if (Config::isDebug()) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        // Load routes
        $router = $this->router;
        require $this->basePath . '/src/routes.php';

        // Match current request
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        $match = $this->router->match($method, $uri);

        if ($match === null) {
            $this->render404();
            return;
        }

        // Run global CSRF middleware
        $csrf = new CsrfMiddleware();
        if (!$csrf->handle()) {
            http_response_code(403);
            echo 'Invalid CSRF token. Please go back and try again.';
            return;
        }

        // Run route-specific middleware
        foreach ($match['middleware'] as $name) {
            if (!isset($this->middlewareMap[$name])) {
                continue;
            }
            $middlewareClass = $this->middlewareMap[$name];
            $middleware = new $middlewareClass();
            if (!$middleware->handle()) {
                $this->handleMiddlewareFailure($name);
                return;
            }
        }

        // Dispatch to controller
        $controllerClass = 'App\\Controllers\\' . $match['controller'];
        if (!class_exists($controllerClass)) {
            $this->render404();
            return;
        }

        $controller = new $controllerClass();
        $action = $match['action'];

        if (!method_exists($controller, $action)) {
            $this->render404();
            return;
        }

        // Call controller action with route parameters
        $controller->$action(...array_values($match['params']));
    }

    private function handleMiddlewareFailure(string $name): void
    {
        if ($name === 'auth') {
            Session::flash('error', 'Je moet ingelogd zijn om deze pagina te bekijken.');
            header('Location: /auth/login');
            exit;
        }

        if ($name === 'admin') {
            Session::flash('error', 'Geen toegang.');
            header('Location: /');
            exit;
        }
    }

    private function render404(): void
    {
        http_response_code(404);
        View::render('errors/404', ['title' => 'Pagina niet gevonden']);
    }
}
