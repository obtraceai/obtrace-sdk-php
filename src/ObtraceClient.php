<?php

declare(strict_types=1);

namespace Obtrace\Sdk;

require_once __DIR__ . "/Types.php";
require_once __DIR__ . "/Context.php";
require_once __DIR__ . "/Otlp.php";

final class ObtraceClient
{
    private array $queue = [];

    public function __construct(private readonly ObtraceConfig $cfg)
    {
        if ($cfg->apiKey === "" || $cfg->ingestBaseUrl === "" || $cfg->serviceName === "") {
            throw new \InvalidArgumentException("apiKey, ingestBaseUrl and serviceName are required");
        }
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $this->enqueue("/otlp/v1/logs", Otlp::logsPayload($this->cfg, $level, $message, $context));
    }

    public function metric(string $name, float $value, string $unit = "1", array $context = []): void
    {
        $this->enqueue("/otlp/v1/metrics", Otlp::metricPayload($this->cfg, $name, $value, $unit, $context));
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
        $this->enqueue("/otlp/v1/traces", Otlp::spanPayload($this->cfg, $name, $trace, $span, $start, $end, $statusCode, $statusMessage, $attrs));
        return ["trace_id" => $trace, "span_id" => $span];
    }

    public function injectPropagation(array $headers = [], ?string $traceId = null, ?string $spanId = null, ?string $sessionId = null): array
    {
        return Context::ensurePropagationHeaders($headers, $traceId, $spanId, $sessionId);
    }

    public function flush(): void
    {
        $batch = $this->queue;
        $this->queue = [];
        foreach ($batch as $item) {
            $this->send($item["endpoint"], $item["payload"]);
        }
    }

    public function shutdown(): void
    {
        $this->flush();
    }

    private function enqueue(string $endpoint, array $payload): void
    {
        if (count($this->queue) >= $this->cfg->maxQueueSize) {
            array_shift($this->queue);
        }
        $this->queue[] = ["endpoint" => $endpoint, "payload" => $payload];
    }

    private function send(string $endpoint, array $payload): void
    {
        if (!function_exists("curl_init")) {
            return;
        }

        $url = rtrim($this->cfg->ingestBaseUrl, "/") . $endpoint;
        $ch = curl_init($url);
        if ($ch === false) {
            return;
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

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => (int) floor($this->cfg->requestTimeoutSec * 1000),
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($this->cfg->debug && $code >= 300) {
            fwrite(STDERR, sprintf("[obtrace-sdk-php] status=%d endpoint=%s body=%s\n", $code, $endpoint, (string) $body));
        }
        if ($this->cfg->debug && $err !== "") {
            fwrite(STDERR, sprintf("[obtrace-sdk-php] send failed endpoint=%s err=%s\n", $endpoint, $err));
        }
    }
}
