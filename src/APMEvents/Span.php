<?php

namespace AG\ElasticApmLaravel\APMEvents;

use Nipwaayoni\Events\Span as BaseSpan;

class Span extends BaseSpan
{
    /**
     * Extended Contexts such as Custom and/or User
     *
     * @var array
     */
    private $contexts = [
        'db' => [],
        'destination' => [],
        'http' => [],
        'message' => [],
        'service' => [],
        'tags' => [],
    ];

    /**
     * Set db data in Context
     *
     * @param array $tags
     */
    public function setDBContext(array $tags)
    {
        $this->contexts['db'] = array_merge($this->contexts['db'], $tags);
    }

    /**
     * Set destination data in Context
     *
     * @param array $tags
     */
    public function setDestinationContext(array $tags)
    {
        $this->contexts['destination'] = array_merge($this->contexts['destination'], $tags);
    }

    /**
     * Set http data in Context
     *
     * @param array $tags
     */
    public function setHttpContext(array $tags)
    {
        $this->contexts['http'] = array_merge($this->contexts['http'], $tags);
    }

    /**
     * Set message data in Context
     *
     * @param array $tags
     */
    public function setMessageContext(array $tags)
    {
        $this->contexts['message'] = array_merge($this->contexts['message'], $tags);
    }

    /**
     * Set service data in Context
     *
     * @param array $tags
     */
    public function setServiceContext(array $tags)
    {
        $this->contexts['service'] = array_merge($this->contexts['service'], $tags);
    }

    /**
     * Serialize Span Event
     *
     * @link https://www.elastic.co/guide/en/apm/server/master/span-api.html
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        $json_data = parent::jsonSerialize();

        $json_data['span']['context'] = [];
        foreach ($this->contexts as $key => $data) {
            if (empty($data) === false) {
                $json_data['span']['context'][$key] = $data;
            }
        }

        return $json_data;
    }
}
