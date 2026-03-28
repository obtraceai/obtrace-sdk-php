<?php

declare(strict_types=1);

namespace Obtrace\Sdk\Laravel;

use Closure;
use Illuminate\Http\Request;
use Obtrace\Sdk\ObtraceClient;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\HttpFoundation\Response;

class ObtraceMiddleware
{
    public function __construct(private readonly ObtraceClient $client)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tracer = $this->client->getTracerProvider()->getTracer('obtrace-sdk-php', '1.0.0');
        $method = $request->getMethod();
        $path = $request->getPathInfo();
        $route = $request->route()?->uri() ?? $path;

        $span = $tracer->spanBuilder("{$method} {$route}")
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttributes([
                'http.method' => $method,
                'http.route' => $route,
                'http.target' => $path,
                'http.host' => $request->getHost(),
                'http.user_agent' => $request->userAgent() ?? '',
            ])
            ->startSpan();

        $scope = $span->activate();

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->recordException($e);
            $span->end();
            $scope->detach();
            throw $e;
        }

        $statusCode = $response->getStatusCode();
        $span->setAttribute('http.status_code', $statusCode);
        $span->setAttribute('http.request_content_length', $request->header('Content-Length', '0'));
        $span->setAttribute('http.response_content_length', $response->headers->get('Content-Length', '0'));

        if ($statusCode >= 400) {
            $span->setStatus(StatusCode::STATUS_ERROR);
        } else {
            $span->setStatus(StatusCode::STATUS_OK);
        }

        $span->end();
        $scope->detach();

        $response->headers->set('X-Trace-Id', $span->getContext()->getTraceId());

        return $response;
    }
}
