<?php

use GuzzleHttp\Client as HttpClient;
use Prezly\KubernetesClient\KubernetesClientFactory;
use Prezly\KubernetesClient\Tests\Utils\Xray;
use Psr\Log\Test\TestLogger;

describe('KubernetesClientFactory', function () {
    it('should instantiate', function () {
        $factory = new KubernetesClientFactory();
        assert($factory instanceof KubernetesClientFactory);
    });

    it('should construct a KubernetesClient instance with the defined logger instance', function () {
        $factory = KubernetesClientFactory::connectTo('https://api.kubernetes.local/')
            ->withLogger($logger = new TestLogger());

        $client = $factory->constructClient();

        assert($logger === Xray::property($client, 'logger'));
    });

    it('should construct a KubernetesClient instance with a pre-configured Guzzle HTTP client', function () {
        $factory = KubernetesClientFactory::connectTo('https://api.kubernetes.local/')
            ->withAccessToken('S3cR3770K3N')
            ->withCertificateAuthority('/app/kubernetes.ca')
            ->withClientCertificate('/app/client.ca', 'pa55word')
            ->withPrivateSslKey('/app/client.key', 's3cr3t');

        $client = $factory->constructClient();

        $http = Xray::property($client, 'client');

        assert($http instanceof HttpClient);

        $config = $http->getConfig();

        assert((string) $config['base_uri'] === 'https://api.kubernetes.local/');
        assert($config['cert'] === ['/app/client.ca', 'pa55word']);
        assert($config['ssl_key'] === ['/app/client.key', 's3cr3t']);
        assert($config['verify'] === '/app/kubernetes.ca');
        assert($config['headers']['Authorization'] === 'Bearer S3cR3770K3N');
    });
});
