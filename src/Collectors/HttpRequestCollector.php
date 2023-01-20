<?php

namespace AG\ElasticApmLaravel\Collectors;

use AG\ElasticApmLaravel\Contracts\DataCollector;
use Illuminate\Container\Container;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;

/**
 * Collects info about the http request process.
 */
class HttpRequestCollector extends EventDataCollector implements DataCollector
{
    public function getName(): string
    {
        return 'request-collector';
    }

    public static function registerEventListeners(Container $app): void
    {
       $app->booted(function () {
           $collector = Container::getInstance()->make(static::class);
           $collector->startMeasure('route_matching', 'laravel', 'request', 'Route matching');
        });

        // Time between route resolution and request handled
       $app->events->listen(RouteMatched::class, function (RouteMatched $event) {
           $collector = Container::getInstance()->make(static::class);
           $collector->startMeasure('request_handled', 'laravel', 'request', $collector->getController($event->route));
            if ($collector->started_measures->has('route_matching')) {
                $collector->stopMeasure('route_matching');
            }
        });

       $app->events->listen(RequestHandled::class, function () {
           $collector = Container::getInstance()->make(static::class);
            // Some middlewares might return a response
            // before the RouteMatched has been dispatched
            if ($collector->hasStartedMeasure('request_handled')) {
                $collector->stopMeasure('request_handled');
            }
        });
    }

    protected function getController(Route $route): ?string
    {
        $controller = $route->getActionName();

        if ($controller instanceof \Closure) {
            $controller = 'anonymous function';
        } elseif (is_object($controller)) {
            $controller = 'instance of ' . get_class($controller);
        } elseif (is_array($controller) && 2 == count($controller)) {
            if (is_object($controller[0])) {
                $controller = get_class($controller[0]) . '->' . $controller[1];
            } else {
                $controller = $controller[0] . '::' . $controller[1];
            }
        } elseif (!is_string($controller)) {
            $controller = null;
        }

        return $controller;
    }
}
