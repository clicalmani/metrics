# clicalmani/metrics

A metrics package for the [Tonka PHP Framework](https://clicalmani.github.io/tonka).

It gives your application a single, fluent entry point for counters, gauges and timings — with support for global and scoped tags, pattern-based sampling, cardinality protection and multiple backends (in-memory, log file or StatsD over UDP).

## Features

- **Simple API**: `increment`, `decrement`, `gauge`, `timing` and `measure` on one manager.
- **Tags**: global defaults, per-call overrides and scoped tags with automatic restoration.
- **Sampling**: reduce volume with `fnmatch` pattern rules (`pattern => rate`) or bring your own sampler.
- **Cardinality guard**: cap the number of unique tag series per metric to protect your backend.
- **Three drivers**: in-memory (introspectable, great for tests), log file, or StatsD (UDP with buffering and datagram packing).
- **Auto-instrumentation** (optional): HTTP requests, database queries, queue jobs and notifications.

## Requirements

- PHP >= 8.3

## Installation

```bash
composer require clicalmani/metrics
```

## Configuration

Create a `config/metrics.php` file in your Tonka application:

```php
<?php

return [
    // 'memory' (default), 'log' or 'statsd'
    'driver' => 'memory',

    // Use the in-memory driver automatically when running tests
    'fake_when_testing' => true,

    // Tags applied to every metric
    'tags' => [
        'app' => 'tonka',
        'env' => 'production',
    ],

    // Sampling rules: pattern => rate (0..1). First matching pattern wins.
    'sampling' => [
        'debug.*' => 0.0,
        'db.*'    => 0.1,
    ],

    // Max number of unique tag series per metric key (0 = disabled)
    'cardinality_limit' => 1000,

    // Auto-flush interval in seconds (0 = disabled)
    'flush_interval' => 10,

    // StatsD agent settings (used by the 'statsd' driver)
    'statsd' => [
        'host'   => '127.0.0.1',
        'port'   => 8125,
        'prefix' => 'tonka.',
    ],
];
```

The service provider registers the manager in the container under the `metrics` alias, so you can use the `Metrics` facade or resolve `Clicalmani\Metrics\MetricsManager` directly.

## Usage

### Counters, gauges and timings

```php
use Clicalmani\Core\Support\Facades\Metrics;

Metrics::increment('page.views');                    // +1
Metrics::increment('files.uploaded', 5);             // +5
Metrics::decrement('cart.items');                    // -1
Metrics::gauge('memory.usage', 134217728);           // point-in-time value
Metrics::timing('api.duration', 245.3);              // milliseconds
```

### Measuring code execution

`measure()` runs the callback, records its duration in milliseconds and returns its result. The timing is recorded even if the callback throws.

```php
$result = Metrics::measure('report.generation', function () {
    return generateReport();
});
```

### Tags

Tags are merged from three sources: global defaults, scoped tags and per-call tags (highest priority).

```php
Metrics::increment('requests', tags: ['route' => 'home']);

// Scoped tags: applied inside the callback, restored afterwards —
// even if the callback throws.
Metrics::withTags(['tenant' => 'acme'], function ($metrics) {
    $metrics->increment('requests'); // tagged tenant=acme
});
```

### Sampling

Sampling rules match metric keys with `fnmatch` patterns. The first matching pattern wins; non-matching keys are always sampled.

```php
Metrics::sampling([
    'debug.*' => 0.0,   // never sample debug metrics
    'db.*'    => 0.1,   // sample 10% of database metrics
]);
```

You can also plug in any implementation of `Clicalmani\Metrics\Sampling\Sampler` (`AlwaysSampler` and `NeverSampler` are provided):

```php
Metrics::sampler(new AlwaysSampler());
```

### Cardinality guard

High-cardinality tags (user IDs, URLs...) can overwhelm a metrics backend. The guard caps the number of unique tag series **per metric key**. Once the limit is reached, unknown series are dropped while already-known series keep flowing.

```php
Metrics::cardinalityLimit(1000, function (string $key, array $tags) {
    logger()->warning("Metrics cardinality limit exceeded for {$key}", ['tags' => $tags]);
});
```

### Flushing

With the StatsD driver, metrics are buffered and sent automatically when the buffer is full (500 entries by default) or when a configured flush interval elapses on the next emission. You can also flush manually:

```php
Metrics::flush();
Metrics::pending(); // number of buffered entries (0 for non-buffering drivers)
```

## Drivers

| Driver | Backend | Buffered | Introspectable |
|---|---|---|---|
| `InMemoryDriver` | PHP arrays | No | Yes (`all()`) |
| `LogDriver` | Framework logger | No | No |
| `StatsdDriver` | StatsD agent (UDP) | Yes | No |

The `InMemoryDriver` is handy for tests: it exposes everything it recorded.

```php
Metrics::increment('hits', 3);
$metrics = Metrics::all();
// ['counters' => ['hits' => 3], 'gauges' => [...], 'timings' => [...]]
```

Tagged series are stored under keys like `requests[method=GET,route=home]` (tags sorted alphabetically).

The `StatsdDriver` speaks the StatsD line protocol with DogStatsD-style tags and packs multiple metrics into datagrams of at most 1432 bytes:

```
tonka.http.requests:1|c|#method:GET,route:home
```

### Swapping drivers at runtime

```php
Metrics::setDriver(new InMemoryDriver());

// Or instantiate one by class name with constructor arguments
Metrics::use(LogDriver::class, logger());
```

You can implement your own driver by implementing the `Clicalmani\Metrics\Contracts\MetricsDriver` interface — add `Clicalmani\Metrics\Contracts\FlushableDriver` as well to support `pending()` and interval flushing.

## Auto-instrumentation

The package can hook into Tonka events to emit metrics automatically. Enable the sections you need:

```php
use Clicalmani\Metrics\AutoInstrumentation;

AutoInstrumentation::register([
    'http'          => true,  // http.request.count, http.request.duration, http.response.size, http.client_error, http.server_error
    'database'      => true,  // db.query.count, db.query.duration
    'queue'         => true,  // queue.job.pushed, queue.job.processed, queue.job.failed
    'notifications' => true,  // notifications.sending, notifications.sent, notifications.duration, notifications.failed, notifications.skipped
]);
```

## Testing

The package ships with a PHPUnit 12 suite:

```bash
composer test
```

Set `fake_when_testing` to `true` in `config/metrics.php` so the application automatically uses the in-memory driver in the `testing` environment — then assert against `Metrics::all()`.

## License

[MIT](LICENSE)
