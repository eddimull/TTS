<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('booking_contacts', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('booking_id')->constrained();
            $table->foreignId('contact_id')->constrained();
            $table->string('role')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->json('additional_info')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'contact_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('booking_contacts');
    }
};
