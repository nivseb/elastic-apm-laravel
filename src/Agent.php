<?php

namespace AG\ElasticApmLaravel;

use AG\ElasticApmLaravel\APMEvents\Span;
use AG\ElasticApmLaravel\Collectors\EventDataCollector;
use AG\ElasticApmLaravel\Contracts\DataCollector;
use AG\ElasticApmLaravel\Exception\NoCurrentTransactionException;
use Illuminate\Config\Repository;
use Illuminate\Support\Collection;
use Nipwaayoni\Agent as NipwaayoniAgent;
use Nipwaayoni\Config;
use Nipwaayoni\Contexts\ContextCollection;
use Nipwaayoni\Events\EventFactoryInterface;
use Nipwaayoni\Events\Metadata;
use Nipwaayoni\Events\Transaction;
use Nipwaayoni\Middleware\Connector;
use Nipwaayoni\Stores\TransactionsStore;

/**
 * The Elastic APM agent sends performance metrics and error logs to the APM Server.
 *
 * The agent records events, like HTTP requests and database queries.
 * The Agent automatically keeps track of queries to your data stores
 * to measure their duration and metadata (like the DB statement), as well as HTTP related information.
 *
 * These events, called Transactions and Spans, are sent to the APM Server.
 * The APM Server converts them to a format suitable for Elasticsearch,
 * and sends them to an Elasticsearch cluster. You can then use the APM app
 * in Kibana to gain insight into latency issues and error culprits within your application.
 */
class Agent extends NipwaayoniAgent
{
    protected $collectors;

    /** @var Transaction */
    private $current_transaction;

    /** @var Repository */
    private $app_config;

    public function __construct(
        Config                $config,
        ContextCollection     $sharedContext,
        Connector             $connector,
        EventFactoryInterface $eventFactory,
        TransactionsStore     $transactionsStore,
        Repository            $app_config
    )
    {
        parent::__construct($config, $sharedContext, $connector, $eventFactory, $transactionsStore);

        $this->app_config = $app_config;

        $this->collectors = new Collection();
    }

    public function addCollector(DataCollector $collector): void
    {
        $collector->useAgent($this);

        $this->collectors->put(
            $collector->getName(),
            $collector
        );
    }

    public function getCollector(string $name): DataCollector
    {
        return $this->collectors->get($name);
    }

    /**
     * We need to keep track of the current Transaction so the app can access it for
     * distributed tracing and other tasks. For now, we expect a single transaction
     * to be sufficient for HTTP requests and jobs. This will need to change if we
     * ever start allowing nested transactions.
     */
    public function hasCurrentTransaction(): bool
    {
        return null !== $this->current_transaction;
    }

    public function setCurrentTransaction(Transaction $transaction): void
    {
        $this->current_transaction = $transaction;
    }

    public function clearCurrentTransaction(): void
    {
        $this->current_transaction = null;
    }

    public function currentTransaction(): Transaction
    {
        if (!$this->hasCurrentTransaction()) {
            throw new NoCurrentTransactionException();
        }

        return $this->current_transaction;
    }

    public function collectEvents(string $transaction_name): void
    {
        $transaction = $this->getTransaction($transaction_name);
        $this->collectors->each(function ($collector) use ($transaction) {
            $collector->collect()->each(function ($measure) use ($transaction) {
                $event = new Span($measure['label'], $transaction);
                $event->setType($measure['type']);
                $event->setAction($measure['action']);
                $event->setStartOffset($measure['start']);
                $event->setDuration($measure['duration']);

                dump($measure['label'], $measure['context']);

                if (empty($measure['context']) === false) {
                    if (empty($measure['context']['db']) === false) {
                        $event->setDBContext($measure['context']['db']);
                    }
                    if (empty($measure['context']['destination']) === false) {
                        $event->setDestinationContext($measure['context']['destination']);
                    }
                    if (empty($measure['context']['http']) === false) {
                        $event->setHttpContext($measure['context']['http']);
                    }
                    if (empty($measure['context']['message']) === false) {
                        $event->setMessageContext($measure['context']['message']);
                    }
                    if (empty($measure['context']['service']) === false) {
                        $event->setServiceContext($measure['context']['service']);
                    }
                    if (empty($measure['context']['tags']) === false) {
                        $event->setTags($measure['context']['tags']);
                    }
                }

                $this->putEvent($event);
            });
        });
    }

    public function startTransaction(string $name, array $context = [], float $start = null): Transaction
    {
        $transaction = parent::startTransaction($name, $context, $start);
        $this->setCurrentTransaction($transaction);

        return $transaction;
    }

    public function send(): void
    {
        parent::send();

        $this->clearCurrentTransaction();

        // Ensure collectors are reset after data is sent to APM
        $this->collectors->each(function (EventDataCollector $collector) {
            $collector->reset();
        });

        // TODO reset started event counter

        /*
         * Push new metadata onto the stack in preparation for the next send event. This
         * simulates the behavior when a new Agent is created and is needed for long running
         * worker processes. A future release of the Agent package should handle event
         * collection better and remove the need for this.
         */
        $this->putEvent(new Metadata([], $this->getConfig(), $this->agentMetadata()));
    }
}
