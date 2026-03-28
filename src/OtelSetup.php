<?php

declare(strict_types=1);

namespace Obtrace\Sdk;

use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

final class OtelSetup
{
    private TracerProvider $tracerProvider;
    private LoggerProvider $loggerProvider;
    private MeterProvider $meterProvider;

    public function __construct(ObtraceConfig $cfg)
    {
        $headers = array_merge(
            ['Authorization' => 'Bearer ' . $cfg->apiKey],
            $cfg->defaultHeaders,
        );

        $baseUrl = rtrim($cfg->ingestBaseUrl, '/');

        $resource = ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => $cfg->serviceName,
            ResourceAttributes::SERVICE_VERSION => $cfg->serviceVersion,
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => $cfg->env ?? '',
            'runtime.name' => 'php',
            'obtrace.tenant_id' => $cfg->tenantId ?? '',
            'obtrace.project_id' => $cfg->projectId ?? '',
            'obtrace.app_id' => $cfg->appId ?? '',
            'obtrace.env' => $cfg->env ?? '',
        ]));

        $transportFactory = new OtlpHttpTransportFactory();

        $traceTransport = $transportFactory->create(
            $baseUrl . '/otlp/v1/traces',
            'application/json',
            $headers,
        );
        $spanExporter = new SpanExporter($traceTransport);
        $this->tracerProvider = TracerProvider::builder()
            ->addSpanProcessor(new SimpleSpanProcessor($spanExporter))
            ->setResource($resource)
            ->build();

        $logTransport = $transportFactory->create(
            $baseUrl . '/otlp/v1/logs',
            'application/json',
            $headers,
        );
        $logsExporter = new LogsExporter($logTransport);
        $this->loggerProvider = LoggerProvider::builder()
            ->addLogRecordProcessor(new SimpleLogRecordProcessor($logsExporter))
            ->setResource($resource)
            ->build();

        $metricTransport = $transportFactory->create(
            $baseUrl . '/otlp/v1/metrics',
            'application/json',
            $headers,
        );
        $metricExporter = new MetricExporter($metricTransport);
        $this->meterProvider = MeterProvider::builder()
            ->addReader(new ExportingReader($metricExporter))
            ->setResource($resource)
            ->build();
    }

    public function getTracerProvider(): TracerProvider
    {
        return $this->tracerProvider;
    }

    public function getLoggerProvider(): LoggerProvider
    {
        return $this->loggerProvider;
    }

    public function getMeterProvider(): MeterProvider
    {
        return $this->meterProvider;
    }

    public function shutdown(): void
    {
        $this->tracerProvider->shutdown();
        $this->loggerProvider->shutdown();
        $this->meterProvider->shutdown();
    }
}
