<?php

namespace Prezly\KubernetesClient;

use GuzzleHttp\Client as HttpClient;
use Psr\Log\LoggerInterface;

final class KubernetesClientFactory
{
    private string $apiUri;
    private ?string $certificateAuthorityPath = null;
    private ?string $clientCertificate;
    private ?string $clientCertificatePassword;
    private ?string $privateKey;
    private ?string $privateKeyPassword = null;
    private ?string $accessToken = null;
    private ?LoggerInterface $logger = null;

    public function __construct(string $apiUri)
    {
        $this->apiUri = $apiUri;
    }

    public static function connectTo(string $apiUri): self
    {
        return new self($apiUri);
    }

    public function withCertificateAuthority(string $certificateAuthorityPath): self
    {
        $that = clone $this;
        $that->certificateAuthorityPath = $certificateAuthorityPath;

        return $that;
    }

    public function withClientCertificate(string $clientCertificate, ?string $password = null): self
    {
        $that = clone $this;
        $that->clientCertificate = $clientCertificate;
        $this->clientCertificatePassword = $password;

        return $that;
    }

    public function withPrivateSslKey(string $privateKey, ?string $password = null): self
    {
        $that = clone $this;
        $that->privateKey = $privateKey;
        $that->privateKeyPassword = $password;

        return $that;
    }

    public function withAccessToken(string $accessToken): self
    {
        $that = clone $this;
        $that->accessToken = $accessToken;

        return $that;
    }

    public function withLogger(LoggerInterface $logger): self
    {
        $that = clone $this;
        $that->logger = $logger;

        return $that;
    }

    public function constructHttpClient(): HttpClient
    {
        $config = [
            'base_uri' => $this->apiUri,
        ];

        if ($this->certificateAuthorityPath) {
            $config['verify'] = $this->certificateAuthorityPath;
        }

        if ($this->clientCertificate) {
            $config['cert'] = $this->clientCertificatePassword
                ? [$this->clientCertificate, $this->clientCertificatePassword]
                : $this->clientCertificate;
        }

        if ($this->privateKey) {
            $config['ssl_key'] = $this->privateKeyPassword
                ? [$this->privateKey, $this->privateKeyPassword]
                : $this->privateKey;
        }

        if ($this->accessToken) {
            $config['headers'] = ['Authorization' => "Bearer {$this->accessToken}"];
        }

        return new HttpClient($config);
    }

    public function constructKubernetesClient(): KubernetesClient
    {
        return new KubernetesClient(
            $this->constructHttpClient(),
            $this->logger,
        );
    }
}
