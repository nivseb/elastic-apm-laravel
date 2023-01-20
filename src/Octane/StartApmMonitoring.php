<?php

namespace AG\ElasticApmLaravel\Octane;

use AG\ElasticApmLaravel\Collectors\FrameworkCollector;
use Laravel\Octane\Events\RequestReceived;

class StartApmMonitoring
{
    public function handle(RequestReceived $octaneEvent)
    {
        /** @var FrameworkCollector $frameworkCollector */
        $frameworkCollector = $octaneEvent->sandbox->make(FrameworkCollector::class);
        $start_time = defined('LARAVEL_START') ? constant('LARAVEL_START') : microtime(true);
        $frameworkCollector->startMeasure('app_boot', 'app', 'boot', 'App boot', $start_time);
    }
}