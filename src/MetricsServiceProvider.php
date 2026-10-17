<?php
namespace Clicalmani\Metrics;

use Clicalmani\Core\Providers\ServiceProvider;
use Clicalmani\Core\Support\Facades\Event;
use Symfony\Component\DependencyInjection\Loader\Configurator\App;
use Symfony\Component\DependencyInjection\Loader\Configurator\DefaultsConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServiceConfigurator;

class MetricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        parent::register();

        if (!file_exists(config_path('/metrics.php'))) {
            return;
        }

        $config = require config_path('/metrics.php');

        if (!$config) {
            throw new \RuntimeException('Metrics configuration file not found. Please ensure that config/metrics.php exists.');
        }

        app()->addService(
            Drivers\InMemoryDriver::class,
            Drivers\InMemoryDriver::class,
            static function(ServiceConfigurator|DefaultsConfigurator $config) {
                // ...
            }
        );

        app()->addService(
            Drivers\LogDriver::class,
            Drivers\LogDriver::class,
            static function(ServiceConfigurator|DefaultsConfigurator $config) {
                $config->args([
                    app()->dependency('service', 'logger')
                ]);
            }
        );

        app()->addService(
            Drivers\StatsdDriver::class,
            Drivers\StatsdDriver::class,
            static function(ServiceConfigurator|DefaultsConfigurator $services) use($config) {
                $services->args([
                    $config['statsd']['host'] ?? '127.0.0.1', // Host
                    $config['statsd']['port'] ?? 8125,         // Port
                    $config['statsd']['prefix'] ?? 'tonka.'     // Prefix
                ]);
            }
        );

        app()->addService(
            MetricsManager::class,
            MetricsManager::class,
            static function(ServiceConfigurator|DefaultsConfigurator $services) use($config) {
                // ─────────────────────────────────────────────────────
                // Fake when testing
                // ─────────────────────────────────────────────────────
                if (($config['fake_when_testing'] ?? false)
                    && (app()->environment('testing'))) {
                    $services->args([
                        app()->dependency('service', Drivers\InMemoryDriver::class)
                    ]);
                } else {
                    // ─────────────────────────────────────────────────────
                    // Driver
                    // ─────────────────────────────────────────────────────
                    $driver = match ($config['driver'] ?? 'memory') {
                        'log'    => app()->dependency('service', Drivers\LogDriver::class),
                        'statsd' => app()->dependency('service', Drivers\StatsdDriver::class),
                        default  => app()->dependency('service', Drivers\InMemoryDriver::class),
                    };

                    $services->args([$driver]);
                }

                $services->call('__init__', [
                    $config['tags'] ?? [],
                    $config['sampling'] ?? [],
                    $config['cardinality_limit'] ?? 0,
                    $config['flush_interval'] ?? 0
                ]);
            },
            ['metrics', MetricsManager::class]
        );
    }

    public function boot(
        ?\Clicalmani\Core\Acme\Container $container = null
    ): void
    {
        if ( is_file(config_path('/metrics.php')) ) {
            app()->config->set('metrics', require config_path('/metrics.php'));
        }
    }
}