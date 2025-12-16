<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('venue_cache', function (Blueprint $table) {
            // Add address column for geocoding cache
            $table->string('address')->nullable()->after('place_id');

            // Make place_id nullable (for geocoding-only caches)
            $table->string('place_id')->nullable()->change();

            // Make name and formatted_address nullable
            $table->string('name')->nullable()->change();
            $table->text('formatted_address')->nullable()->change();

            // Add index for address lookups
            $table->index('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venue_cache', function (Blueprint $table) {
            $table->dropColumn('address');
            $table->dropIndex(['address']);
        });
    }
};
