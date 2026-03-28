<?php

declare(strict_types=1);

namespace Obtrace\Sdk\Tests;

use Obtrace\Sdk\HttpInstrumentation;
use Obtrace\Sdk\ObtraceClient;
use Obtrace\Sdk\ObtraceConfig;
use PHPUnit\Framework\TestCase;

final class HttpInstrumentationTest extends TestCase
{
    public function testRegisterSetsClient(): void
    {
        $cfg = new ObtraceConfig(
            apiKey: 'key',
            ingestBaseUrl: 'https://ingest.test',
            serviceName: 'svc',
            autoInstrumentHttp: false,
        );
        $client = new ObtraceClient($cfg);
        HttpInstrumentation::register($client);
        $this->assertSame($client, HttpInstrumentation::getClient());
    }

    public function testGuzzleMiddlewareReturnsCallable(): void
    {
        $middleware = HttpInstrumentation::guzzleMiddleware();
        $this->assertIsCallable($middleware);
    }

    public function testAutoInstrumentRegistersOnConstruct(): void
    {
        $cfg = new ObtraceConfig(
            apiKey: 'key',
            ingestBaseUrl: 'https://ingest.test',
            serviceName: 'svc',
            autoInstrumentHttp: true,
        );
        $client = new ObtraceClient($cfg);
        $this->assertSame($client, HttpInstrumentation::getClient());
    }
}
