<?php

namespace Prezly\KubernetesClient;

use GuzzleHttp\ClientInterface as HttpClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Utils;
use InvalidArgumentException;
use Prezly\KubernetesClient\Exceptions\KubernetesClientException;
use Prezly\KubernetesClient\Exceptions\RequestException;
use Prezly\KubernetesClient\Exceptions\ResponseException;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class KubernetesClient
{
    private HttpClientInterface $client;

    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param string $uri
     * @param array $query
     * @return array
     *
     * @throws RequestException
     * @throws ResponseException
     */
    public function get(string $uri, array $query = []): array
    {
        return $this->request('GET', $this->withQueryValues($uri, $query));
    }

    /**
     * @param string $uri
     * @param array $body
     * @param array $query
     * @return array
     *
     * @throws RequestException
     * @throws ResponseException
     */
    public function post(string $uri, array $body = [], array $query = []): array
    {
        return $this->request('POST', $this->withQueryValues($uri, $query), $body);
    }

    /**
     * @param string $uri
     * @param array $body
     * @param array $query
     * @return array
     *
     * @throws RequestException
     * @throws ResponseException
     */
    public function put(string $uri, array $body = [], array $query = []): array
    {
        return $this->request('PUT', $this->withQueryValues($uri, $query), $body);
    }

    /**
     * @param string $uri
     * @param array $body
     * @param array $query
     * @return array
     *
     * @throws RequestException
     * @throws ResponseException
     */
    public function patch(string $uri, array $body = [], array $query = []): array
    {
        return $this->request('PATCH', $this->withQueryValues($uri, $query), $body);
    }

    /**
     * @param string $uri
     * @param array $query
     * @return array
     *
     * @throws RequestException
     * @throws ResponseException
     */
    public function delete(string $uri, array $query = []): array
    {
        return $this->request('DELETE', $this->withQueryValues($uri, $query));
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array|null $body
     * @return array
     *
     * @throws RequestException
     * @throws ResponseException
     */
    public function request(string $method, string $uri, array $body = null): array
    {
        $method = strtoupper($method);

        try {
            $response = $this->client->request(
                $method,
                $uri,
                $body !== null ? ['json' => (object) $body] : [],
            );
        } catch (GuzzleException $exception) {
            throw new RequestException("Failed requesting {$method} on `{$uri}`.", 0, $exception);
        }

        return $this->decodeResponseJson($response->getBody()->getContents());
    }

    /**
     * @param string $uri
     * @param callable $watcher
     * @param callable|null $initialize
     */
    public function watch(string $uri, callable $watcher, callable $initialize = null): void
    {
        $uri = new Uri($uri);

        do {
            $continue = null;
            try {
                $continue = $this->doWatch($uri, $watcher, $initialize);
            } catch (KubernetesClientException $exception) {
                $this->logger->warning("Caught exception: {$exception->getMessage()}", [
                    'exception' => [
                        'class'   => get_class($exception),
                        'message' => $exception->getMessage(),
                        'code'    => $exception->getCode(),
                    ],
                ]);
                $this->logger->notice('Retrying in 5s');
                sleep(5);
            }
        } while ($continue !== false);
    }

    /**
     * @param string $uri
     * @param callable $watcher
     * @param callable|null $initializer
     * @return bool
     * @throws RequestException
     * @throws ResponseException
     */
    private function doWatch(string $uri, callable $watcher, callable $initializer = null): bool
    {
        $resourceVersion = $initializer ? $this->initializeWatch($uri, $initializer) : null;

        $this->logger->info('Starting watcher');

        $uri = $this->withQueryValues($uri, array_filter([
            'watch'           => 1,
            /**
             * @see https://kubernetes.io/docs/reference/using-api/api-concepts/#the-resourceversion-parameter
             */
            'resourceVersion' => $resourceVersion,
        ]));

        try {
            $response = $this->client->request('GET', $uri, [
                'stream'       => true,
                'read_timeout' => PHP_INT_MAX,
            ]);
        } catch (GuzzleException $exception) {
            throw new RequestException("Failed requesting GET on `{$uri}`.", 0, $exception);
        }

        foreach ($this->decodeJsonStream($response->getBody()) as $record) {
            $continue = $watcher($record);

            if ($continue === false) {
                $this->logger->notice('Watcher callback returned `false`. Exiting.');
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $uri
     * @param callable $initializer
     * @return string
     * @throws RequestException
     * @throws ResponseException
     */
    private function initializeWatch(string $uri, callable $initializer): string
    {
        $this->logger->info("Initializing watch base resourceVersion for `{$uri}`.");

        $response = $this->get($uri);

        $initializer($response);

        return $response['metadata']['resourceVersion'];
    }

    /**
     * @param StreamInterface $stream
     * @return iterable
     * @throws ResponseException
     */
    private function decodeJsonStream(StreamInterface $stream): iterable
    {
        $buffer = '';

        while (! $stream->eof()) {
            $byte = $stream->read(1);
            $buffer .= $byte;

            if ($byte === "\n") {
                yield $this->decodeResponseJson($buffer);
                $buffer = '';
            }
        }

        if ($buffer) {
            yield $buffer;
        }
    }

    /**
     * @param string $uri
     * @param array<string,string> $query
     * @return string
     */
    private function withQueryValues(string $uri, array $query): string
    {
        if (empty($query)) {
            return $uri;
        }

        return (string) Uri::withQueryValues(new Uri($uri), $query);
    }

    /**
     * @param string $contents
     * @return array|bool|float|int|object|string|null
     * @throws ResponseException
     */
    private function decodeResponseJson(string $contents)
    {
        try {
            return Utils::jsonDecode($contents, true);
        } catch (InvalidArgumentException $exception) {
            throw new ResponseException("Failed decoding response JSON: {$exception->getMessage()}", 0, $exception);
        }
    }
}
