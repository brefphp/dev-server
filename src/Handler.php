<?php declare(strict_types=1);

namespace Bref\DevServer;

use Bref\Bref;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * @internal
 */
class Handler
{
    public const ASSETS_DIRECTORY_VARIABLE = '_BREF_LOCAL_ASSETS_DIRECTORY';

    public function handleRequest(): bool|null
    {
        $assetsDirectory = getenv(self::ASSETS_DIRECTORY_VARIABLE);

        // Serve assets
        if (PHP_SAPI === 'cli-server') {
            $url = parse_url($_SERVER['REQUEST_URI']);
            if (is_file($assetsDirectory . ($url['path'] ?? ''))) return false;
        }

        $whoops = new Run;
        $whoops->pushHandler(new PrettyPageHandler);
        $whoops->register();

        $container = Bref::getContainer();

        $serverlessFile = getcwd() . '/serverless.yml';
        if (! file_exists($serverlessFile)) {
            throw new RuntimeException('No serverless.yml file was found in the current directory. This dev server needs a serverless.yml to discover the API Gateway routes.');
        }
        $serverlessConfig = Yaml::parseFile($serverlessFile, Yaml::PARSE_CUSTOM_TAGS);
        $router = Router::fromServerlessConfig($serverlessConfig);

        $request = $this->requestFromGlobals();
        [$handler, $request] = $router->match($request);
        $controller = $handler ? $container->get($handler) : new NotFound;
        $response = $controller->handle($request);
        (new ResponseEmitter)->emit($response);

        return null;
    }

    private function requestFromGlobals(): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory;
        $requestFactory = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );
        return $requestFactory->fromGlobals();
    }
}
