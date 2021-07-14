<?php

namespace Prezly\KubernetesClient;

use GuzzleHttp\Client as HttpClient;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final class KubernetesClientFactory
{
    private array $config;
    private ?LoggerInterface $logger;

    public function __construct(array $config = [], LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public static function create(): self
    {
        return new self();
    }

    public static function connectTo(string $apiUri): self
    {
        return new self([
            'base_uri' => $apiUri,
        ]);
    }

    public function withLogger(LoggerInterface $logger): self
    {
        return new self($this->config, $logger);
    }

    public function withConfig(array $config): self
    {
        return new self(
            array_merge($this->config, $config),
            $this->logger,
        );
    }

    public function withHeaders(array $headers): self
    {
        $headers = array_merge(
            $this->config['headers'] ?? [],
            $headers,
        );

        return $this->withConfig([
            'headers' => $headers,
        ]);
    }

    public function withApiUri(string $apiUri): self
    {
        return $this->withConfig([
            'base_uri' => $apiUri,
        ]);
    }

    public function withoutCertificateAuthorityVerification(): self
    {
        return $this->withConfig([
            'verify' => false,
        ]);
    }

    public function withCertificateAuthority(string $certificateAuthority): self
    {
        return $this->withConfig([
            'verify' => $certificateAuthority,
        ]);
    }

    public function withClientCertificate(string $clientCertificate, ?string $password = null): self
    {
        return $this->withConfig([
            'cert' => $password ? [$clientCertificate, $password] : $clientCertificate,
        ]);
    }

    public function withPrivateSslKey(string $privateKey, ?string $password = null): self
    {
        return $this->withConfig([
            'ssl_key' => $password ? [$privateKey, $password] : $privateKey,
        ]);
    }

    public function withAccessToken(string $accessToken): self
    {
        if (substr($accessToken, 0, 7) === 'file://' || substr($accessToken, 0, 7) === 'data://') {
            $accessToken = $this->readFile($accessToken);
        }

        return $this->withHeaders([
            'Authorization' => "Bearer {$accessToken}",
        ]);
    }

    public function constructClient(): KubernetesClient
    {
        return new KubernetesClient(
            new HttpClient($this->config),
            $this->logger,
        );
    }

    private function readFile(string $uri): string
    {
        $contents = file_get_contents($uri);

        if ($contents === false) {
            throw new InvalidArgumentException("Failed to read contents from `{$uri}`.");
        }

        return $contents;
    }
}
