<?php

namespace AG\ElasticApmLaravel\Octane;

use AG\ElasticApmLaravel\Agent;
use AG\ElasticApmLaravel\AgentBuilder;
use AG\ElasticApmLaravel\Collectors\RequestStartTime;
use AG\ElasticApmLaravel\ServiceProvider;
use Illuminate\Config\Repository;
use Laravel\Octane\Events\RequestReceived;
use Nipwaayoni\Config;

class StartApmMonitoring
{
    public function handle(RequestReceived $octaneEvent)
    {
        $apmProvider = $octaneEvent->app->getProvider(ServiceProvider::class);
        $builder     = $octaneEvent->app->make(AgentBuilder::class);

        $octaneEvent->sandbox->instance(
            RequestStartTime::class,
            new RequestStartTime($octaneEvent->request->server('REQUEST_TIME_FLOAT') ?? microtime(true))
        );

        $octaneEvent->sandbox->instance(
            Agent::class,
            $builder
                ->withConfig(new Config($apmProvider->getAgentConfig()))
                ->withEnvData(config('elastic-apm-laravel.env.env'))
                ->withAppConfig($octaneEvent->sandbox->make(Repository::class))
                ->withEventCollectors(collect($octaneEvent->sandbox->tagged(ServiceProvider::COLLECTOR_TAG)))
                ->build()

        );
    }
}