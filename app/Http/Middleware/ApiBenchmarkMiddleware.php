<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiBenchmarkMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('benchmark.enabled')) {
            return $next($request);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $start = hrtime(true);
        $response = null;
        $exception = null;

        try {
            $response = $next($request);

            return $response;
        } catch (Throwable $e) {
            $exception = $e;
            throw $e;
        } finally {
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            DB::flushQueryLog();

            $durationMs = round((hrtime(true) - $start) / 1_000_000, 2);
            $queryCount = count($queries);
            $dbTimeMs = round(array_sum(array_column($queries, 'time')), 2);
            $maxQuery = $this->slowestQuery($queries);
            $dbSharePercent = $durationMs > 0 ? round(($dbTimeMs / $durationMs) * 100, 2) : 0.0;

            $route = $request->route();
            $routeUri = $route?->uri() ?? $request->path();
            $action = $route?->getActionName();
            $status = $response?->getStatusCode() ?? 500;
            $responseContent = $response && method_exists($response, 'getContent')
                ? $response->getContent()
                : null;
            $responseKb = is_string($responseContent)
                ? round(strlen($responseContent) / 1024, 2)
                : null;

            $payload = [
                'type' => 'api_benchmark',
                'timestamp' => now()->toIso8601String(),
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'route' => $routeUri,
                'action' => $action,
                'status' => $status,
                'duration_ms' => $durationMs,
                'query_count' => $queryCount,
                'db_time_ms' => $dbTimeMs,
                'db_share_percent' => $dbSharePercent,
                'max_query_ms' => $maxQuery['time'],
                'max_query_sql' => $maxQuery['query'],
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'response_kb' => $responseKb,
                'authenticated' => $request->user() !== null,
                'user_id' => $request->user()?->id,
                'exception' => $exception ? $exception::class : null,
                'slow_request' => $durationMs >= config('benchmark.slow_request_threshold_ms'),
                'slow_query' => $maxQuery['time'] >= config('benchmark.slow_query_threshold_ms'),
                'bottleneck_hint' => $this->resolveBottleneckHint(
                    $durationMs,
                    $dbTimeMs,
                    $queryCount,
                    $maxQuery['time'],
                    $responseKb
                ),
            ];

            Log::channel(config('benchmark.log_channel'))
                ->info(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $queries
     * @return array{time: float, query: string|null}
     */
    private function slowestQuery(array $queries): array
    {
        if ($queries === []) {
            return [
                'time' => 0.0,
                'query' => null,
            ];
        }

        $slowest = collect($queries)->sortByDesc('time')->first();

        return [
            'time' => round((float) ($slowest['time'] ?? 0), 2),
            'query' => $slowest['query'] ?? null,
        ];
    }

    private function resolveBottleneckHint(
        float $durationMs,
        float $dbTimeMs,
        int $queryCount,
        float $maxQueryMs,
        ?float $responseKb
    ): string {
        if ($queryCount >= 15) {
            return 'High query count: possible N+1 or over-fetching.';
        }

        if ($durationMs > 0 && ($dbTimeMs / $durationMs) >= 0.6) {
            return 'Database-bound request: most of the request time is spent in SQL.';
        }

        if ($maxQueryMs >= config('benchmark.slow_query_threshold_ms')) {
            return 'A slow SQL query is likely the bottleneck.';
        }

        if ($responseKb !== null && $responseKb >= 100 && $dbTimeMs < ($durationMs * 0.3)) {
            return 'Large response payload: serialization or response size is likely the bottleneck.';
        }

        if ($durationMs >= config('benchmark.slow_request_threshold_ms')) {
            return 'Request is slow outside SQL: inspect app logic, serialization, cache, or external I/O.';
        }

        return 'No obvious bottleneck from this sample.';
    }
}
