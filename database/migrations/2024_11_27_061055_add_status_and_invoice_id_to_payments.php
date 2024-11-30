<?php

use App\Models\Invoices;
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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('status')->default('paid');
            $table->foreignIdFor(Invoices::class)->nullable();
            // make the existing date column nullable
            $table->dateTime('date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropForeignIdFor(Invoices::class);
            // make the existing date column not nullable
            $table->dateTime('date')->change();
        });
    }
};
