<?php

namespace AG\ElasticApmLaravel\Collectors;

use AG\ElasticApmLaravel\Contracts\DataCollector;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;
use Nipwaayoni\Events\Transaction;
use Throwable;

/**
 * Collects info about the job process.
 */
class JobCollector extends EventDataCollector implements DataCollector
{
    public function getName(): string
    {
        return 'job-collector';
    }

    public static function registerEventListeners(Container $app): void
    {
       $app->events->listen(JobProcessing::class, function (JobProcessing $event) use ($app) {
           $collector = Container::getInstance()->make(static::class);
            if ($app->runningInConsole()) {
                // Since the application starts only once for async queues, make sure
                // the transaction and all spans have the correct start time.
                $collector->start_time->setStartTime($collector->event_clock->microtime());
            }

            $transaction_name = $collector->getTransactionName($event);
            if ($transaction_name) {
                $transaction = $collector->getTransaction($transaction_name);
                if (!$transaction) {
                    $collector->startTransaction($transaction_name);
                    $collector->setTransactionType($transaction_name);
                    $collector->addMetadata($transaction_name, $event->job);
                }
            }
        });

       $app->events->listen(JobProcessed::class, function (JobProcessed $event) {
           $collector = Container::getInstance()->make(static::class);
            $transaction_name = $collector->getTransactionName($event);
            if ($transaction_name) {
                $transaction = $collector->getTransaction($transaction_name);
                if ($transaction) {
                    $collector->stopTransaction($transaction_name, 200);
                    $collector->send($event->job);
                }
            }
        });

       $app->events->listen(JobFailed::class, function (JobFailed $event) {
           $collector = Container::getInstance()->make(static::class);
            $transaction_name = $collector->getTransactionName($event);
            if ($transaction_name) {
                $transaction = $collector->getTransaction($transaction_name);
                if ($transaction) {
                    $collector->agent->captureThrowable($event->exception, [], $transaction);
                    $collector->stopTransaction($transaction_name, 500);
                    $collector->send($event->job);
                }
            }
        });
    }

    protected function startTransaction(string $transaction_name): Transaction
    {
        return $this->agent->startTransaction(
            $transaction_name,
            [],
            $this->start_time->microseconds()
        );
    }

    protected function setTransactionType(string $transaction_name): void
    {
        $this->agent->getTransaction($transaction_name)->setMeta([
            'type' => 'job',
        ]);
    }

    /**
     * Jobs don't have a response code like HTTP but we'll add the 200 success or 500 failure anyway
     * because it helps with filtering in Elastic.
     */
    protected function stopTransaction(string $transaction_name, int $result): void
    {
        // Stop the transaction and measure the time
        $this->agent->stopTransaction($transaction_name, ['result' => $result]);
        $this->agent->collectEvents($transaction_name);
    }

    protected function addMetadata(string $transaction_name, Job $job): void
    {
        $this->agent->getTransaction($transaction_name)->setCustomContext([
            'job_id' => $job->getJobId(),
            'max_tries' => $job->maxTries(),
            'attempts' => $job->attempts(),
            'connection_name' => $job->getConnectionName(),
            'queue_name' => $job->getQueue(),
        ]);
    }

    protected function send(Job $job): void
    {
        try {
            if (!($job instanceof SyncJob)) {
                // When using a queued driver, send/flush transaction to make room for the next job in the queue
                $this->agent->send();
            }
        } catch (ClientException $exception) {
            Log::error($exception, ['api_response' => (string) $exception->getResponse()->getBody()]);
        } catch (Throwable $t) {
            Log::error($t->getMessage());
        }
    }

    /**
     * Return no name if we shouldn't record this transaction.
     */
    protected function getTransactionName($event): string
    {
        $transaction_name = $event->job->resolveName();

        return $this->shouldIgnoreTransaction($transaction_name) ? '' : $transaction_name;
    }
}
