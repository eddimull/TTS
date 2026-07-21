<?php

namespace App\Console\Commands;

use App\Models\Bands;
use App\Services\FinanceServices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Breaks the trends handler into stages and reports wall time per stage so you
 * can see which chunk of the ~1.7s warm run is worth attacking next. Mirrors
 * FinancesController::bucketByMonth / FinanceServices::getPaidUnpaid so we can
 * time each sub-step without instrumenting production code.
 */
class ProfileTrends extends Command
{
    protected $signature = 'dev:profile-trends
                            {--band=test_band}
                            {--year= : year to bucket by (defaults to current)}';

    protected $description = 'Fine-grained wall-time breakdown of the trends handler';

    public function handle(FinanceServices $svc): int
    {
        $band = Bands::where('site_name', $this->option('band'))->first();
        if (!$band) {
            $this->error("Band {$this->option('band')} not found.");
            return self::FAILURE;
        }
        $year = (int) ($this->option('year') ?: date('Y'));

        // Attribute SQL time to the section that emitted it.
        $sqlMs = 0.0;
        $sqlCount = 0;
        DB::listen(function ($q) use (&$sqlMs, &$sqlCount) {
            $sqlMs += (float) $q->time;
            $sqlCount++;
        });

        $stages = [];
        $stage = function (string $label, callable $fn) use (&$stages, &$sqlMs, &$sqlCount) {
            $sqlBefore = $sqlMs;
            $countBefore = $sqlCount;
            $t = microtime(true);
            $result = $fn();
            $wall = (microtime(true) - $t) * 1000;
            $stages[] = [
                'label' => $label,
                'wall_ms' => $wall,
                'sql_ms' => $sqlMs - $sqlBefore,
                'sql_count' => $sqlCount - $countBefore,
            ];
            return $result;
        };

        // Warm caches/opcode/etc so the first stage doesn't eat cold-start cost.
        $band->loadMissing('activePayoutConfig');
        gc_collect_cycles();

        $totalStart = microtime(true);

        // ---- 1. getPaidUnpaid runs both scopes + addNetAmount map ----------
        $bands = $stage('FinanceServices::getPaidUnpaid (both scopes + addNetAmount)', function () use ($svc, $band) {
            return $svc->getPaidUnpaid([$band], null);
        });
        $b = $bands->first();

        // ---- 2. concat + foreach loop that actually builds the buckets -----
        $stage('concat + bucket-by-month PHP loop', function () use ($b, $year) {
            $bookings = collect($b->paidBookings)->concat(collect($b->unpaidBookings));
            $rows = [];
            for ($m = 1; $m <= 12; $m++) {
                $rows[$m] = ['paid' => 0.0, 'unpaid' => 0.0, 'forecast' => 0.0, 'net' => 0.0, 'count' => 0];
            }
            foreach ($bookings as $booking) {
                if (($booking->status ?? null) === 'cancelled') continue;
                if (empty($booking->start_date)) continue;
                $date = \Carbon\Carbon::parse($booking->start_date);
                if ((int) $date->year !== $year) continue;
                $m = (int) $date->month;
                $price = (float) $booking->price;
                $paid = (float) $booking->amount_paid;
                $net = (float) ($booking->net_amount ?? 0);
                $rows[$m]['forecast'] += $price;
                $rows[$m]['paid'] += $paid;
                $rows[$m]['unpaid'] += max(0, $price - $paid);
                $rows[$m]['net'] += $net;
                $rows[$m]['count'] += 1;
            }
            return $rows;
        });

        // ---- 3. availableYears (second getPaidUnpaid call in the handler) --
        $stage('availableYears (2nd getPaidUnpaid + year map)', function () use ($svc, $band) {
            $bands = $svc->getPaidUnpaid([$band], null);
            $b = $bands->first();
            $bookings = collect($b->paidBookings)->concat(collect($b->unpaidBookings));
            return $bookings
                ->filter(fn ($bk) => ($bk->status ?? null) !== 'cancelled' && !empty($bk->start_date))
                ->map(fn ($bk) => (int) \Carbon\Carbon::parse($bk->start_date)->year)
                ->unique()->sortDesc()->values()->all();
        });

        $totalMs = (microtime(true) - $totalStart) * 1000;

        // Break the first stage down further so we see hydration vs netAmount
        $this->info(sprintf(
            'Band %s | %d paid + %d unpaid bookings loaded',
            $band->name,
            $b->paidBookings->count(),
            $b->unpaidBookings->count(),
        ));
        $this->newLine();

        $this->line('Stage                                                          Wall     SQL   Queries');
        $this->line('----------------------------------------------------------- ---------- ------- -------');
        foreach ($stages as $s) {
            $this->line(sprintf(
                '%-60s %8.2fms %6.2fms %6d',
                substr($s['label'], 0, 60),
                $s['wall_ms'],
                $s['sql_ms'],
                $s['sql_count'],
            ));
        }
        $this->line('----------------------------------------------------------- ---------- ------- -------');
        $totalSql = array_sum(array_column($stages, 'sql_ms'));
        $totalCount = array_sum(array_column($stages, 'sql_count'));
        $this->line(sprintf(
            '%-60s %8.2fms %6.2fms %6d',
            'TOTAL',
            $totalMs,
            $totalSql,
            $totalCount,
        ));
        $this->newLine();

        // Zoom into stage 1: split scope get() (SQL + hydration) from the PHP
        // addNetAmount map loop, so you see how much is really the map.
        $this->info('Zoomed breakdown of stage 1 (getPaidUnpaid):');
        $band->activePayoutConfig; // ensure preloaded

        $tPaidSql = microtime(true);
        $paidRaw = $band->bookings()->paid();
        $paidSql = (microtime(true) - $tPaidSql) * 1000;

        $tUnpaidSql = microtime(true);
        $unpaidRaw = $band->bookings()->unpaid();
        $unpaidSql = (microtime(true) - $tUnpaidSql) * 1000;

        $tMap = microtime(true);
        $paidRaw->each(function ($booking) use ($svc, $band) {
            $this->invokeAddNet($svc, $booking, $band);
        });
        $unpaidRaw->each(function ($booking) use ($svc, $band) {
            $this->invokeAddNet($svc, $booking, $band);
        });
        $mapMs = (microtime(true) - $tMap) * 1000;

        $this->line(sprintf('  scopePaid   ->get() (SQL + hydrate): %8.2f ms  (%d rows)', $paidSql, $paidRaw->count()));
        $this->line(sprintf('  scopeUnpaid ->get() (SQL + hydrate): %8.2f ms  (%d rows)', $unpaidSql, $unpaidRaw->count()));
        $this->line(sprintf('  addNetAmount PHP map loop:           %8.2f ms', $mapMs));

        return self::SUCCESS;
    }

    private function invokeAddNet(FinanceServices $svc, \App\Models\Bookings $booking, Bands $band): void
    {
        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('addNetAmount');
        $m->setAccessible(true);
        $m->invoke($svc, $booking, $band);
    }
}
