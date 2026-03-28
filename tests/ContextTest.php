<?php

declare(strict_types=1);

namespace Obtrace\Sdk\Tests;

use Obtrace\Sdk\Context;
use PHPUnit\Framework\TestCase;

final class ContextTest extends TestCase
{
    public function testEnsurePropagationHeadersWithProvidedIds(): void
    {
        $headers = Context::ensurePropagationHeaders(
            [],
            "0123456789abcdef0123456789abcdef",
            "0123456789abcdef",
            "sess-1"
        );
        $this->assertSame(
            "00-0123456789abcdef0123456789abcdef-0123456789abcdef-01",
            $headers["traceparent"]
        );
        $this->assertSame("sess-1", $headers["x-obtrace-session-id"]);
    }

    public function testEnsurePropagationHeadersPreservesExisting(): void
    {
        $existing = Context::ensurePropagationHeaders(
            ["traceparent" => "00-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-bbbbbbbbbbbbbbbb-01"],
            null,
            null,
            "sess-2"
        );
        $this->assertSame(
            "00-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-bbbbbbbbbbbbbbbb-01",
            $existing["traceparent"]
        );
    }

    public function testRandomHexLength(): void
    {
        $this->assertSame(32, strlen(Context::randomHex(16)));
        $this->assertSame(16, strlen(Context::randomHex(8)));
    }
}
