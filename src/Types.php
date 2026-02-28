<?php

declare(strict_types=1);

namespace Obtrace\Sdk;

final class ObtraceConfig
{
    public function __construct(
        public string $apiKey,
        public string $ingestBaseUrl,
        public string $serviceName,
        public ?string $tenantId = null,
        public ?string $projectId = null,
        public ?string $appId = null,
        public string $env = "dev",
        public string $serviceVersion = "1.0.0",
        public int $maxQueueSize = 1000,
        public float $requestTimeoutSec = 5.0,
        public array $defaultHeaders = [],
        public bool $debug = false,
    ) {
    }
}
