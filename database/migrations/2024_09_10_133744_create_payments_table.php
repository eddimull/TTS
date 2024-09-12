<?php

use App\Models\Bands;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('payments', 'proposal_payments');
        Schema::create('payments', function (Blueprint $table)
        {
            $table->id();
            $table->morphs('payable');
            $table->string('name');
            $table->integer('amount');
            $table->dateTime('date');
            $table->foreignIdFor(Bands::class, 'band_id')->constrained();
            $table->foreignIdFor(User::class)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::rename('proposal_payments', 'payments');
    }
};
