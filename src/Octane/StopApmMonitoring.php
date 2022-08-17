<?php

namespace AG\ElasticApmLaravel\Octane;

use AG\ElasticApmLaravel\Agent;
use AG\ElasticApmLaravel\Collectors\CommandCollector;
use AG\ElasticApmLaravel\Collectors\DBQueryCollector;
use AG\ElasticApmLaravel\Collectors\EventCounter;
use AG\ElasticApmLaravel\Collectors\FrameworkCollector;
use AG\ElasticApmLaravel\Collectors\HttpRequestCollector;
use AG\ElasticApmLaravel\Collectors\JobCollector;
use AG\ElasticApmLaravel\Collectors\RequestStartTime;
use AG\ElasticApmLaravel\Collectors\ScheduledTaskCollector;
use AG\ElasticApmLaravel\Collectors\SpanCollector;
use Illuminate\Foundation\Application;
use Laravel\Octane\Events\RequestTerminated;
use mySWOOOP\Locations\Http\Clients\APIClient;

class StopApmMonitoring
{
    public function handle(RequestTerminated $event): void
    {
        $this->clearInstances(
            [
                Agent::class,
                RequestStartTime::class,
                EventCounter::class,
                CommandCollector::class,
                DBQueryCollector::class,
                FrameworkCollector::class,
                HttpRequestCollector::class,
                JobCollector::class,
                ScheduledTaskCollector::class,
                SpanCollector::class,
            ]
            ,
            $event->app,
            $event->sandbox
        );
    }

    protected function clearInstances(iterable $set, Application $app, Application $sandbox): void
    {
        foreach ($set as $byebye) {
            if ( !is_string($byebye)) {
                $byebye = get_class($byebye);
            }
            $app->forgetInstance($byebye);
            $sandbox->forgetInstance($byebye);
        }
    }
}