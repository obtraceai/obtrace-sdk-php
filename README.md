# obtrace-sdk-php

PHP backend SDK for Obtrace telemetry transport and instrumentation.

## Scope
- OTLP logs/traces/metrics transport
- Context propagation
- Laravel/Symfony middleware helper baseline

## Design Principle
SDK is thin/dumb.
- No business logic authority in client SDK.
- Policy and product logic are server-side.

## Install

```bash
composer require obtrace/sdk-php
```

### Auto-Instrumentation Setup

For full auto-instrumentation (automatic tracing of HTTP requests, database queries, framework operations), install the `ext-opentelemetry` PHP extension:

```bash
pecl install opentelemetry
```

Add to your `php.ini`:

```ini
extension=opentelemetry
```

Then install auto-instrumentation packages for the libraries you use:

```bash
composer require open-telemetry/contrib-auto-guzzle      # Guzzle HTTP
composer require open-telemetry/contrib-auto-pdo          # PDO databases
composer require open-telemetry/contrib-auto-laravel      # Laravel
composer require open-telemetry/contrib-auto-symfony      # Symfony
composer require open-telemetry/contrib-auto-slim         # Slim
```

Set these environment variables to enable auto-instrumentation:

```bash
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=your-service
OTEL_EXPORTER_OTLP_ENDPOINT=https://ingest.obtrace.io
OTEL_EXPORTER_OTLP_HEADERS="Authorization=Bearer obt_live_..."
```

Run the built-in setup checker to see what's installed and what's missing:

```bash
composer obtrace:check
```

The SDK will also log suggestions at startup when it detects libraries that could benefit from auto-instrumentation packages.

## Configuration

Required:
- `apiKey`
- `ingestBaseUrl`
- `serviceName`

Optional (auto-resolved from API key on the server side):
- `tenantId`
- `projectId`
- `appId`
- `env`
- `serviceVersion`

## Quickstart

### Simplified setup

The API key resolves `tenant_id`, `project_id`, `app_id`, and `env` automatically on the server side, so only three fields are needed:

```php
<?php
use Obtrace\Sdk\ObtraceClient;
use Obtrace\Sdk\ObtraceConfig;

$cfg = new ObtraceConfig(
    apiKey: "obt_live_...",
    ingestBaseUrl: "https://ingest.obtrace.io",
    serviceName: "my-service"
);

$client = new ObtraceClient($cfg);
```

### Full configuration

For advanced use cases you can override the resolved values explicitly:

```php
<?php
require_once __DIR__ . "/src/ObtraceClient.php";
require_once __DIR__ . "/src/Types.php";
require_once __DIR__ . "/src/SemanticMetrics.php";

use Obtrace\Sdk\ObtraceClient;
use Obtrace\Sdk\ObtraceConfig;
use Obtrace\Sdk\SemanticMetrics;

$cfg = new ObtraceConfig(
    apiKey: "<API_KEY>",
    ingestBaseUrl: "https://inject.obtrace.ai",
    serviceName: "php-api"
);

$client = new ObtraceClient($cfg);
$client->log("info", "started");
$client->metric(SemanticMetrics::RUNTIME_CPU_UTILIZATION, 0.41);
$client->span("checkout.charge", attrs: [
    "feature.name" => "checkout",
    "payment.provider" => "stripe",
]);
$client->flush();
```

## Canonical metrics and custom spans

- Use `SemanticMetrics::*` for globally normalized metric names.
- Custom spans are emitted with `$client->span(..., attrs: [...])`.
- Keep free-form metric names only for product-specific signals outside the shared catalog.

## Frameworks

- Middleware helper baseline for Laravel/Symfony style stacks
- Reference docs:
  - `docs/frameworks.md`

## Production Hardening

1. Keep API keys in server secret storage only.
2. Use one key per service/environment.
3. Flush queue at the end of short-lived workers/jobs.
4. Validate logs/replay ingestion after deploy.

## Troubleshooting

- Missing telemetry: check endpoint URL, key, and outgoing network.
- Runtime issues: ensure PHP version is compatible with `composer.json`.
- Transport errors: enable debug and inspect stderr output.

## Documentation
- Docs index: `docs/index.md`
- LLM context file: `llm.txt`
- MCP metadata: `mcp.json`

## Reference
