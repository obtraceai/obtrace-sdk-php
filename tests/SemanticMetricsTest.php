<?php

declare(strict_types=1);

namespace Obtrace\Sdk\Tests;

use Obtrace\Sdk\SemanticMetrics;
use PHPUnit\Framework\TestCase;

final class SemanticMetricsTest extends TestCase
{
    public function testRuntimeCpuConstant(): void
    {
        $this->assertSame("runtime.cpu.utilization", SemanticMetrics::RUNTIME_CPU_UTILIZATION);
    }

    public function testDbOperationLatencyConstant(): void
    {
        $this->assertSame("db.operation.latency", SemanticMetrics::DB_OPERATION_LATENCY);
    }

    public function testWebVitalInpConstant(): void
    {
        $this->assertSame("web.vital.inp", SemanticMetrics::WEB_VITAL_INP);
    }

    public function testIsSemanticMetricRecognizesKnownMetrics(): void
    {
        $this->assertTrue(SemanticMetrics::isSemanticMetric(SemanticMetrics::WEB_VITAL_INP));
        $this->assertTrue(SemanticMetrics::isSemanticMetric(SemanticMetrics::THROUGHPUT));
    }

    public function testIsSemanticMetricRejectsCustomMetrics(): void
    {
        $this->assertFalse(SemanticMetrics::isSemanticMetric("orders.count"));
    }
}
