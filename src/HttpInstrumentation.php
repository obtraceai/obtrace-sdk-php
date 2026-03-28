<?php

declare(strict_types=1);

namespace Obtrace\Sdk;

final class HttpInstrumentation
{
    private static ?ObtraceClient $client = null;

    public static function register(ObtraceClient $client): void
    {
        self::$client = $client;
    }

    public static function getClient(): ?ObtraceClient
    {
        return self::$client;
    }

    public static function curlExec(\CurlHandle $ch): string|bool
    {
        if (self::$client === null) {
            return curl_exec($ch);
        }

        $url = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $startNano = Otlp::nowUnixNano();
        $traceId = Context::randomHex(16);
        $spanId = Context::randomHex(8);

        $result = curl_exec($ch);

        $endNano = Otlp::nowUnixNano();
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $method = "UNKNOWN";

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? 'unknown';
        $path = $parsed['path'] ?? '/';

        $attrs = [
            'http.method' => $method,
            'http.url' => $url,
            'http.host' => $host,
            'http.target' => $path,
            'http.status_code' => $httpCode,
        ];

        if ($result === false) {
            $attrs['http.error'] = curl_error($ch);
        }

        self::$client->span(
            name: "HTTP {$method} {$host}{$path}",
            traceId: $traceId,
            spanId: $spanId,
            startUnixNano: $startNano,
            endUnixNano: $endNano,
            statusCode: $httpCode,
            attrs: $attrs,
        );

        return $result;
    }

    public static function guzzleMiddleware(): callable
    {
        return static function (callable $handler): callable {
            return static function ($request, array $options) use ($handler) {
                if (self::$client === null) {
                    return $handler($request, $options);
                }

                $method = $request->getMethod();
                $uri = $request->getUri();
                $host = $uri->getHost();
                $path = $uri->getPath() ?: '/';
                $traceId = Context::randomHex(16);
                $spanId = Context::randomHex(8);
                $startNano = Otlp::nowUnixNano();

                $promise = $handler($request, $options);

                return $promise->then(
                    static function ($response) use ($method, $host, $path, $uri, $traceId, $spanId, $startNano) {
                        $endNano = Otlp::nowUnixNano();
                        $statusCode = $response->getStatusCode();

                        self::$client->span(
                            name: "HTTP {$method} {$host}{$path}",
                            traceId: $traceId,
                            spanId: $spanId,
                            startUnixNano: $startNano,
                            endUnixNano: $endNano,
                            statusCode: $statusCode,
                            attrs: [
                                'http.method' => $method,
                                'http.url' => (string) $uri,
                                'http.host' => $host,
                                'http.target' => $path,
                                'http.status_code' => $statusCode,
                            ],
                        );

                        return $response;
                    },
                    static function ($reason) use ($method, $host, $path, $uri, $traceId, $spanId, $startNano) {
                        $endNano = Otlp::nowUnixNano();
                        $message = $reason instanceof \Throwable ? $reason->getMessage() : (string) $reason;

                        self::$client->span(
                            name: "HTTP {$method} {$host}{$path}",
                            traceId: $traceId,
                            spanId: $spanId,
                            startUnixNano: $startNano,
                            endUnixNano: $endNano,
                            statusCode: 500,
                            statusMessage: $message,
                            attrs: [
                                'http.method' => $method,
                                'http.url' => (string) $uri,
                                'http.host' => $host,
                                'http.target' => $path,
                                'http.error' => $message,
                            ],
                        );

                        throw $reason;
                    },
                );
            };
        };
    }
}
