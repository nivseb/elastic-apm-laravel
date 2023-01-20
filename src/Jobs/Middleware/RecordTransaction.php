<?php

namespace AG\ElasticApmLaravel\Jobs\Middleware;

use AG\ElasticApmLaravel\Services\ApmCollectorService;

class RecordTransaction
{
    /**
     * Wrap the job processing in an APM transaction.
     *
     * @param mixed    $job
     * @param callable $next
     *
     * @return mixed
     */
    public function handle($job, $next)
    {
        if (false === config('elastic-apm-laravel.active') || false === config('elastic-apm-laravel.cli.active')) {
            return $next($job);
        }


        app(ApmCollectorService::class)->startMeasure('job_processing', 'job', 'processing', get_class($job) . ' processing');

        $next($job);

        app(ApmCollectorService::class)->stopMeasure('job_processing');
    }
}
