<?php

namespace App\Console\Commands;

use App\Http\Controllers\FinancesController;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Time the web /finances/paidUnpaid handler in-process. Same idea as
 * dev:bench-trends but for the Inertia paidUnpaid page — its cost is
 * dominated by Bookings::$appends firing per-booking events queries during
 * toArray/serialize, so we care about total query count as much as wall time.
 */
class BenchPaidUnpaid extends Command
{
    protected $signature = 'dev:bench-paid-unpaid
                            {--email=admin@example.com : user to authenticate as}
                            {--runs=3}
                            {--queries : print top 10 slowest SQL for the first run}
                            {--serialize : also time Inertia toResponse to include append-driven queries}';

    protected $description = 'Time the web paidUnpaid handler in-process';

    public function handle(FinancesController $controller): int
    {
        $user = User::where('email', $this->option('email'))->first();
        if (!$user) {
            $this->error("User {$this->option('email')} not found.");
            return self::FAILURE;
        }
        Auth::login($user);

        $runs = max(1, (int) $this->option('runs'));
        $times = [];

        for ($i = 1; $i <= $runs; $i++) {
            $req = Request::create('/finances/paidUnpaid', 'GET');

            $logQueries = $this->option('queries') && $i === 1;
            $totalCount = 0;
            $totalMs = 0.0;
            DB::flushQueryLog();
            if ($logQueries) {
                DB::enableQueryLog();
            }
            DB::listen(function ($q) use (&$totalCount, &$totalMs) {
                $totalCount++;
                $totalMs += (float) $q->time;
            });

            $t = microtime(true);
            $response = $controller->paidUnpaid($req);

            $extra = '';
            if ($this->option('serialize')) {
                // Inertia response's toResponse() runs the props → JSON, which
                // is where the appends fire. Measure it too.
                $resp = $response->toResponse($req);
                $extra = sprintf('  (%s bytes json)', number_format(strlen($resp->getContent())));
            }

            $ms = (microtime(true) - $t) * 1000;
            $times[] = $ms;

            $this->line(sprintf(
                '  run %d: %8.2f ms  (%d queries, %.2f ms SQL)%s',
                $i, $ms, $totalCount, $totalMs, $extra
            ));

            if ($logQueries) {
                $log = DB::getQueryLog();
                DB::disableQueryLog();
                DB::flushQueryLog();
                usort($log, fn ($a, $b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));
                $this->line('    top 10 SQL:');
                foreach (array_slice($log, 0, 10) as $q) {
                    $sql = preg_replace('/\s+/', ' ', trim($q['query']));
                    if (strlen($sql) > 220) $sql = substr($sql, 0, 217) . '...';
                    $this->line(sprintf('      %7.2f ms  %s', $q['time'] ?? 0, $sql));
                }
            }
        }

        if ($runs > 1) {
            sort($times);
            $this->newLine();
            $this->info(sprintf(
                'min=%.2fms median=%.2fms avg=%.2fms max=%.2fms',
                min($times),
                $times[intdiv(count($times), 2)],
                array_sum($times) / count($times),
                max($times)
            ));
        }

        return self::SUCCESS;
    }
}
