<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lodgings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->dateTime('check_in_at');
            $table->dateTime('check_out_at');
            $table->text('notes')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['band_id', 'check_in_at']);
        });

        Schema::create('lodging_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodging_id')->constrained()->onDelete('cascade');
            $table->string('label');
            $table->string('confirmation_number')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lodging_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodging_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('stored_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('disk');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lodging_attachments');
        Schema::dropIfExists('lodging_rooms');
        Schema::dropIfExists('lodgings');
    }
};
