<?php

declare(strict_types=1);

namespace Obtrace\Sdk;

final class Framework
{
    /**
     * Generic wrapper that can be used in middleware-style stacks.
     */
    public static function middleware(ObtraceClient $client, callable $next): callable
    {
        return static function (...$args) use ($client, $next) {
            $client->log("info", "request.start");
            $res = $next(...$args);
            $client->log("info", "request.finish");
            return $res;
        };
    }
}
