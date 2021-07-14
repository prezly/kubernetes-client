<?php

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface as HttpClientInterface;
use GuzzleHttp\Psr7\PumpStream;
use GuzzleHttp\Psr7\Response;
use Prezly\KubernetesClient\KubernetesClient;
use Prezly\KubernetesClient\Tests\Utils\StderrLogger;

describe('KubernetesClient', function () {
    it('should instantiate', function () {
        $client = new KubernetesClient(new HttpClient());
        assert($client instanceof KubernetesClient);
    });

    it('should submit GET requests to Kubernetes API', function () {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->allows()
            ->request('GET', '/apis/networking.k8s.io/v1/namespaces/default/ingresses?labelSelector=method%3Dget', [])
            ->andReturns(new Response(200, [], '{"success": true}'));

        $client = new KubernetesClient($http, new StderrLogger());

        $data = $client->get('/apis/networking.k8s.io/v1/namespaces/default/ingresses', [
            'labelSelector' => 'method=get',
        ]);

        assert($data === ['success' => true]);
    });

    it('should submit POST requests to Kubernetes API', function () {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->allows()
            ->request('POST', '/apis/networking.k8s.io/v1/namespaces/default/ingresses?labelSelector=method%3Dpost', [
                'json' => (object) ['kind' => 'Ingress'],
            ])
            ->andReturns(new Response(201, [], '{"success": true}'));

        $client = new KubernetesClient($http, new StderrLogger());

        $data = $client->post(
            '/apis/networking.k8s.io/v1/namespaces/default/ingresses',
            ['kind' => 'Ingress'],
            ['labelSelector' => 'method=post']
        );

        assert($data === ['success' => true]);
    });

    it('should submit PUT requests to Kubernetes API', function () {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->allows()
            ->request('PUT', '/api/v1/namespaces/default/services?labelSelector=method%3Dput', [
                'json' => (object) ['kind' => 'Service'],
            ])
            ->andReturns(new Response(200, [], '{"success": true}'));

        $client = new KubernetesClient($http, new StderrLogger());

        $data = $client->put(
            '/api/v1/namespaces/default/services',
            ['kind' => 'Service'],
            ['labelSelector' => 'method=put']
        );

        assert($data === ['success' => true]);
    });

    it('should submit PATCH requests to Kubernetes API', function () {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->allows()
            ->request('PATCH', '/api/v1/namespaces/default/services/test-backend?additional=patch', [
                'json' => (object) ['metadata' => ['name' => 'production-backend']],
            ])
            ->andReturns(new Response(200, [], '{"success": true}'));

        $client = new KubernetesClient($http);

        $data = $client->patch(
            '/api/v1/namespaces/default/services/test-backend',
            ['metadata' => ['name' => 'production-backend']],
            ['additional' => 'patch'],
        );

        assert($data === ['success' => true]);
    });

    it('should submit DELETE requests to Kubernetes API', function () {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->allows()
            ->request('DELETE', '/api/v1/namespaces/default/services/production-backend?resourceVersion=0001', [])
            ->andReturns(new Response(200, [], '{"success": true}'));

        $client = new KubernetesClient($http, new StderrLogger());

        $data = $client->delete(
            '/api/v1/namespaces/default/services/production-backend',
            ['resourceVersion' => '0001'],
        );

        assert($data === ['success' => true]);
    });

    it('should watch resource collections events via Kubernetes Watch API', function () {
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm(5);
        }

        $stream = [
            time()     => '{"type":"ADDED","object":{"kind":"Service","...":"..."}}' . "\n",
            time() + 1 => '{"type":"MODIFIED","object":{"kind":"Service","...":"..."}}' . "\n",
            time() + 2 => '{"type":"DELETED","object":{"kind":"Service","...":"..."}}' . "\n",
        ];

        $http = Mockery::mock(HttpClientInterface::class);
        $http->allows()
            ->request('GET', '/api/v1/namespaces/default/services?watch=1', ['stream' => true, 'read_timeout' => PHP_INT_MAX])
            ->andReturns(
                new Response(200, [], new PumpStream(function () use (& $stream) {
                    if (empty($stream)) {
                        return null; // EOF
                    }

                    foreach (array_keys($stream) as $time) {
                        if ($time <= time()) {
                            $portion = $stream[$time];
                            unset($stream[$time]);
                            return $portion;
                        }
                    }

                    return '';
                }))
            );

        $client = new KubernetesClient($http, new StderrLogger());
        $events = [];

        $client->watch(
            '/api/v1/namespaces/default/services',
            function (array $event) use (& $events) {
                $events[] = $event;

                return count($events) < 3; // continue util we have 3 events
            },
        );

        assert($events === [
            ['type' => 'ADDED', 'object' => ['kind' => 'Service', '...' => '...']],
            ['type' => 'MODIFIED', 'object' => ['kind' => 'Service', '...' => '...']],
            ['type' => 'DELETED', 'object' => ['kind' => 'Service', '...' => '...']],
        ]);
    });

    it('should query existing resources before watching collections events if initializer is provided', function () {
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm(5);
        }

        $http = Mockery::mock(HttpClientInterface::class);
        $http->allows()
            ->request('GET', '/api/v1/namespaces/default/services', [])
            ->andReturns(
                new Response(200, [], <<<JSON
                    {
                        "apiVersion": "v1",
                        "kind": "ServiceList",
                        "metadata": {
                            "resourceVersion": "0002"
                        },
                        "items": [
                            {"kind": "Service", "metadata": {"name": "service-01"}},
                            {"kind": "Service", "metadata": {"name": "service-02"}}
                        ]
                    }
                    JSON)
            );

        $http->allows()
            ->request('GET', '/api/v1/namespaces/default/services?watch=1&resourceVersion=0002', ['stream' => true, 'read_timeout' => PHP_INT_MAX])
            ->andReturns(
                new Response(200, [], <<<JSON
                    {"type": "ADDED", "object": {"kind": "Service", "metadata": {"name": "service-03"} } }
                    {"type": "MODIFIED", "object": {"kind": "Service", "metadata": {"name": "service-02"} } }
                    {"type": "DELETED", "object": {"kind": "Service", "metadata": {"name": "service-01"} } }

                    JSON)
            );

        $client = new KubernetesClient($http, new StderrLogger());
        $initial = [];
        $events = [];

        $client->watch(
            '/api/v1/namespaces/default/services',
            function (array $event) use (& $events) {
                $events[] = $event;

                return count($events) < 3; // continue util we have 3 events
            },
            function (array $data) use (& $initial) {
                $initial = $data;
            }
        );

        assert($initial === [
            'apiVersion' => 'v1',
            'kind'       => 'ServiceList',
            'metadata'   => [
                'resourceVersion' => '0002',
            ],
            'items'      => [
                ['kind' => 'Service', 'metadata' => ['name' => 'service-01']],
                ['kind' => 'Service', 'metadata' => ['name' => 'service-02']],
            ],
        ]);

        assert($events === [
            ['type' => 'ADDED', 'object' => ['kind' => 'Service', 'metadata' => ['name' => 'service-03']]],
            ['type' => 'MODIFIED', 'object' => ['kind' => 'Service', 'metadata' => ['name' => 'service-02']]],
            ['type' => 'DELETED', 'object' => ['kind' => 'Service', 'metadata' => ['name' => 'service-01']]],
        ]);
    });
});
