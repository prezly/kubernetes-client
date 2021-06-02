<?php

namespace Prezly\KubernetesClient;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Utils;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;

final class KubernetesClient
{
    private HttpClient $client;

    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        string $base,
        string $token,
        string $authority
    ) {
        $this->logger = $logger;

        if (substr($token, 0, 5) === 'data:' || substr($token, 0, 5) === 'file:') {
            $token = file_get_contents($token);
        }

        $this->client = new HttpClient([
            'base_uri'     => $base,
            'verify'       => $authority,
            'headers'      => [
                'Authorization' => "Bearer {$token}",
            ],
            'read_timeout' => PHP_INT_MAX,
        ]);
    }

    public function get(string $uri, array $params = []): array
    {
        $response = $this->client->get($uri, [
            'query' => $params,
        ]);

        return Utils::jsonDecode($response->getBody()->getContents(), $assoc = true);
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
        $resourceVersion = $initializer ? $this->initialize($endpoint, $initializer) : null;

        $this->logger->info('Starting watcher');

        $response = $this->client->get(
            Uri::withQueryValues($endpoint, array_filter([
                'watch'           => 1,
                /**
                 * @see https://kubernetes.io/docs/reference/using-api/api-concepts/#the-resourceversion-parameter
                 */
                'resourceVersion' => $resourceVersion,
            ])),
            ['stream' => true],
        );

        foreach ($this->decodeNDJsonStream($response->getBody()) as $record) {
            $watcher($record);
        }
    }

    private function initialize(UriInterface $endpoint, callable $initializer): string
    {
        $this->logger->info('Initializing base resourceVersion');

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
}
