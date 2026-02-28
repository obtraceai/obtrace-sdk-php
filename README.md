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

Current workspace usage:

```php
require_once __DIR__ . "/src/ObtraceClient.php";
```

## Configuration

Required:
- `apiKey`
- `ingestBaseUrl`
- `serviceName`

Recommended:
- `tenantId`
- `projectId`
- `appId`
- `env`
- `serviceVersion`

## Quickstart

```php
<?php
require_once __DIR__ . "/src/ObtraceClient.php";
require_once __DIR__ . "/src/Types.php";

use Obtrace\Sdk\ObtraceClient;
use Obtrace\Sdk\ObtraceConfig;

$cfg = new ObtraceConfig(
    apiKey: "<API_KEY>",
    ingestBaseUrl: "https://injet.obtrace.ai",
    serviceName: "php-api"
);

$client = new ObtraceClient($cfg);
$client->log("info", "started");
$client->metric("orders.count", 1);
$client->span("job.process");
$client->flush();
```

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
- `specs/sdk/universal-contract-v1.md`
