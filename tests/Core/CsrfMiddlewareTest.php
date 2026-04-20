<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Middleware\CsrfMiddleware;

class CsrfMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    }

    public function testHandleGeneratesTokenOnGet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $middleware = new CsrfMiddleware();
        $result = $middleware->handle();
        $this->assertTrue($result);
        $this->assertNotEmpty($_SESSION['_csrf_token']);
    }

    public function testHandleAcceptsValidPostToken(): void
    {
        $_SESSION['_csrf_token'] = 'valid-token-123';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'valid-token-123';

        $middleware = new CsrfMiddleware();
        $result = $middleware->handle();
        $this->assertTrue($result);
    }

    public function testHandleRejectsMissingPostToken(): void
    {
        $_SESSION['_csrf_token'] = 'valid-token-123';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $middleware = new CsrfMiddleware();
        $result = $middleware->handle();
        $this->assertFalse($result);
    }

    public function testHandleRejectsInvalidPostToken(): void
    {
        $_SESSION['_csrf_token'] = 'valid-token-123';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'wrong-token';

        $middleware = new CsrfMiddleware();
        $result = $middleware->handle();
        $this->assertFalse($result);
    }

    public function testHandleAcceptsHeaderToken(): void
    {
        $_SESSION['_csrf_token'] = 'valid-token-123';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid-token-123';

        $middleware = new CsrfMiddleware();
        $result = $middleware->handle();
        $this->assertTrue($result);
    }
}
