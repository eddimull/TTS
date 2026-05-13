<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('deposit_type', 16)->default('percent')->after('price');
            $table->decimal('deposit_value', 10, 2)->default(50.00)->after('deposit_type');
        });

        DB::table('bookings')->update([
            'deposit_type'  => 'percent',
            'deposit_value' => 50.00,
        ]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['deposit_type', 'deposit_value']);
        });
    }
};
