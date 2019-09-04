<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 08:10
 */

namespace TS\WebSockets\Routing;


use Exception;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use TS\WebSockets\Controller\ControllerInterface;
use TS\WebSockets\Http\MatcherFactory;


class RouteCollection
{

    /** @var Route[] */
    private $routes;

    protected $serverParams;
    protected $matcherFactory;


    public function __construct(array $serverParams, MatcherFactory $matcherFactory)
    {
        $this->routes = [];
        $this->serverParams = $serverParams;
        $this->matcherFactory = $matcherFactory;
    }


    final public function add(Route $route): void
    {
        $this->routes[] = $route;
    }


    final public function match(ServerRequestInterface $request): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->matches($request)) {
                return $route;
            }
        }
        return null;
    }


    public function create(array $options): Route
    {
        try {
            $matcher = $this->matcherFactory->create($options['match'] ?? '*');
        } catch (InvalidArgumentException $exception) {
            $msg = sprintf('Invalid option "match": %s', $exception->getMessage());
            throw new InvalidArgumentException($msg, 0, $exception);
        }

        $protocols = $options['sub_protocols'] ?? null;
        if (is_null($protocols)) {
            $protocols = $this->serverParams['sub_protocols'] ?? [];
        } else if (!is_array($protocols)) {
            $msg = sprintf('Option "sub_protocols" must be an array or null. Null inherits "sub_protocols" from server parameters.');
            throw new InvalidArgumentException($msg);
        }

        $controller = $options['controller'] ?? null;
        if (is_string($controller)) {
            try {
                $ref = new ReflectionClass($controller);
                $controller = $ref->newInstance();
            } catch (Exception $exception) {
                $msg = sprintf('Unable to instantiate controller %s: %s', $controller, $exception->getMessage());
                throw new InvalidArgumentException($msg, 0, $exception);
            }
        } else if (is_object($controller)) {
            if (!$controller instanceof ControllerInterface) {
                $msg = sprintf('Instance of %s provided for option "controller" does not implement %s.', get_class($controller), ControllerInterface::class);
                throw new InvalidArgumentException($msg);
            }
        } else if (!is_null($controller)) {
            $msg = sprintf('Invalid value for option "controller". Expected string or %s, got %s.', ControllerInterface::class, gettype($controller));
            throw new InvalidArgumentException($msg);
        }

        if (!$controller) {
            $msg = sprintf('Missing controller. You have to provide either an implementation or a class name of %s as the option "controller".', ControllerInterface::class);
            throw new InvalidArgumentException($msg);
        }

        return new Route($matcher, $controller, $protocols);
    }

}
