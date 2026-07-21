<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\Mobile\FinancesController;
use App\Models\Bands;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Time the mobile finances/trends handler in-process against a seeded band.
 * Bypasses HTTP/auth so you can iterate on indexes without a token or curl.
 *
 * Example:
 *   php artisan dev:bench-trends
 *   php artisan dev:bench-trends --year=2026 --queries
 *   php artisan dev:bench-trends --band=test_band --runs=5
 */
class BenchTrends extends Command
{
    protected $signature = 'dev:bench-trends
                            {--band=test_band : site_name of the band to profile against}
                            {--year= : year to bucket by (defaults to current)}
                            {--snapshot= : optional snapshot_date (Y-m-d) to filter created_at}
                            {--compare : also request current_months (only relevant with --snapshot)}
                            {--runs=3 : how many times to run the handler}
                            {--queries : log and print the executed SQL for the first run}';

    protected $description = 'Time the mobile trends endpoint in-process for perf/index tuning';

    public function handle(FinancesController $controller): int
    {
        $band = Bands::where('site_name', $this->option('band'))->first();
        if (!$band) {
            $this->error("Band with site_name={$this->option('band')} not found.");
            return self::FAILURE;
        }

        $runs = max(1, (int) $this->option('runs'));
        $query = [];
        if ($year = $this->option('year')) {
            $query['year'] = $year;
        }
        if ($snapshot = $this->option('snapshot')) {
            $query['snapshot_date'] = $snapshot;
        }
        if ($this->option('compare')) {
            $query['compare_with_current'] = 1;
        }

        $this->info(sprintf(
            'Benchmarking trends for %s (year=%s, snapshot=%s) — %d run(s)',
            $band->name,
            $query['year'] ?? '(current)',
            $query['snapshot_date'] ?? '-',
            $runs
        ));

        $times = [];
        for ($i = 1; $i <= $runs; $i++) {
            $req = Request::create('/api/mobile/bands/' . $band->id . '/finances/trends', 'GET', $query);
            $req->merge(['mobile_band' => $band]);

            $logQueries = $this->option('queries') && $i === 1;
            if ($logQueries) {
                DB::enableQueryLog();
            }

            $start = microtime(true);
            $response = $controller->trends($req);
            $ms = (microtime(true) - $start) * 1000;
            $times[] = $ms;

            $this->line(sprintf('  run %d: %8.2f ms  (HTTP %d, %s bytes)',
                $i,
                $ms,
                $response->getStatusCode(),
                number_format(strlen($response->getContent())),
            ));

            if ($logQueries) {
                $log = DB::getQueryLog();
                DB::disableQueryLog();
                DB::flushQueryLog();
                $this->line(sprintf('    executed %d queries (top 10 by time):', count($log)));
                usort($log, fn ($a, $b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));
                foreach (array_slice($log, 0, 10) as $q) {
                    $sql = preg_replace('/\s+/', ' ', trim($q['query']));
                    if (strlen($sql) > 220) {
                        $sql = substr($sql, 0, 217) . '...';
                    }
                    $this->line(sprintf('      %7.2f ms  %s', $q['time'] ?? 0, $sql));
                }
                $totalMs = array_sum(array_column($log, 'time'));
                $this->line(sprintf('    total SQL time (aggregated): %.2f ms', $totalMs));
            }
        }

        if ($runs > 1) {
            sort($times);
            $median = $times[intdiv(count($times), 2)];
            $min = min($times);
            $max = max($times);
            $avg = array_sum($times) / count($times);
            $this->newLine();
            $this->info(sprintf(
                'min=%.2fms  median=%.2fms  avg=%.2fms  max=%.2fms',
                $min, $median, $avg, $max
            ));
        }

        return self::SUCCESS;
    }
}
