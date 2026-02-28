<?php

declare(strict_types=1);

namespace Obtrace\Sdk;

final class Context
{
    public static function randomHex(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function ensurePropagationHeaders(array $headers = [], ?string $traceId = null, ?string $spanId = null, ?string $sessionId = null): array
    {
        if (!isset($headers["traceparent"])) {
            $headers["traceparent"] = sprintf("00-%s-%s-01", $traceId ?? self::randomHex(16), $spanId ?? self::randomHex(8));
        }
        if ($sessionId !== null && $sessionId !== "" && !isset($headers["x-obtrace-session-id"])) {
            $headers["x-obtrace-session-id"] = $sessionId;
        }
        return $headers;
    }
}
