<?php

declare(strict_types=1);

namespace Obtrace\Sdk\Laravel;

use Illuminate\Support\ServiceProvider;
use Obtrace\Sdk\ObtraceClient;
use Obtrace\Sdk\ObtraceConfig;

class ObtraceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ObtraceClient::class, function ($app) {
            $config = $app['config']->get('obtrace', []);
            return new ObtraceClient(new ObtraceConfig(
                apiKey: $config['api_key'] ?? '',
                serviceName: $config['service_name'] ?? $app['config']->get('app.name', 'laravel'),
                ingestBaseUrl: $config['ingest_base_url'] ?? 'https://ingest.obtrace.ai',
                tenantId: $config['tenant_id'] ?? null,
                projectId: $config['project_id'] ?? null,
                appId: $config['app_id'] ?? null,
                env: $config['env'] ?? $app->environment(),
                serviceVersion: $config['service_version'] ?? '1.0.0',
                defaultHeaders: $config['default_headers'] ?? [],
                validateSemanticMetrics: $config['validate_semantic_metrics'] ?? false,
                debug: $config['debug'] ?? false,
            ));
        });
    }

    public function boot(): void
    {
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', ObtraceMiddleware::class);
        $router->pushMiddlewareToGroup('api', ObtraceMiddleware::class);
    }
}
