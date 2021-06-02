<?php

namespace Prezly\KubernetesClient;

use GuzzleHttp\Client as HttpClient;
use Psr\Log\LoggerInterface;

final class KubernetesClientFactory
{
    private array $config;
    private ?LoggerInterface $logger = null;

    public function __construct(array $config, LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
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

    public function withoutCertificateAuthorityVerification(): self
    {
        return $this->withConfig([
            'verify' => false,
        ]);
    }

    public function withCertificateAuthority(string $certificateAuthorityPath): self
    {
        return $this->withConfig([
            'verify' => $certificateAuthorityPath,
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
        return $this->withHeaders([
            'Authorization' => "Bearer {$accessToken}",
        ]);
    }

    public function constructKubernetesClient(): KubernetesClient
    {
        return new KubernetesClient(
            new HttpClient($this->config),
            $this->logger,
        );
    }
}
