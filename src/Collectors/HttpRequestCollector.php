<?php

namespace AG\ElasticApmLaravel\Collectors;

use AG\ElasticApmLaravel\Contracts\DataCollector;
use Illuminate\Container\Container;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;

/**
 * Collects info about the http request process.
 */
class HttpRequestCollector extends EventDataCollector implements DataCollector
{
    public function getName(): string
    {
        return 'request-collector';
    }

    public function registerEventListeners(Container $app): void
    {
       $app->booted(function () {
            $this->startMeasure('route_matching', 'laravel', 'request', 'Route matching');
        });

        // Time between route resolution and request handled
       $app->events->listen(RouteMatched::class, function (RouteMatched $event) {
            $this->startMeasure('request_handled', 'laravel', 'request', $this->getController($event->route));
            if ($this->started_measures->has('route_matching')) {
                $this->stopMeasure('route_matching');
            }
        });

       $app->events->listen(RequestHandled::class, function () {
            // Some middlewares might return a response
            // before the RouteMatched has been dispatched
            if ($this->hasStartedMeasure('request_handled')) {
                $this->stopMeasure('request_handled');
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
