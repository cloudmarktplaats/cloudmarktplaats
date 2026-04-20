<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Router;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testRegisterGetRoute(): void
    {
        $this->router->get('/test', 'TestController@index');
        $match = $this->router->match('GET', '/test');
        $this->assertNotNull($match);
        $this->assertEquals('TestController', $match['controller']);
        $this->assertEquals('index', $match['action']);
    }

    public function testRegisterPostRoute(): void
    {
        $this->router->post('/submit', 'TestController@store');
        $match = $this->router->match('POST', '/submit');
        $this->assertNotNull($match);
        $this->assertEquals('store', $match['action']);
    }

    public function testParameterExtraction(): void
    {
        $this->router->get('/product/{id}', 'ProductController@view');
        $match = $this->router->match('GET', '/product/42');
        $this->assertNotNull($match);
        $this->assertEquals('42', $match['params']['id']);
    }

    public function testMultipleParameters(): void
    {
        $this->router->get('/forum/{category_id}/topic/{id}', 'ForumController@topic');
        $match = $this->router->match('GET', '/forum/5/topic/12');
        $this->assertNotNull($match);
        $this->assertEquals('5', $match['params']['category_id']);
        $this->assertEquals('12', $match['params']['id']);
    }

    public function testNoMatchReturnsNull(): void
    {
        $this->router->get('/exists', 'TestController@index');
        $match = $this->router->match('GET', '/not-exists');
        $this->assertNull($match);
    }

    public function testMethodMismatchReturnsNull(): void
    {
        $this->router->get('/only-get', 'TestController@index');
        $match = $this->router->match('POST', '/only-get');
        $this->assertNull($match);
    }

    public function testMiddlewareAttachment(): void
    {
        $this->router->get('/admin', 'AdminController@index', ['auth', 'admin']);
        $match = $this->router->match('GET', '/admin');
        $this->assertNotNull($match);
        $this->assertEquals(['auth', 'admin'], $match['middleware']);
    }
}
