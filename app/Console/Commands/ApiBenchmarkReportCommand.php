<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ApiBenchmarkReportCommand extends Command
{
    protected $signature = 'benchmark:api-report
                            {--path= : Custom path to the benchmark log file}
                            {--limit=10 : Number of rows to show in each section}';

    protected $description = 'Analyze API benchmark logs and highlight likely bottlenecks.';

    public function handle(): int
    {
        $path = $this->option('path') ?: $this->resolveDefaultLogPath();

        if (! $path || ! is_file($path)) {
            $this->error("Benchmark log file not found: {$path}");

            return self::FAILURE;
        }

        $entries = $this->readEntries($path);

        if ($entries === []) {
            $this->warn('No benchmark entries were found in the log file.');

            return self::SUCCESS;
        }

        $aggregates = $this->aggregateEntries($entries);
        $limit = max((int) $this->option('limit'), 1);

        $this->info('API Benchmark Summary');
        $this->newLine();
        $this->line('Samples: '.count($entries));
        $this->line('Endpoints: '.count($aggregates));
        $this->newLine();

        $this->renderSection(
            'Slowest Endpoints',
            $aggregates,
            fn (array $row) => $row['avg_duration_ms'],
            $limit
        );

        $this->renderSection(
            'Most Database-Bound Endpoints',
            $aggregates,
            fn (array $row) => $row['avg_db_share_percent'],
            $limit
        );

        $this->renderSection(
            'Highest Error Rate Endpoints',
            $aggregates,
            fn (array $row) => $row['error_rate_percent'],
            $limit
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readEntries(string $path): array
    {
        $entries = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $jsonStart = strpos($line, '{');

            if ($jsonStart === false) {
                continue;
            }

            $payload = json_decode(substr($line, $jsonStart), true);

            if (! is_array($payload) || ($payload['type'] ?? null) !== 'api_benchmark') {
                continue;
            }

            $entries[] = $payload;
        }

        return $entries;
    }

    private function resolveDefaultLogPath(): ?string
    {
        $candidates = glob(storage_path('logs/api-benchmark*.log')) ?: [];

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $candidates[0];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function aggregateEntries(array $entries): array
    {
        $groups = [];

        foreach ($entries as $entry) {
            $key = ($entry['method'] ?? 'GET').' '.($entry['route'] ?? $entry['path'] ?? 'unknown');

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'endpoint' => $key,
                    'samples' => 0,
                    'duration_total' => 0.0,
                    'duration_max' => 0.0,
                    'db_time_total' => 0.0,
                    'db_share_total' => 0.0,
                    'query_count_total' => 0,
                    'response_kb_total' => 0.0,
                    'response_kb_max' => 0.0,
                    'max_query_ms' => 0.0,
                    'error_count' => 0,
                    'last_hint' => null,
                ];
            }

            $groups[$key]['samples']++;
            $groups[$key]['duration_total'] += (float) ($entry['duration_ms'] ?? 0);
            $groups[$key]['duration_max'] = max($groups[$key]['duration_max'], (float) ($entry['duration_ms'] ?? 0));
            $groups[$key]['db_time_total'] += (float) ($entry['db_time_ms'] ?? 0);
            $groups[$key]['db_share_total'] += (float) ($entry['db_share_percent'] ?? 0);
            $groups[$key]['query_count_total'] += (int) ($entry['query_count'] ?? 0);
            $groups[$key]['response_kb_total'] += (float) ($entry['response_kb'] ?? 0);
            $groups[$key]['response_kb_max'] = max($groups[$key]['response_kb_max'], (float) ($entry['response_kb'] ?? 0));
            $groups[$key]['max_query_ms'] = max($groups[$key]['max_query_ms'], (float) ($entry['max_query_ms'] ?? 0));
            $groups[$key]['error_count'] += ((int) ($entry['status'] ?? 200) >= 400) ? 1 : 0;
            $groups[$key]['last_hint'] = $entry['bottleneck_hint'] ?? null;
        }

        return collect($groups)
            ->map(function (array $group) {
                $samples = max($group['samples'], 1);

                return [
                    'endpoint' => $group['endpoint'],
                    'samples' => $group['samples'],
                    'avg_duration_ms' => round($group['duration_total'] / $samples, 2),
                    'max_duration_ms' => round($group['duration_max'], 2),
                    'avg_db_time_ms' => round($group['db_time_total'] / $samples, 2),
                    'avg_db_share_percent' => round($group['db_share_total'] / $samples, 2),
                    'avg_query_count' => round($group['query_count_total'] / $samples, 2),
                    'avg_response_kb' => round($group['response_kb_total'] / $samples, 2),
                    'max_response_kb' => round($group['response_kb_max'], 2),
                    'max_query_ms' => round($group['max_query_ms'], 2),
                    'error_rate_percent' => round(($group['error_count'] / $samples) * 100, 2),
                    'bottleneck' => $this->classifyAggregateBottleneck($group, $samples),
                    'last_hint' => $group['last_hint'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function classifyAggregateBottleneck(array $group, int $samples): string
    {
        $avgDbShare = $group['db_share_total'] / $samples;
        $avgQueries = $group['query_count_total'] / $samples;
        $avgResponseKb = $group['response_kb_total'] / $samples;
        $errorRate = ($group['error_count'] / $samples) * 100;

        if ($errorRate >= 5) {
            return 'Functional errors or rate limiting are affecting this endpoint.';
        }

        if ($avgQueries >= 15) {
            return 'Likely N+1 or over-fetching from the database.';
        }

        if ($avgDbShare >= 60) {
            return 'Database is the dominant bottleneck.';
        }

        if ((float) $group['max_query_ms'] >= (float) config('benchmark.slow_query_threshold_ms')) {
            return 'One or more slow queries are likely limiting performance.';
        }

        if ($avgResponseKb >= 100 && $avgDbShare < 30) {
            return 'Large API payload or JSON serialization is likely the bottleneck.';
        }

        if (($group['duration_total'] / $samples) >= (float) config('benchmark.slow_request_threshold_ms')) {
            return 'Request time is mostly outside SQL; inspect app logic, cache, serialization, or external services.';
        }

        return 'No major bottleneck detected from current samples.';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): float  $sortBy
     */
    private function renderSection(string $title, array $rows, callable $sortBy, int $limit): void
    {
        $sorted = collect($rows)
            ->sortByDesc($sortBy)
            ->take($limit)
            ->map(function (array $row) {
                return [
                    'Endpoint' => $row['endpoint'],
                    'Samples' => $row['samples'],
                    'Avg ms' => $row['avg_duration_ms'],
                    'DB ms' => $row['avg_db_time_ms'],
                    'DB %' => $row['avg_db_share_percent'],
                    'Queries' => $row['avg_query_count'],
                    'Resp KB' => $row['avg_response_kb'],
                    'Errors %' => $row['error_rate_percent'],
                    'Hint' => $row['bottleneck'],
                ];
            })
            ->all();

        $this->info($title);
        $this->table(
            ['Endpoint', 'Samples', 'Avg ms', 'DB ms', 'DB %', 'Queries', 'Resp KB', 'Errors %', 'Hint'],
            $sorted
        );
    }
}
