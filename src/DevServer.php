<?php declare(strict_types=1);

namespace Bref\DevServer;

use Bref\Bref;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Symfony\Component\Yaml\Yaml;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

class DevServer
{
    public function run(): bool|null
    {
        // Serve assets
        if (PHP_SAPI === 'cli-server') {
            $url = parse_url($_SERVER['REQUEST_URI']);
            if (is_file(getcwd() . '/web' . ($url['path'] ?? ''))) return false;
        }

        $whoops = new Run;
        $whoops->pushHandler(new PrettyPageHandler);
        $whoops->register();

        $container = Bref::getContainer();

        $psr17Factory = new Psr17Factory;
        $requestFactory = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        $serverlessConfig = Yaml::parseFile(getcwd() . '/serverless.yml', Yaml::PARSE_CUSTOM_TAGS);
        $router = Router::fromServerlessConfig($serverlessConfig);

        $request = $requestFactory->fromGlobals();
        [$handler, $request] = $router->match($request);
        $controller = $handler ? $container->get($handler) : new NotFound;
        $response = $controller->handle($request);
        (new ResponseEmitter)->emit($response);

        return null;
    }
}
