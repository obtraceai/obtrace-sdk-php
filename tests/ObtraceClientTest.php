<?php

declare(strict_types=1);

namespace Obtrace\Sdk\Tests;

use Obtrace\Sdk\ObtraceClient;
use Obtrace\Sdk\ObtraceConfig;
use PHPUnit\Framework\TestCase;

final class ObtraceClientTest extends TestCase
{
    private function makeConfig(array $overrides = []): ObtraceConfig
    {
        return new ObtraceConfig(
            apiKey: $overrides['apiKey'] ?? 'test-key',
            ingestBaseUrl: $overrides['ingestBaseUrl'] ?? 'https://ingest.test',
            serviceName: $overrides['serviceName'] ?? 'test-svc',
            autoInstrumentHttp: $overrides['autoInstrumentHttp'] ?? false,
        );
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();
    }

    public function testConstructorRequiresApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ObtraceClient($this->makeConfig(['apiKey' => '']));
    }

    public function testConstructorRequiresIngestBaseUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ObtraceClient($this->makeConfig(['ingestBaseUrl' => '']));
    }

    public function testConstructorRequiresServiceName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ObtraceClient($this->makeConfig(['serviceName' => '']));
    }

    public function testSpanReturnsTraceAndSpanIds(): void
    {
        $client = new ObtraceClient($this->makeConfig());
        $result = $client->span("test.span");
        $this->assertArrayHasKey("trace_id", $result);
        $this->assertArrayHasKey("span_id", $result);
        $this->assertSame(32, strlen($result["trace_id"]));
        $this->assertSame(16, strlen($result["span_id"]));
    }

    public function testSpanUsesProvidedIds(): void
    {
        $client = new ObtraceClient($this->makeConfig());
        $traceId = "0123456789abcdef0123456789abcdef";
        $spanId = "fedcba9876543210";
        $result = $client->span("test.span", traceId: $traceId, spanId: $spanId);
        $this->assertSame($traceId, $result["trace_id"]);
        $this->assertSame($spanId, $result["span_id"]);
    }

    public function testLogDoesNotThrow(): void
    {
        $client = new ObtraceClient($this->makeConfig());
        $client->log("info", "test message", ["key" => "value"]);
        $this->assertTrue(true);
    }

    public function testMetricDoesNotThrow(): void
    {
        $client = new ObtraceClient($this->makeConfig());
        $client->metric("test.metric", 42.0, "ms");
        $this->assertTrue(true);
    }

    public function testAutoInstrumentHttpDefaultTrue(): void
    {
        $cfg = new ObtraceConfig(
            apiKey: 'key',
            ingestBaseUrl: 'https://ingest.test',
            serviceName: 'svc',
        );
        $this->assertTrue($cfg->autoInstrumentHttp);
    }

    public function testAutoInstrumentHttpCanBeDisabled(): void
    {
        $cfg = new ObtraceConfig(
            apiKey: 'key',
            ingestBaseUrl: 'https://ingest.test',
            serviceName: 'svc',
            autoInstrumentHttp: false,
        );
        $this->assertFalse($cfg->autoInstrumentHttp);
    }

    public function testHandleErrorChainsToAPreviousHandler(): void
    {
        $called = false;
        $prev = set_error_handler(function () use (&$called) {
            $called = true;
            return true;
        });
        $client = new ObtraceClient($this->makeConfig());
        $client->handleError(E_USER_NOTICE, "test notice", "file.php", 42);
        $this->assertTrue($called);
    }

    public function testHandleExceptionChainsToPreviousHandler(): void
    {
        $called = false;
        set_exception_handler(function () use (&$called) {
            $called = true;
        });
        $client = new ObtraceClient($this->makeConfig());
        $client->handleException(new \RuntimeException("test"));
        $this->assertTrue($called);
    }
}
