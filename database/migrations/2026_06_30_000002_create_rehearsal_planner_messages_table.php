<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rehearsal_planner_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('rehearsal_planner_sessions')
                ->onDelete('cascade');
            $table->string('role', 16);            // 'user' | 'assistant'
            $table->longText('content')->nullable();
            $table->json('payload')->nullable();   // suggestions / plan
            $table->string('status', 16)->default('complete'); // streaming|complete|failed
            $table->timestamps();
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rehearsal_planner_messages');
    }
};
