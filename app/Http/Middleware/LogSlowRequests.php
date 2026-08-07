<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogSlowRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('performance.slow_requests.enabled', true) || ! $this->shouldWatch($request)) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $queryCount = 0;
        $queryTimeMs = 0.0;

        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryTimeMs): void {
            $queryCount++;
            $queryTimeMs += $query->time;
        });

        $response = $next($request);

        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;

        if ($this->isSlow($durationMs, $queryCount, $queryTimeMs)) {
            Log::info('slow_request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => optional($request->route())->getName(),
                'user_id' => optional($request->user())->id,
                'status' => $response->getStatusCode(),
                'duration_ms' => round($durationMs, 2),
                'query_count' => $queryCount,
                'query_time_ms' => round($queryTimeMs, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            ]);
        }

        return $response;
    }

    private function shouldWatch(Request $request): bool
    {
        foreach (config('performance.slow_requests.paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isSlow(float $durationMs, int $queryCount, float $queryTimeMs): bool
    {
        return $durationMs >= config('performance.slow_requests.min_duration_ms', 1500)
            || $queryCount >= config('performance.slow_requests.min_query_count', 80)
            || $queryTimeMs >= config('performance.slow_requests.min_query_time_ms', 800);
    }
}
