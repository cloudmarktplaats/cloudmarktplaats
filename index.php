<?php
require_once 'includes/session.php';
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'controllers/BaseController.php';

// Haal de URL op en verwijder query parameters
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/';
$path = parse_url($request_uri, PHP_URL_PATH);
$path = substr($path, strlen($base_path));

// Verwijder trailing slash
$path = rtrim($path, '/');

// Als de path leeg is, gebruik dan 'home'
if (empty($path)) {
    $path = 'home';
}

// Split de path in controller en action
$parts = explode('/', $path);
$controller_name = ucfirst($parts[0]) . 'Controller';
$action = isset($parts[1]) ? $parts[1] : 'index';

// Laad de controller
$controller_file = "controllers/{$controller_name}.php";

if (file_exists($controller_file)) {
    require_once $controller_file;
    $controller_instance = new $controller_name();
    
    if (method_exists($controller_instance, $action)) {
        $controller_instance->$action();
    } else {
        // Als de action niet bestaat, toon 404
        require_once 'views/404.php';
    }
} else {
    // Als de controller niet bestaat, toon 404
    require_once 'views/404.php';
} 