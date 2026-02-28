<?php

declare(strict_types=1);

namespace Obtrace\Sdk;

final class Otlp
{
    public static function logsPayload(ObtraceConfig $cfg, string $level, string $message, array $context = []): array
    {
        return [
            "resourceLogs" => [[
                "resource" => ["attributes" => self::resource($cfg)],
                "scopeLogs" => [[
                    "scope" => ["name" => "obtrace-sdk-php", "version" => "1.0.0"],
                    "logRecords" => [[
                        "timeUnixNano" => self::nowUnixNano(),
                        "severityText" => strtoupper($level),
                        "body" => ["stringValue" => $message],
                        "attributes" => self::attrs(array_merge(["obtrace.log.level" => $level], $context)),
                    ]],
                ]],
            ]],
        ];
    }

    public static function metricPayload(ObtraceConfig $cfg, string $name, float $value, string $unit = "1", array $context = []): array
    {
        return [
            "resourceMetrics" => [[
                "resource" => ["attributes" => self::resource($cfg)],
                "scopeMetrics" => [[
                    "scope" => ["name" => "obtrace-sdk-php", "version" => "1.0.0"],
                    "metrics" => [[
                        "name" => $name,
                        "unit" => $unit,
                        "gauge" => [
                            "dataPoints" => [[
                                "timeUnixNano" => self::nowUnixNano(),
                                "asDouble" => $value,
                                "attributes" => self::attrs($context),
                            ]],
                        ],
                    ]],
                ]],
            ]],
        ];
    }

    public static function spanPayload(
        ObtraceConfig $cfg,
        string $name,
        string $traceId,
        string $spanId,
        string $startUnixNano,
        string $endUnixNano,
        ?int $statusCode = null,
        string $statusMessage = "",
        array $attrs = [],
    ): array {
        return [
            "resourceSpans" => [[
                "resource" => ["attributes" => self::resource($cfg)],
                "scopeSpans" => [[
                    "scope" => ["name" => "obtrace-sdk-php", "version" => "1.0.0"],
                    "spans" => [[
                        "traceId" => $traceId,
                        "spanId" => $spanId,
                        "name" => $name,
                        "kind" => 3,
                        "startTimeUnixNano" => $startUnixNano,
                        "endTimeUnixNano" => $endUnixNano,
                        "attributes" => self::attrs($attrs),
                        "status" => [
                            "code" => ($statusCode !== null && $statusCode >= 400) ? 2 : 1,
                            "message" => $statusMessage,
                        ],
                    ]],
                ]],
            ]],
        ];
    }

    public static function nowUnixNano(): string
    {
        return (string) ((int) floor(microtime(true) * 1000000000));
    }

    private static function resource(ObtraceConfig $cfg): array
    {
        $base = [
            "service.name" => $cfg->serviceName,
            "service.version" => $cfg->serviceVersion,
            "deployment.environment" => $cfg->env ?: "dev",
            "runtime.name" => "php",
        ];
        if ($cfg->tenantId !== null) {
            $base["obtrace.tenant_id"] = $cfg->tenantId;
        }
        if ($cfg->projectId !== null) {
            $base["obtrace.project_id"] = $cfg->projectId;
        }
        if ($cfg->appId !== null) {
            $base["obtrace.app_id"] = $cfg->appId;
        }
        if ($cfg->env !== null) {
            $base["obtrace.env"] = $cfg->env;
        }
        return self::attrs($base);
    }

    private static function attrs(array $attrs): array
    {
        $out = [];
        foreach ($attrs as $k => $v) {
            if (is_bool($v)) {
                $value = ["boolValue" => $v];
            } elseif (is_int($v) || is_float($v)) {
                $value = ["doubleValue" => (float) $v];
            } else {
                $value = ["stringValue" => (string) $v];
            }
            $out[] = ["key" => (string) $k, "value" => $value];
        }
        return $out;
    }
}
