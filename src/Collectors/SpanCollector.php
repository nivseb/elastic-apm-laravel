<?php

namespace AG\ElasticApmLaravel\Collectors;

use AG\ElasticApmLaravel\Contracts\DataCollector;
use AG\ElasticApmLaravel\Events\StartMeasuring;
use AG\ElasticApmLaravel\Events\StopMeasuring;
use Illuminate\Container\Container;

/**
 * Generic collector for spans measured manually throughout the app.
 */
class SpanCollector extends EventDataCollector implements DataCollector
{
    public function getName(): string
    {
        return 'span-collector';
    }

    public function registerEventListeners(Container $app): void
    {
       $app->events->listen(StartMeasuring::class, function (StartMeasuring $event) {
            $this->startMeasure(
                $event->name,
                $event->type,
                $event->action,
                $event->label,
                $event->start_time
            );
        });

       $app->events->listen(StopMeasuring::class, function (StopMeasuring $event) {
            $this->stopMeasure($event->name, $event->params);
        });
    }
}
