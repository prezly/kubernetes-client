# Prezly's Kubernetes Client

Prezly's Kubernetes Client is a minimalistic Kubernetes API client implementation in PHP 
which allows you to list, fetch, update, delete and **watch** resources in your Kubernetes cluster.

![Tests Status](https://github.com/prezly/kubernetes-client/actions/workflows/test.yml/badge.svg)


## Features

- [Kubernetes Watch API](https://kubernetes.io/docs/reference/using-api/api-concepts/#efficient-detection-of-changes) support
- Unlimited authentication functionality
- No knowledge about any specific Kubernetes resources: it supports every resource or collection you have
- PHP 7.4+
- PHP 8.0
- Semver
- Tests


## Installation

Use [Composer](https://getcomposer.org/) package manager to add *Prezly's Kubernetes Client* to your project:

```
composer require prezly/kubernetes-client
```


## Authentication

*Prezly's Kubernetes Client* accepts a pre-configured Guzzle HTTP client as constructor argument,
so you can configure it to any exotic connection, authentication or proxy setup you may have.

```php
use GuzzleHttp\Client as HttpClient;
use Prezly\KubernetesClient\KubernetesClient;

$http = new HttpClient([
    'base_uri' => 'https://kubernetes.local/',
    'verify'   => false,
]);

$client = new KubernetesClient($http);
```

There's also a `KubernetesClientFactory` to provide a fluent API to configure a KubernetesClient
for most common use-cases: 

```php
<?php
use Prezly\KubernetesClient\KubernetesClientFactory as Factory;

$client = Factory::connectTo('https://kubernetes.companyintranet.local')
    ->withAccessToken(getenv('KUBERNETES_ACCESS_TOKEN'))
    ->withCertificateAuthority('/app/kubernetes.ca')
    ->constructClient();
    
// Interact with Kubernetes API with $client
```


## Logging

*Prezly's Kubernetes Client* can be configured with any PSR logger implementation to provide internal log
for application monitoring. This is especially recommended for long-running resource-watching *daemon applications*.

```php
use Prezly\KubernetesClient\KubernetesClient;
use Psr\Log\LoggerInterface;

$logger = new MyCustomLogger();
assert($logger instanceof LoggerInterface);

$client = new KubernetesClient($http, $logger);
```

Or you can also configure a logger with `KubernetesClientFactory` fluent API:

```php
use Prezly\KubernetesClient\KubernetesClientFactory as Factory;

$client = Factory::connectTo('https://kubernetes.companyintranet.local')
    ->withLogger(new MyCustomLogger())
    ->constructClient();
```


## API Interaction

Once you have a *KubernetesClient* instance, you can interact with Kubernetes APIs 
with the plain-simple REST client abstraction: 

- `$client->get($uri, $queryParams)` &mdash; to perform `GET` requests
- `$client->post($uri, $body, $queryParams)` &mdash; to perform `POST` requests
- `$client->put($uri, $body, $queryParams)` &mdash; to perform `PUT` requests
- `$client->patch($uri, $body, $queryParams)` &mdash; to perform `PATCH` requests
- `$client->delete($uri, $queryParams)` &mdash; to perform `DELETE` requests

```php
<?php
/** @var \Prezly\KubernetesClient\KubernetesClient $client */
$ingresses = $client->get('/apis/networking.k8s.io/v1/namespaces/default/ingresses');

foreach ($ingresses['items'] as $ingress) {
    $client->delete("/apis/networking.k8s.io/v1/namespaces/default/ingresses/{$ingress['metadata']['name']}");
}
```


## Watching resources

*KubernetesClient* implements a simple yet powerful abstraction to access Kubernetes *Watch API*:

```php
/** @var \Prezly\KubernetesClient\KubernetesClient $client */
$client->watch($url, $watcher, $initializer = null);
```

A `watch()` call starts an **infinite daemon process** that will *self-recover and retry* from any HTTP errors.
To better monitor what's going on during a watch call it is strongly recommended configuring a logger.

```php
/** @var \Prezly\KubernetesClient\KubernetesClient $client */
$client->watch('/apis/networking.k8s.io/v1/namespaces/default/ingresses', function (array $event) {
    if ($event['type'] === 'ADDED') {
        echo "Ingress `{$event['object']['metadata']['name']}` was added\n";
    }
});
```

You can also provide an *initializer* to initialize state before *watch* starts:

```php
/** @var \Prezly\KubernetesClient\KubernetesClient $client */
$client->watch(
    '/apis/networking.k8s.io/v1/namespaces/default/ingresses', 
    function (array $event) {
        if ($event['type'] === 'ADDED') {
            echo "Ingress `{$event['object']['metadata']['name']}` was added\n";
        }
    },
    function (array $ingresses) {
        foreach ($ingresses['items'] as $ingress) {
            echo "Ingress `{$ingress['metadata']['name']}` existed before the watcher started\n";
        }
    }
);
```

By default, the watcher will run indefinitely, but you can return `false` from your *watch* callback to force it exit.

```php
/** @var \Prezly\KubernetesClient\KubernetesClient $client */
$client->watch('/apis/networking.k8s.io/v1/namespaces/default/ingresses', function (array $event) {
    if ($event['type'] === 'DELETED') {
        return false; // force exit
    }
});
```

```php
/** @var \Prezly\KubernetesClient\KubernetesClient $client */
$ingresses = $client->get('/apis/networking.k8s.io/v1/namespaces/default/ingresses');
```


## Credits

Brought to you with :heart: by [Prezly](https://www.prezly.com/?utm_source=github&utm_campaign=prezly/kubernetes-client)

