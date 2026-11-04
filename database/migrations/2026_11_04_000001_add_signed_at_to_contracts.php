<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->timestamp('signed_at')->nullable()->after('status');
        });

        // Nothing recorded the signing moment until now, so updated_at is the
        // best available value for contracts that are already completed.
        DB::table('contracts')
            ->where('status', 'completed')
            ->update(['signed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('signed_at');
        });
    }
};
