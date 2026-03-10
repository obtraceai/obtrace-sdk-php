<?php

declare(strict_types=1);

require_once __DIR__ . "/../src/SemanticMetrics.php";

use Obtrace\Sdk\SemanticMetrics;

if (SemanticMetrics::RUNTIME_CPU_UTILIZATION !== "runtime.cpu.utilization") {
    fwrite(STDERR, "runtime metric mismatch\n");
    exit(1);
}

if (SemanticMetrics::DB_OPERATION_LATENCY !== "db.operation.latency") {
    fwrite(STDERR, "db metric mismatch\n");
    exit(1);
}

if (SemanticMetrics::WEB_VITAL_INP !== "web.vital.inp") {
    fwrite(STDERR, "web vital mismatch\n");
    exit(1);
}

if (!SemanticMetrics::isSemanticMetric(SemanticMetrics::WEB_VITAL_INP)) {
    fwrite(STDERR, "semantic metric should be recognized\n");
    exit(1);
}

if (SemanticMetrics::isSemanticMetric("orders.count")) {
    fwrite(STDERR, "custom metric should not be recognized as semantic\n");
    exit(1);
}
