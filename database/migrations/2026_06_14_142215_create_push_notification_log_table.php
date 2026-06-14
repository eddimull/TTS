<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notification_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 32);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('event_id');
            $table->unique(['event_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_log');
    }
};
