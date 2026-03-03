<?php

declare(strict_types=1);

require __DIR__ . "/../src/Context.php";

use Obtrace\Sdk\Context;

function assert_true(bool $cond, string $message): void
{
    if (!$cond) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$headers = Context::ensurePropagationHeaders(
    [],
    "0123456789abcdef0123456789abcdef",
    "0123456789abcdef",
    "sess-1"
);
assert_true(
    $headers["traceparent"] === "00-0123456789abcdef0123456789abcdef-0123456789abcdef-01",
    "traceparent should use provided ids"
);
assert_true(
    $headers["x-obtrace-session-id"] === "sess-1",
    "session header should be set"
);

$existing = Context::ensurePropagationHeaders(
    ["traceparent" => "00-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-bbbbbbbbbbbbbbbb-01"],
    null,
    null,
    "sess-2"
);
assert_true(
    $existing["traceparent"] === "00-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-bbbbbbbbbbbbbbbb-01",
    "traceparent should be preserved"
);

echo "ok\n";
