<?php

namespace Prezly\KubernetesClient;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Utils;
use InvalidArgumentException;
use Prezly\KubernetesClient\Exceptions\RequestException;
use Prezly\KubernetesClient\Exceptions\ResponseException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class KubernetesClient
{
    private HttpClient $client;

    private LoggerInterface $logger;

    public function __construct(HttpClient $client, LoggerInterface $logger = null)
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
            $response = $this->client->get(
                $uri,
                $body !== null ? ['json' => (object) $body] : [],
            );
        } catch (GuzzleException $exception) {
            throw new RequestException("Failed requesting {$method} on `{$uri}`.", 0, $exception);
        }

        try {
            return Utils::jsonDecode($response->getBody()->getContents(), true);
        } catch (InvalidArgumentException $exception) {
            throw new ResponseException("Failed decoding response JSON: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function watch(string $endpoint, callable $watcher, callable $initialize = null): void
    {
        $endpoint = new Uri($endpoint);

        do {
            try {
                $this->doWatch($endpoint, $watcher, $initialize);
            } catch (GuzzleException $exception) {
                $this->logger->warning("Caught exception: {$exception->getMessage()} ({$exception->getCode()})", [
                    'exception' => [
                        'class'   => get_class($exception),
                        'message' => $exception->getMessage(),
                        'code'    => $exception->getCode(),
                    ],
                ]);
                $this->logger->notice('Retrying in 5s');
                sleep(5);
            }
        } while (true);
    }

    private function doWatch(UriInterface $endpoint, callable $watcher, callable $initializer = null): void
    {
        $resourceVersion = $initializer ? $this->initializeWatch($endpoint, $initializer) : null;

        $this->logger->info('Starting watcher');

        $response = $this->client->get(
            Uri::withQueryValues($endpoint, array_filter([
                'watch'           => 1,
                /**
                 * @see https://kubernetes.io/docs/reference/using-api/api-concepts/#the-resourceversion-parameter
                 */
                'resourceVersion' => $resourceVersion,
            ])),
            [
                'stream'       => true,
                'read_timeout' => PHP_INT_MAX,
            ],
        );

        foreach ($this->decodeNDJsonStream($response->getBody()) as $record) {
            $watcher($record);
        }
    }

    private function initializeWatch(UriInterface $endpoint, callable $initializer): string
    {
        $this->logger->info('Initializing watch base resourceVersion');

        $response = $this->client->get($endpoint);

        $data = Utils::jsonDecode($response->getBody()->getContents(), true);

        $initializer($data);

        return $data['metadata']['resourceVersion'];
    }

    /**
     * @param StreamInterface $stream
     * @return iterable
     */
    private function decodeNDJsonStream(StreamInterface $stream): iterable
    {
        $buffer = '';

        while (! $stream->eof()) {
            $byte = $stream->read(1);
            $buffer .= $byte;

            if ($byte === "\n") {
                $decoded = json_decode($buffer, $assoc = true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    yield $decoded;
                    $buffer = '';
                }
            }
        }
    }

    private function withQueryValues(string $uri, array $query): string
    {
        if (empty($query)) {
            return $uri;
        }

        return (string) Uri::withQueryValues(new Uri($uri), $query);
    }
}
