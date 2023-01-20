<?php

namespace AG\ElasticApmLaravel\Collectors;

use AG\ElasticApmLaravel\Contracts\DataCollector;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use Jasny\DB\MySQL\QuerySplitter;

/**
 * Collects info about the database executed queries.
 */
class DBQueryCollector extends EventDataCollector implements DataCollector
{
    public function getName(): string
    {
        return 'query-collector';
    }

    public static function registerEventListeners(Container $app): void
    {
       $app->events->listen(QueryExecuted::class, function (QueryExecuted $query) {
           $collector = Container::getInstance()->make(static::class);
           $collector->onQueryExecutedEvent($query);
        });
    }

    private function onQueryExecutedEvent(QueryExecuted $executed_query): void
    {
        if ('auto' === $this->config->get('elastic-apm-laravel.spans.querylog.enabled')) {
            if ($executed_query->time < $this->config->get('elastic-apm-laravel.spans.querylog.threshold')) {
                return;
            }
        }

        $start_time = $this->event_clock->microtime() - $this->start_time->microseconds() - $executed_query->time / 1000;
        $end_time = $start_time + $executed_query->time / 1000;

        $query = [
            'name' => $this->getQueryName($executed_query->sql),
            'type' => 'db.' . $this->getDatabaseType($executed_query) . '.query',
            'action' => 'query',
            'start' => $start_time,
            'end' => $end_time,
            'context' => [
                'db' => [
                    'statement' => (string) $executed_query->sql,
                    'bindings' => $executed_query->bindings,
                    'type' => 'sql',
                ],
            ],
        ];

        $this->addMeasure(
            $query['name'],
            $query['start'],
            $query['end'],
            $query['type'],
            $query['action'],
            $query['context']
        );
    }

    private function getQueryName(string $sql): string
    {
        $fallback = 'Eloquent Query';

        try {
            $query_type = QuerySplitter::getQueryType($sql);
            $tables = QuerySplitter::splitTables($sql);

            if (isset($query_type) && is_array($tables)) {
                // Query type and tables
                return $query_type . ' ' . join(', ', array_values($tables));
            }

            return $fallback;
        } catch (Exception $e) {
            return $fallback;
        }
    }

    protected function getDatabaseType(QueryExecuted $executed_query) {

        if ($executed_query->connection) {
            return strtolower($executed_query->connection->getDriverName());
        }

        if (!empty($executed_query->connectionName)) {
            return strtolower($executed_query->connectionName);
        }

        return 'mysql';
    }
}
