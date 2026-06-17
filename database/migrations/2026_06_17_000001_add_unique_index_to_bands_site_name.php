<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce site_name uniqueness at the database level. Until now it was only
     * guarded by application validation and the uniqueSiteName() generator,
     * which leaves a time-of-check/time-of-use race for the programmatic
     * (go-solo / mobile createBand) paths.
     *
     * Any pre-existing duplicates are renamed to a unique value first so the
     * index can be added cleanly in every environment.
     */
    public function up(): void
    {
        $this->deduplicateSiteNames();

        Schema::table('bands', function (Blueprint $table) {
            $table->unique('site_name');
        });
    }

    public function down(): void
    {
        Schema::table('bands', function (Blueprint $table) {
            $table->dropUnique(['site_name']);
        });
    }

    /**
     * Rename colliding site_names so each is unique. The lowest-id row in each
     * collision group keeps its name; the rest get a "-N" suffix, checked
     * against all known names so we never create a fresh collision.
     */
    private function deduplicateSiteNames(): void
    {
        $taken = DB::table('bands')->pluck('site_name')->all();
        $taken = array_fill_keys($taken, true);

        $duplicateNames = DB::table('bands')
            ->select('site_name')
            ->groupBy('site_name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('site_name');

        foreach ($duplicateNames as $name) {
            // Keep the first (lowest id) row as-is; rename the rest.
            $ids = DB::table('bands')
                ->where('site_name', $name)
                ->orderBy('id')
                ->pluck('id')
                ->slice(1);

            foreach ($ids as $id) {
                $suffix = 1;
                do {
                    $candidate = "{$name}-{$suffix}";
                    $suffix++;
                } while (isset($taken[$candidate]));

                $taken[$candidate] = true;
                DB::table('bands')->where('id', $id)->update(['site_name' => $candidate]);
            }
        }
    }
};
