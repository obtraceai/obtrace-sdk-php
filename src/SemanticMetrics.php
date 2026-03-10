<?php

declare(strict_types=1);

namespace Obtrace\Sdk;

final class SemanticMetrics
{
    public const THROUGHPUT = "http_requests_total";
    public const ERROR_RATE = "http_5xx_total";
    public const LATENCY_P95 = "latency_p95";
    public const RUNTIME_CPU_UTILIZATION = "runtime.cpu.utilization";
    public const RUNTIME_MEMORY_USAGE = "runtime.memory.usage";
    public const RUNTIME_THREAD_COUNT = "runtime.thread.count";
    public const RUNTIME_GC_PAUSE = "runtime.gc.pause";
    public const RUNTIME_EVENTLOOP_LAG = "runtime.eventloop.lag";
    public const CLUSTER_CPU_UTILIZATION = "cluster.cpu.utilization";
    public const CLUSTER_MEMORY_USAGE = "cluster.memory.usage";
    public const CLUSTER_NODE_COUNT = "cluster.node.count";
    public const CLUSTER_POD_COUNT = "cluster.pod.count";
    public const DB_OPERATION_LATENCY = "db.operation.latency";
    public const DB_CLIENT_ERRORS = "db.client.errors";
    public const DB_CONNECTIONS_USAGE = "db.connections.usage";
    public const MESSAGING_CONSUMER_LAG = "messaging.consumer.lag";
    public const WEB_VITAL_LCP = "web.vital.lcp";
    public const WEB_VITAL_FCP = "web.vital.fcp";
    public const WEB_VITAL_INP = "web.vital.inp";
    public const WEB_VITAL_CLS = "web.vital.cls";
    public const WEB_VITAL_TTFB = "web.vital.ttfb";
    public const USER_ACTIONS = "obtrace.sim.web.react.actions";
}
