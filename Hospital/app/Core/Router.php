//handles parsing the URL (/patients/view/1) and instantiates controllers.
<?php
// app/Core/Router.php
namespace Core;

class Router
{
    protected array $routes = [];
    protected array $container;

    public function __construct(array $container = [])
    {
        $this->container = $container;
    }

    public function get(string $path, string $controllerAction, array $middleware = [])
    {
        $this->addRoute('GET', $path, $controllerAction, $middleware);
    }

    public function post(string $path, string $controllerAction, array $middleware = [])
    {
        $this->addRoute('POST', $path, $controllerAction, $middleware);
    }

    protected function addRoute(string $method, string $path, string $controllerAction, array $middleware = [])
    {
        $this->routes[] = compact('method', 'path', 'controllerAction', 'middleware');
    }

    public function dispatch(string $uri, string $httpMethod)
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $httpMethod) {
                continue;
            }
            if ($route['path'] === $path) {
                // parse controller@action
                [$controllerName, $action] = explode('@', $route['controllerAction']);
                $controllerClass = '\\App\\Controllers\\' . $controllerName;
                if (!class_exists($controllerClass)) {
                    http_response_code(500);
                    echo "Controller {$controllerClass} not found.";
                    return;
                }

                $controller = new $controllerClass($this->container);
                // Run middleware (simple)
                foreach ($route['middleware'] as $m) {
                    $mwClass = '\\App\\Middleware\\' . ucfirst($m);
                    if (class_exists($mwClass)) {
                        $mw = new $mwClass($this->container);
                        $result = $mw->handle();
                        if ($result === false) {
                            // Middleware blocked request (should have handled redirect)
                            return;
                        }
                    }
                }

                if (!method_exists($controller, $action)) {
                    http_response_code(500);
                    echo "Action {$action} not found in {$controllerClass}.";
                    return;
                }

                // call action
                return $controller->$action();
            }
        }

        // No route matched
        http_response_code(404);
        echo "Page not found (404).";
    }
}
