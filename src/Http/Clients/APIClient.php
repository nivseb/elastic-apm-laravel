<?php

namespace AG\ElasticApmLaravel\Http\Clients;

use AG\ElasticApmLaravel\Events\StartMeasuring;
use AG\ElasticApmLaravel\Events\StopMeasuring;
use AG\ElasticApmLaravel\Services\ApmCollectorService;
use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7;
use Illuminate\Contracts\Container\BindingResolutionException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Throwable;

class APIClient extends Client
{
    /** @var array $muted_status_codes */
    protected $muted_status_codes;

    public function __construct(array $config = [], array $muted_status_codes = [])
    {
        parent::__construct($config);

        $this->muted_status_codes = $muted_status_codes;
    }

    /**
     * @throws Throwable
     * @throws BindingResolutionException
     */
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        return $this->measureAction(
            $request->getMethod(),
            $request->getUri(),
            function () use ($request, $options) {
                return parent::sendAsync($request, $options);
            }
        );
    }

    /**
     * @throws Throwable
     * @throws BindingResolutionException
     */
    public function requestAsync(string $method, $uri = '', array $options = []): PromiseInterface
    {
        return $this->measureAction(
            $method,
            Psr7\Utils::uriFor($uri),
            function () use ($method, $uri, $options) {
                return parent::requestAsync($method, $uri, $options);
            }
        );
    }

    /**
     * @throws Throwable
     * @throws BindingResolutionException
     */
    protected function measureAction(string $method, UriInterface $uri, Closure $action): PromiseInterface
    {
        if (!class_exists(StartMeasuring::class) || !class_exists(StopMeasuring::class)) {
            return $action();
        }

        event(new StartMeasuring($method.' '.$uri->getPath(), 'request.http', strtolower($method)));

        return $action()->then(
            function (ResponseInterface $response) use ($method, $uri) {
                $context = [];
                $this->fillContextFromRequest($context, $method, $uri);
                $this->fillContextFromResponse($context, $response);

                event(new StopMeasuring($method.' '.$uri->getPath(), $context));

                return $response;
            },
            function (Throwable $exception) use ($method, $uri): void {
                $context = [];
                $this->fillContextFromRequest($context, $method, $uri);
                $this->fillContextFromException($context, $exception);

                if (!$this->isMuted($exception)) {
                    app(ApmCollectorService::class)->captureThrowable($exception);
                }
                event(new StopMeasuring($method.' '.$uri->getPath(), $context));

                throw $exception;
            }
        );
    }

    protected function isMuted(Throwable $throwable): bool
    {
        if (!($throwable instanceof RequestException) || !$throwable->hasResponse()) {
            return false;
        }

        $status_code = $throwable->getResponse()->getStatusCode();

        if (in_array($status_code, $this->muted_status_codes, true)) {
            return true;
        }

        return false;
    }

    protected function fillContextFromRequest(array &$context, string $method, UriInterface $uri): void
    {
        $context['http']['method'] = strtoupper($method);
        $context['http']['url']    = (string) $uri;
    }

    protected function fillContextFromResponse(array &$context, ResponseInterface $response): void
    {
        $context['http']['response']['status_code'] = $response->getStatusCode();
    }

    protected function fillContextFromException(array &$context, Throwable $exception): void
    {
        $context['message']['headers']['exception_code'] = (string) $exception->getCode();
        $context['message']['body']                      = $exception->getMessage();
        if ($exception instanceof RequestException && $exception->hasResponse()) {
            $this->fillContextFromResponse($context, $exception->getResponse());
            if (!empty($exception->getResponse()->getBody()->getContents())) {
                $context['message']['body'] = $exception->getResponse()->getBody()->getContents();
            }
        }
    }
}
