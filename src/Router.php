<?php declare(strict_types=1);

namespace Bref\DevServer;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Reproduces API Gateway routing for local development.
 *
 * @internal
 */
class Router
{
    /** @var array<string,string> */
    private array $routes;

    /**
     * @param array<string,string> $routes
     */
    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public static function fromServerlessConfig(array $serverlessConfig): self
    {
        $routes = [];
        foreach ($serverlessConfig['functions'] as $function) {
            $pattern = $function['events'][0]['httpApi'] ?? null;
            if (! $pattern) continue;
            if (is_array($pattern) && isset($pattern['method']) && isset($pattern['path'])) {
                $pattern = "${pattern['method']} ${pattern['path']}";
            }
            $routes[$pattern] = $function['handler'];
        }
        return new self($routes);
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

        return ($method === 'any') || ($method === strtolower($request->getMethod()));
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
        preg_match($pathRegex, $requestPath, $matches);
        foreach ($matches as $name => $value) {
            $request = $request->withAttribute($name, $value);
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
