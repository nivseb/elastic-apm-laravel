<?php

namespace Nipwaayoni\Events;

use Nipwaayoni\Config;
use Nipwaayoni\Helper\Encoding;

class Metadata extends EventBean implements \JsonSerializable
{
    protected $eventType = 'metadata';

    /**
     * @var Config
     */
    private $config;
    /**
     * @var array
     */
    private $agentMetaData;

    /**
     * @param array  $contexts
     * @param Config $config
     */
    public function __construct(array $contexts, Config $config, array $agentMetaData)
    {
        parent::__construct($contexts);
        $this->config        = $config;
        $this->agentMetaData = $agentMetaData;
    }

    /**
     * Generate request data.
     *
     * @return array
     */
    final public function jsonSerialize(): array
    {
        return [
            $this->eventType => [
                'service' => [
                    'name'      => Encoding::keywordField($this->config->serviceName()),
                    'version'   => Encoding::keywordField($this->config->serviceVersion()),
                    'framework' => [
                        'name'    => $this->config->frameworkName(),
                        'version' => $this->config->frameworkVersion(),
                    ],
                    'language' => [
                        'name'    => 'php',
                        'version' => phpversion(),
                    ],
                    'process' => [
                        'pid' => getmypid(),
                    ],
                    'agent'       => $this->agentMetaData,
                    'environment' => Encoding::keywordField($this->config->environment()),
                ],
                'system' => [
                    'hostname'     => Encoding::keywordField($this->config->hostname()),
                    'container'    => ['id' => config('elastic-apm-laravel.container.id')],
                    'architecture' => php_uname('m'),
                    'platform'     => php_uname('s'),
                ],
            ],
        ];
    }
}
