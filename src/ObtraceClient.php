<?php

declare(strict_types=1);

namespace Obtrace\Sdk;

require_once __DIR__ . "/Types.php";
require_once __DIR__ . "/Context.php";
require_once __DIR__ . "/Otlp.php";
require_once __DIR__ . "/SemanticMetrics.php";

final class ObtraceClient
{
    private array $queue = [];
    private int $circuitFailures = 0;
    private float $circuitOpenUntil = 0;

    public function __construct(private readonly ObtraceConfig $cfg)
    {
        if ($cfg->apiKey === "" || $cfg->ingestBaseUrl === "" || $cfg->serviceName === "") {
            throw new \InvalidArgumentException("apiKey, ingestBaseUrl and serviceName are required");
        }
        register_shutdown_function([$this, 'shutdown']);
    }

    private function truncate(string $s, int $max): string
    {
        if (strlen($s) <= $max) {
            return $s;
        }
        return substr($s, 0, $max) . "...[truncated]";
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $this->enqueue("/otlp/v1/logs", Otlp::logsPayload($this->cfg, $level, $this->truncate($message, 32768), $context));
    }

    public function metric(string $name, float $value, string $unit = "1", array $context = []): void
    {
        if ($this->cfg->validateSemanticMetrics && $this->cfg->debug && !SemanticMetrics::isSemanticMetric($name)) {
            fwrite(STDERR, sprintf("[obtrace-sdk-php] non-canonical metric name: %s\n", $name));
        }
        $this->enqueue("/otlp/v1/metrics", Otlp::metricPayload($this->cfg, $this->truncate($name, 1024), $value, $unit, $context));
    }

    public function span(
        string $name,
        ?string $traceId = null,
        ?string $spanId = null,
        ?string $startUnixNano = null,
        ?string $endUnixNano = null,
        ?int $statusCode = null,
        string $statusMessage = "",
        array $attrs = [],
    ): array {
        $trace = ($traceId !== null && strlen($traceId) === 32) ? $traceId : Context::randomHex(16);
        $span = ($spanId !== null && strlen($spanId) === 16) ? $spanId : Context::randomHex(8);
        $start = $startUnixNano ?? Otlp::nowUnixNano();
        $end = $endUnixNano ?? Otlp::nowUnixNano();
        $name = $this->truncate($name, 32768);
        foreach ($attrs as $k => $v) {
            if (is_string($v)) {
                $attrs[$k] = $this->truncate($v, 4096);
            }
        }
        $this->enqueue("/otlp/v1/traces", Otlp::spanPayload($this->cfg, $name, $trace, $span, $start, $end, $statusCode, $statusMessage, $attrs));
        return ["trace_id" => $trace, "span_id" => $span];
    }

    public function injectPropagation(array $headers = [], ?string $traceId = null, ?string $spanId = null, ?string $sessionId = null): array
    {
        return Context::ensurePropagationHeaders($headers, $traceId, $spanId, $sessionId);
    }

    public function flush(): void
    {
        $now = microtime(true);
        if ($now < $this->circuitOpenUntil) {
            return;
        }
        $halfOpen = $this->circuitFailures >= 5;
        if ($halfOpen) {
            if (count($this->queue) === 0) {
                return;
            }
            $batch = [array_shift($this->queue)];
        } else {
            $batch = $this->queue;
            $this->queue = [];
        }
        foreach ($batch as $item) {
            $success = $this->send($item["endpoint"], $item["payload"]);
            if ($success) {
                if ($this->circuitFailures > 0) {
                    if ($this->cfg->debug) {
                        fwrite(STDERR, "[obtrace-sdk-php] circuit breaker closed\n");
                    }
                    $this->circuitFailures = 0;
                    $this->circuitOpenUntil = 0;
                }
            } else {
                $this->circuitFailures++;
                if ($this->circuitFailures >= 5) {
                    $this->circuitOpenUntil = microtime(true) + 30.0;
                    if ($this->cfg->debug) {
                        fwrite(STDERR, "[obtrace-sdk-php] circuit breaker opened\n");
                    }
                    break;
                }
            }
        }
    }

    public function shutdown(): void
    {
        $this->flush();
    }

    private function enqueue(string $endpoint, array $payload): void
    {
        if (count($this->queue) >= $this->cfg->maxQueueSize) {
            if ($this->cfg->debug) {
                fwrite(STDERR, sprintf("[obtrace-sdk-php] queue full (%d), dropping oldest item\n", $this->cfg->maxQueueSize));
            }
            array_shift($this->queue);
        }
        $this->queue[] = ["endpoint" => $endpoint, "payload" => $payload];
    }

    private function send(string $endpoint, array $payload): bool
    {
        if (!function_exists("curl_init")) {
            return false;
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            if ($this->cfg->debug) {
                fwrite(STDERR, sprintf("[obtrace-sdk-php] json_encode failed endpoint=%s err=%s\n", $endpoint, json_last_error_msg()));
            }
            return false;
        }

        $url = rtrim($this->cfg->ingestBaseUrl, "/") . $endpoint;
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        $headers = array_merge(
            [
                "Authorization: Bearer " . $this->cfg->apiKey,
                "Content-Type: application/json",
            ],
            array_map(
                static fn (string $k, mixed $v): string => $k . ": " . (string) $v,
                array_keys($this->cfg->defaultHeaders),
                array_values($this->cfg->defaultHeaders),
            ),
        );

        $timeoutMs = (int) floor($this->cfg->requestTimeoutSec * 1000);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => (int) floor($timeoutMs / 2),
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            if ($this->cfg->debug) {
                fwrite(STDERR, sprintf("[obtrace-sdk-php] curl_exec failed endpoint=%s err=%s, retrying\n", $endpoint, curl_error($ch)));
            }
            usleep(500000);
            $body = curl_exec($ch);
            if ($body === false) {
                if ($this->cfg->debug) {
                    fwrite(STDERR, sprintf("[obtrace-sdk-php] curl_exec retry failed endpoint=%s err=%s\n", $endpoint, curl_error($ch)));
                }
                curl_close($ch);
                return false;
            }
        }

        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 300) {
            if ($this->cfg->debug) {
                fwrite(STDERR, sprintf("[obtrace-sdk-php] status=%d endpoint=%s body=%s\n", $code, $endpoint, (string) $body));
            }
            return false;
        }
        return true;
    }
}
