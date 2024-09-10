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
            $table->foreignId('band_id')->constrained('bands');
            $table->text('name');
            $table->foreignId('event_type_id')->constrained('event_types');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('venue_name')->default('TBD');
            $table->text('venue_address')->nullable();
            $table->decimal('price', 10, 2);
            $table->enum('status', ['draft', 'pending', 'confirmed', 'cancelled'])->default('draft');
            $table->enum('contract_option', ['default', 'none', 'external'])->default('default');
            $table->longText('notes')->nullable();
            $table->foreignId('author_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bookings');
    }
};
