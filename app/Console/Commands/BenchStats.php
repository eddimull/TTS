<?php

namespace App\Console\Commands;

use App\Http\Controllers\UserStatsController;
use App\Models\User;
use App\Services\UserStatsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Time the /stats handler in-process. Same idea as dev:bench-trends /
 * dev:bench-paid-unpaid.
 *
 * With --stages, splits the service into its three sections (payments,
 * travel, locations) so you can see which one to attack first.
 */
class BenchStats extends Command
{
    protected $signature = 'dev:bench-stats
                            {--email=admin@example.com : user to authenticate as}
                            {--runs=3}
                            {--stages : run payments/travel/locations individually and time each}
                            {--queries : print top 10 slowest SQL for the first run}';

    protected $description = 'Time the /stats handler in-process';

    public function handle(UserStatsController $controller): int
    {
        $user = User::where('email', $this->option('email'))->first();
        if (!$user) {
            $this->error("User {$this->option('email')} not found.");
            return self::FAILURE;
        }
        Auth::login($user);

        if ($this->option('stages')) {
            return $this->runStages($user);
        }

        $runs = max(1, (int) $this->option('runs'));
        $times = [];
        for ($i = 1; $i <= $runs; $i++) {
            $count = 0;
            $ms = 0.0;
            DB::flushQueryLog();
            if ($this->option('queries') && $i === 1) {
                DB::enableQueryLog();
            }
            DB::listen(function ($q) use (&$count, &$ms) {
                $count++;
                $ms += (float) $q->time;
            });

            $t = microtime(true);
            $response = $controller->index();
            // Force Inertia to render the props so the appends actually fire.
            $rendered = $response->toResponse(request());
            $wall = (microtime(true) - $t) * 1000;
            $times[] = $wall;

            $this->line(sprintf(
                '  run %d: %8.2f ms  (%d queries, %.2f ms SQL, %s bytes)',
                $i, $wall, $count, $ms, number_format(strlen($rendered->getContent()))
            ));

            if ($this->option('queries') && $i === 1) {
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
                max($times),
            ));
        }

        return self::SUCCESS;
    }

    private function runStages(User $user): int
    {
        $svc = new UserStatsService($user);
        $ref = new \ReflectionClass($svc);

        $stages = ['getPaymentStats', 'getTravelStats', 'getEventLocations'];
        $this->line('Stage                          Wall      SQL   Queries');
        $this->line('----------------------------- --------- ------- -------');

        foreach ($stages as $method) {
            $m = $ref->getMethod($method);
            $m->setAccessible(true);

            $count = 0;
            $ms = 0.0;
            DB::listen(function ($q) use (&$count, &$ms) {
                $count++;
                $ms += (float) $q->time;
            });

            $t = microtime(true);
            $m->invoke($svc);
            $wall = (microtime(true) - $t) * 1000;
            $this->line(sprintf('%-30s %8.2fms %6.2fms %6d', $method, $wall, $ms, $count));
        }

        return self::SUCCESS;
    }
}
