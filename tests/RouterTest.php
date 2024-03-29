<?php declare(strict_types=1);

namespace Bref\DevServer\Test;

use Bref\DevServer\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Yaml\Yaml;

class RouterTest extends TestCase
{
    public function test wildcard(): void
    {
        $router = new Router([
            'GET /' => 'home',
            '*' => 'wildcard',
        ]);

        self::assertEquals('home', $router->match($this->request('GET', '/'))[0]);
        self::assertEquals('wildcard', $router->match($this->request('GET', '/abc'))[0]);
    }

    public function test routing simple paths(): void
    {
        $router = new Router([
            'GET /' => 'home',
            'POST /' => 'post home',
            'GET /abc' => 'abc',
        ]);

        self::assertEquals('home', $router->match($this->request('GET', '/'))[0]);
        self::assertEquals('post home', $router->match($this->request('POST', '/'))[0]);
        self::assertEquals('abc', $router->match($this->request('GET', '/abc'))[0]);
    }

    public function test routing path parameters(): void
    {
        $router = new Router([
            'GET /' => 'home',
            'GET /{root}' => 'home with param',
            'GET /{root}/abc' => 'abc',
            'GET /{root}/{sub}' => 'def',
        ]);

        self::assertEquals('home', $router->match($this->request('GET', '/'))[0]);
        self::assertEquals('home with param', $router->match($this->request('GET', '/abc'))[0]);
        self::assertEquals('abc', $router->match($this->request('GET', '/abc/abc'))[0]);
        self::assertEquals('def', $router->match($this->request('GET', '/abc/def'))[0]);
    }

    /**
     * @test
     */
    public function test_routing_expanded_config(): void
    {
        $config = <<<YAML
        functions:
          api:
            handler: api.php
            events:
              - httpApi:
                  method: '*'
                  path: '*'
        YAML;

        $config = Yaml::parse($config);
        $router = Router::fromServerlessConfig($config);

        self::assertSame('api.php', $router->match($this->request('GET', '/'))[0]);
        self::assertSame('api.php', $router->match($this->request('POST', '/'))[0]);
        self::assertSame('api.php', $router->match($this->request('GET', '/tests/1'))[0]);
        self::assertSame('api.php', $router->match($this->request('PUT', '/tests/1'))[0]);
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return new ServerRequest($method, $path);
    }
}
