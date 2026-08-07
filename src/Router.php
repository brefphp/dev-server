<?php declare(strict_types=1);

namespace Bref\DevServer;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Yaml\Yaml;

use function is_array;

/**
 * Reproduces API Gateway routing for local development.
 *
 * @internal
 */
class Router
{
    public static function fromServerlessConfig(array $serverlessConfig): self
    {
        $routes = [];
        foreach ($serverlessConfig['functions'] as $functionConfig) {
            //Check if function definition is included by another file
            $function = self::checkIfFunctionIsIncludedByFile($functionConfig);
            //Get pattern by events array
            $pattern = self::getPatternByEvents($function['events']);

            if (! $pattern) {
                continue;
            }

            if (is_string($pattern)) {
                $pattern = array_combine(['method', 'path'], array_pad(explode(' ', $pattern, 2), 2, '*'));
            }

            $pattern = self::patternToString($pattern);
            $routes[$pattern] = $function['handler'];
        }

        return new self($routes);
    }

    public static function checkIfFunctionIsIncludedByFile(string|array $functionConfig): mixed
    {
        //Check if function is included by another file with ${file(./my/function/path)} syntax
        if (is_string($functionConfig) && str_contains($functionConfig, '${file')) {
            $init = strpos($functionConfig, '(') + 1; //path is always after an open parenthesis
            $end = strpos($functionConfig, ')'); //path is always closed by a closed parenthesis
            $functionFilePath = substr($functionConfig, $init, $end - $init); //get file path
            $functionAttributes = Yaml::parseFile($functionFilePath, Yaml::PARSE_CUSTOM_TAGS); //parse function file yaml
            //first element of the attributes array has the name of the function
            return reset($functionAttributes);
        }
        return $functionConfig;
    }

    public static function getPatternByEvents(array $events): mixed
    {
        //Cycle events as they could be multiple
        foreach ($events as $event) {
            if (isset($event['http'])) { //Search for API Gateway v1 syntax
                return $event['http'];
            } elseif (isset($event['httpApi'])) { //Or for API Gateway v2 syntax
                return $event['httpApi'];
            }
        }
        return null;
    }

    private static function patternToString(array $pattern): string
    {
        $method = $pattern['method'] ?? '*';
        $path = $pattern['path'] ?? '*';

        // Special "any" method MUST be converted to star.
        if (strtolower($method) === 'any') {
            $method = '*';
        }

        // Alternative catch-all MUST be converted to standard catch-all.
        if ($method === '*' && $path === '*') {
            return '*';
        }

        return $method . ' ' . $path;
    }

    /** @var array<string,string> */
    private array $routes;

    /**
     * @param array<string,string> $routes
     */
    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    /**
     * @return array{0: ?string, 1: ServerRequestInterface}
     */
    public function match(ServerRequestInterface $request): array
    {
        foreach ($this->routes as $pattern => $handler) {
            // Catch-all
            if ($pattern === '*') return [$handler, $request];

            [$httpMethod, $pathPattern] = explode(' ', $pattern);
            if ($this->matchesMethod($request, $httpMethod) && $this->matchesPath($request, $pathPattern)) {
                $request = $this->addPathParameters($request, $pathPattern);

                return [$handler, $request];
            }
        }

        // No route matched
        return [null, $request];
    }

    private function matchesMethod(ServerRequestInterface $request, string $method): bool
    {
        $method = strtolower($method);

        return ($method === '*') || ($method === strtolower($request->getMethod()));
    }

    private function matchesPath(ServerRequestInterface $request, string $pathPattern): bool
    {
        $requestPath = $request->getUri()->getPath();

        // No path parameter
        if (! str_contains($pathPattern, '{')) {
            return $requestPath === $pathPattern;
        }

        $pathRegex = $this->patternToRegex($pathPattern);

        return preg_match($pathRegex, $requestPath) === 1;
    }

    private function addPathParameters(ServerRequestInterface $request, mixed $pathPattern): ServerRequestInterface
    {
        $requestPath = $request->getUri()->getPath();

        // No path parameter
        if (! str_contains($pathPattern, '{')) {
            return $request;
        }

        $pathRegex = $this->patternToRegex($pathPattern);
        preg_match($pathRegex, ltrim($requestPath, '/'), $matches);
        foreach ($matches as $name => $value) {
            $request = $request->withAttribute((string) $name, $value);
        }

        return $request;
    }

    private function patternToRegex(string $pathPattern): string
    {
        // Match to find all the parameter names
        $matchRegex = '#^' . preg_replace('/{[^}]+}/', '([^/]+)', $pathPattern) . '$#';
        preg_match($matchRegex, $pathPattern, $matches);
        // Ignore the global match of the string
        unset($matches[0]);

        /*
         * We will replace all parameter paths with a *name* group.
         * Essentially:
         * - `/{root}` will be replaced to `/(?<root>[^/]+)` (i.e. `([^/]+)` named "root")
         */
        $patterns = [];
        $replacements = [];
        foreach ($matches as $position => $parameterName) {
            $patterns[$position] = "#$parameterName#";
            // Remove `{` and `}` delimiters
            $parameterName = substr($parameterName, 1, -1);
            // The `?<$parameterName>` syntax lets us name the capturing group
            $replacements[$position] = "(?<$parameterName>[^/]+)";
        }

        $regex = preg_replace($patterns, $replacements, $pathPattern);

        return '#^' . $regex . '$#';
    }
}
