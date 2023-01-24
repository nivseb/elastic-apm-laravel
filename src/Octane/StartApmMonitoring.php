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
use AG\ElasticApmLaravel\ServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Laravel\Octane\Events\RequestReceived;

class StartApmMonitoring
{
    public function handle(RequestReceived $octaneEvent)
    {
        $this->clearInstances(
            [
                Agent::class,
                RequestStartTime::class,
                EventCounter::class,
            ] + (fn() => Arr::get($this->tags,ServiceProvider::COLLECTOR_TAG, []))->call($octaneEvent->app)
            ,
            $octaneEvent->app,
            $octaneEvent->sandbox
        );

        /** @var FrameworkCollector $frameworkCollector */
        $frameworkCollector = $octaneEvent->sandbox->make(FrameworkCollector::class);
        $start_time = defined('LARAVEL_START') ? constant('LARAVEL_START') : microtime(true);
        $frameworkCollector->startMeasure('app_boot', 'app', 'boot', 'App boot', $start_time);
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