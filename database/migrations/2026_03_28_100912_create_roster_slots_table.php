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
        Schema::create('roster_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_id')->constrained('rosters')->onDelete('cascade');
            $table->foreignId('band_role_id')->nullable()->constrained('band_roles')->onDelete('set null');
            $table->string('name');
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('roster_id');
            $table->index('band_role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_slots');
    }
};
