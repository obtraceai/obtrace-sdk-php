<?php

declare(strict_types=1);

namespace Obtrace\Sdk;

use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

final class ObtraceClient
{
    private static bool $initialized = false;
    private OtelSetup $otel;
    private mixed $previousErrorHandler = null;
    private mixed $previousExceptionHandler = null;
    private bool $initialized = false;

    public function isInitialized(): bool { return $this->initialized; }

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

    private const AUTO_INSTRUMENTATION_MAP = [
        'guzzlehttp/guzzle' => 'open-telemetry/contrib-auto-guzzle',
        'ext-pdo' => 'open-telemetry/contrib-auto-pdo',
        'laravel/framework' => 'open-telemetry/contrib-auto-laravel',
        'symfony/http-kernel' => 'open-telemetry/contrib-auto-symfony',
        'slim/slim' => 'open-telemetry/contrib-auto-slim',
    ];

    public function __construct(private readonly ObtraceConfig $cfg)
    {
        if (self::$initialized) {
            fwrite(STDERR, "[obtrace-sdk-php] already initialized, skipping duplicate init\n");
            return;
        }
        if ($cfg->apiKey === '' || $cfg->serviceName === '') {
            throw new \InvalidArgumentException('apiKey and serviceName are required');
        }
        self::$initialized = true;
        $this->otel = new OtelSetup($cfg);
        register_shutdown_function([$this, 'shutdown']);
        $this->previousErrorHandler = set_error_handler([$this, 'handleError']);
        $this->previousExceptionHandler = set_exception_handler([$this, 'handleException']);
        $this->handshake();
    }

    private function handshake(): void
    {
        $base = rtrim($this->cfg->ingestBaseUrl, '/');
        if ($base === '') return;
        try {
            $payload = json_encode([
                'sdk' => 'obtrace-sdk-php',
                'sdk_version' => '1.0.0',
                'service_name' => $this->cfg->serviceName,
                'service_version' => $this->cfg->serviceVersion,
                'runtime' => 'php',
                'runtime_version' => PHP_VERSION,
            ]);
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$this->cfg->apiKey}\r\n",
                    'content' => $payload,
                    'timeout' => 5,
                    'ignore_errors' => true,
                ],
            ]);
            $result = @file_get_contents("{$base}/v1/init", false, $ctx);
            if ($result !== false) {
                $status = 0;
                foreach ($http_response_header ?? [] as $header) {
                    if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $m)) {
                        $status = (int) $m[1];
                    }
                }
                if ($status === 200) {
                    $this->initialized = true;
                    if ($this->cfg->debug) fwrite(STDERR, "[obtrace-sdk-php] init handshake OK\n");
                } elseif ($this->cfg->debug) {
                    fwrite(STDERR, "[obtrace-sdk-php] init handshake failed: {$status}\n");
                }
            }
        } catch (\Throwable $e) {
            if ($this->cfg->debug) fwrite(STDERR, "[obtrace-sdk-php] init handshake error: {$e->getMessage()}\n");
        }
    }

    public function detectMissingInstrumentation(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;
        foreach (self::AUTO_INSTRUMENTATION_MAP as $library => $instrumentation) {
            if ($this->isPackageInstalled($library) && !$this->isPackageInstalled($instrumentation)) {
                fwrite(STDERR, sprintf(
                    "[obtrace] %s is installed but %s is not. Run: composer require %s\n",
                    $library,
                    $instrumentation,
                    $instrumentation,
                ));
            }
        }
    }

    private function isPackageInstalled(string $package): bool
    {
        if (str_starts_with($package, 'ext-')) {
            return extension_loaded(substr($package, 4));
        }
        $installed = __DIR__ . '/../../vendor/composer/installed.json';
        if (!file_exists($installed)) {
            try {
                $vendorDir = dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2);
                $installed = $vendorDir . '/composer/installed.json';
            } catch (\ReflectionException) {
                return false;
            }
        }
        if (!file_exists($installed)) {
            return false;
        }
        static $installedPackages = null;
        if ($installedPackages === null) {
            $data = json_decode(file_get_contents($installed), true);
            $packages = $data['packages'] ?? $data;
            $installedPackages = [];
            foreach ($packages as $pkg) {
                $installedPackages[$pkg['name'] ?? ''] = true;
            }
        }
        return isset($installedPackages[$package]);
    }

    public static function printSetupInstructions(): void
    {
        $extLoaded = extension_loaded('opentelemetry');
        $lines = [
            '',
            '=== Obtrace PHP SDK - Setup Instructions ===',
            '',
            '1. Install the ext-opentelemetry PHP extension for auto-instrumentation:',
            '',
            '   pecl install opentelemetry',
            '',
            '   Then add to your php.ini:',
            '',
            '   extension=opentelemetry',
            '',
            '   Current status: ' . ($extLoaded ? 'INSTALLED' : 'NOT INSTALLED'),
            '',
            '2. Install auto-instrumentation packages for your libraries:',
            '',
        ];
        foreach (self::AUTO_INSTRUMENTATION_MAP as $library => $instrumentation) {
            $lines[] = sprintf('   composer require %s  # auto-instrument %s', $instrumentation, $library);
        }
        $lines[] = '';
        $lines[] = '3. Set environment variables:';
        $lines[] = '';
        $lines[] = '   OTEL_PHP_AUTOLOAD_ENABLED=true';
        $lines[] = '   OTEL_SERVICE_NAME=your-service';
        $lines[] = '   OTEL_EXPORTER_OTLP_ENDPOINT=https://ingest.obtrace.ai';
        $lines[] = '   OTEL_EXPORTER_OTLP_HEADERS="Authorization=Bearer obt_live_..."';
        $lines[] = '';
        $lines[] = '   With ext-opentelemetry and these packages, HTTP requests, database';
        $lines[] = '   queries, and framework operations are traced automatically.';
        $lines[] = '';
        fwrite(STDOUT, implode("\n", $lines) . "\n");
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
