<?php

declare(strict_types=1);

namespace Obtrace\Sdk\Laravel;

use Closure;
use Illuminate\Http\Request;
use Obtrace\Sdk\Context;
use Obtrace\Sdk\ObtraceClient;
use Obtrace\Sdk\Otlp;
use Symfony\Component\HttpFoundation\Response;

class ObtraceMiddleware
{
    public function __construct(private readonly ObtraceClient $client)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $traceId = Context::randomHex(16);
        $spanId = Context::randomHex(8);
        $startNano = Otlp::nowUnixNano();

        $response = $next($request);

        $endNano = Otlp::nowUnixNano();
        $statusCode = $response->getStatusCode();
        $method = $request->getMethod();
        $path = $request->getPathInfo();
        $host = $request->getHost();
        $route = $request->route()?->uri() ?? $path;

        $this->client->span(
            name: "{$method} {$route}",
            traceId: $traceId,
            spanId: $spanId,
            startUnixNano: $startNano,
            endUnixNano: $endNano,
            statusCode: $statusCode,
            attrs: [
                'http.method' => $method,
                'http.route' => $route,
                'http.target' => $path,
                'http.host' => $host,
                'http.status_code' => $statusCode,
                'http.user_agent' => $request->userAgent() ?? '',
                'http.request_content_length' => $request->header('Content-Length', '0'),
                'http.response_content_length' => $response->headers->get('Content-Length', '0'),
            ],
        );

        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }
}
