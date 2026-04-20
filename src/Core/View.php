<?php

namespace App\Core;

class View
{
    private static string $viewPath = __DIR__ . '/../Views';
    private static string $layoutPath = __DIR__ . '/../Views/layouts';

    /**
     * Render a view with optional layout.
     * For HTMX requests, renders without layout.
     */
    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $viewFile = self::$viewPath . '/' . $view . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        // Extract data as local variables for the view
        extract($data);

        // Capture view content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // HTMX requests get content only, no layout
        if (self::isHtmxRequest()) {
            echo $content;
            return;
        }

        // Render within layout
        $layoutFile = self::$layoutPath . '/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }

        require $layoutFile;
    }

    /**
     * Escape output for safe HTML display.
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Check if the current request is an HTMX request.
     */
    public static function isHtmxRequest(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']);
    }

    /**
     * Get the CSRF token field HTML.
     */
    public static function csrfField(): string
    {
        $token = Session::get('_csrf_token', '');
        return '<input type="hidden" name="_csrf_token" value="' . self::e($token) . '">';
    }
}
