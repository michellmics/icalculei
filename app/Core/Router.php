<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Liga cada endereço ao método do controller.
 * Exemplo: $router->get('/calculadoras/{slug}', [SiteController::class, 'tool']);
 * O trecho {slug} vira um parâmetro do método.
 */
class Router
{
    private array $routes = [];

    public function get(string $path, array $action): void
    {
        $this->routes[] = ['method' => 'GET', 'pattern' => $this->buildPattern($path), 'action' => $action];
    }

    public function post(string $path, array $action): void
    {
        $this->routes[] = ['method' => 'POST', 'pattern' => $this->buildPattern($path), 'action' => $action];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }
            $parameters = array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            [$controllerClass, $methodName] = $route['action'];
            (new $controllerClass())->$methodName(...$parameters);
            return;
        }

        (new \App\Controllers\SiteController())->notFound();
    }

    /**
     * "/noticias/{slug}" vira a expressão regular "#^/noticias/(?P<slug>[a-z0-9-]+)$#".
     */
    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[a-z0-9-]+)', $path);

        return '#^' . $pattern . '$#';
    }
}
