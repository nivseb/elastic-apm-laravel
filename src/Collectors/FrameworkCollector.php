<?php

namespace AG\ElasticApmLaravel\Collectors;

use AG\ElasticApmLaravel\Contracts\DataCollector;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * Collects info about the Laravel initialization.
 */
class FrameworkCollector extends EventDataCollector implements DataCollector
{
    public function getName(): string
    {
        return 'framework-collector';
    }

    public static function registerEventListeners(Container $app): void
    {
        // Application and Laravel startup times
        // LARAVEL_START is defined at the entry point of the application
        // https://github.com/laravel/laravel/blob/507d499577e4f3edb51577e144b61e61de4fb57f/public/index.php#L6
        // But for serverless applications like Vapor or Octane,
        // the constant is not defined making the application fail.
        $start_time = defined('LARAVEL_START') ? constant('LARAVEL_START') : microtime(true);

//  TODO:       $this->startMeasure('app_boot', 'app', 'boot', 'App boot', $start_time);

        $app->booting(function () {
            $collector = Container::getInstance()->make(static::class);
            $collector->startMeasure('laravel_boot', 'laravel', 'boot', 'Laravel boot');
            $collector->stopMeasure('app_boot');
        });

        $app->booted(function () {
            $collector = Container::getInstance()->make(static::class);
            if ($collector->hasStartedMeasure('laravel_boot')) {
                $collector->stopMeasure('laravel_boot');
            }
        });
    }
}
