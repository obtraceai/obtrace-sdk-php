<?php

declare(strict_types=1);

require_once __DIR__ . "/../src/ObtraceClient.php";
require_once __DIR__ . "/../src/Framework.php";
require_once __DIR__ . "/../src/SemanticMetrics.php";

use Obtrace\Sdk\ObtraceClient;
use Obtrace\Sdk\ObtraceConfig;
use Obtrace\Sdk\SemanticMetrics;

$cfg = new ObtraceConfig(
    apiKey: getenv("OBTRACE_API_KEY") ?: "test-key",
    serviceName: "php-example",
    env: "dev",
);

$client = new ObtraceClient($cfg);
$client->log("info", "php sdk initialized");
$client->metric(SemanticMetrics::RUNTIME_CPU_UTILIZATION, 0.41);
$client->span("checkout.charge", attrs: [
    "feature.name" => "checkout",
    "payment.provider" => "stripe",
]);
$client->flush();
