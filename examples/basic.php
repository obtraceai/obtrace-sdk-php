<?php

declare(strict_types=1);

require_once __DIR__ . "/../src/ObtraceClient.php";
require_once __DIR__ . "/../src/Framework.php";

use Obtrace\Sdk\ObtraceClient;
use Obtrace\Sdk\ObtraceConfig;

$cfg = new ObtraceConfig(
    apiKey: getenv("OBTRACE_API_KEY") ?: "test-key",
    ingestBaseUrl: getenv("OBTRACE_INGEST_BASE_URL") ?: "https://injet.obtrace.ai",
    serviceName: "php-example",
    env: "dev",
);

$client = new ObtraceClient($cfg);
$client->log("info", "php sdk initialized");
$client->metric("example.counter", 1);
$client->span("example.work");
$client->flush();
