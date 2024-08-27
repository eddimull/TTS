<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bookings', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('band_id')->constrained();
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('venue_name');
            $table->text('venue_address');
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->enum('contract_option', ['default', 'none', 'external'])->default('default');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bookings');
    }
};
