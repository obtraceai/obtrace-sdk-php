<?php

declare(strict_types=1);

namespace Obtrace\Sdk;

use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

final class ObtraceClient
{
    private OtelSetup $otel;
    private mixed $previousErrorHandler = null;
    private mixed $previousExceptionHandler = null;

    private const ERROR_LEVEL_MAP = [
        E_NOTICE => 'info',
        E_USER_NOTICE => 'info',
        E_WARNING => 'warn',
        E_USER_WARNING => 'warn',
        E_ERROR => 'error',
        E_USER_ERROR => 'error',
    ];

    private const SEVERITY_MAP = [
        'trace' => 1,
        'debug' => 5,
        'info' => 9,
        'warn' => 13,
        'error' => 17,
        'fatal' => 21,
    ];

    public function __construct(private readonly ObtraceConfig $cfg)
    {
        if ($cfg->apiKey === '' || $cfg->ingestBaseUrl === '' || $cfg->serviceName === '') {
            throw new \InvalidArgumentException('apiKey, ingestBaseUrl and serviceName are required');
        }
        $this->otel = new OtelSetup($cfg);
        register_shutdown_function([$this, 'shutdown']);
        $this->previousErrorHandler = set_error_handler([$this, 'handleError']);
        $this->previousExceptionHandler = set_exception_handler([$this, 'handleException']);
    }

    public function handleError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        $level = self::ERROR_LEVEL_MAP[$errno] ?? 'error';
        $this->log($level, $errstr, ['file' => $errfile, 'line' => $errline, 'errno' => $errno]);

        if ($this->previousErrorHandler !== null) {
            return ($this->previousErrorHandler)($errno, $errstr, $errfile, $errline);
        }
        return false;
    }

    public function handleException(\Throwable $exception): void
    {
        $this->log('fatal', $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'exception' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
        ]);

        if ($this->previousExceptionHandler !== null) {
            ($this->previousExceptionHandler)($exception);
        }
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $logger = $this->otel->getLoggerProvider()->getLogger('obtrace-sdk-php', '1.0.0');
        $record = (new LogRecord($message))
            ->setSeverityText(strtoupper($level))
            ->setSeverityNumber(self::SEVERITY_MAP[$level] ?? 9)
            ->setAttributes(array_merge(['obtrace.log.level' => $level], $context));
        $logger->emit($record);
    }

    public function metric(string $name, float $value, string $unit = '1', array $context = []): void
    {
        if ($this->cfg->validateSemanticMetrics && $this->cfg->debug && !SemanticMetrics::isSemanticMetric($name)) {
            fwrite(STDERR, sprintf("[obtrace-sdk-php] non-canonical metric name: %s\n", $name));
        }
        $meter = $this->otel->getMeterProvider()->getMeter('obtrace-sdk-php', '1.0.0');
        $gauge = $meter->createObservableGauge($name, $unit);
        $gauge->observe(static function ($observer) use ($value, $context) {
            $observer->observe($value, $context);
        });
        $this->otel->getMeterProvider()->collect();
    }

    public function span(
        string $name,
        ?int $statusCode = null,
        string $statusMessage = '',
        array $attrs = [],
    ): array {
        $tracer = $this->otel->getTracerProvider()->getTracer('obtrace-sdk-php', '1.0.0');
        $spanBuilder = $tracer->spanBuilder($name)->setSpanKind(SpanKind::KIND_CLIENT);

        if (!empty($attrs)) {
            $spanBuilder->setAttributes($attrs);
        }

        $span = $spanBuilder->startSpan();

        if ($statusCode !== null && $statusCode >= 400) {
            $span->setStatus(StatusCode::STATUS_ERROR, $statusMessage);
        } else {
            $span->setStatus(StatusCode::STATUS_OK, $statusMessage);
        }

        $span->end();

        $spanContext = $span->getContext();
        return [
            'trace_id' => $spanContext->getTraceId(),
            'span_id' => $spanContext->getSpanId(),
        ];
    }

    public function shutdown(): void
    {
        $this->otel->shutdown();
    }

    public function getTracerProvider(): \OpenTelemetry\SDK\Trace\TracerProvider
    {
        return $this->otel->getTracerProvider();
    }
}
