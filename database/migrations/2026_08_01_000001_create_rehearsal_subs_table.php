<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rehearsal_subs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rehearsal_id')->constrained()->onDelete('cascade');
            $table->foreignId('band_id')->constrained()->onDelete('cascade');
            $table->foreignId('band_role_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');

            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // One live row per registered user per rehearsal. MySQL allows
            // multiple NULL user_ids, so ad-hoc invitees don't collide here;
            // they're deduped by (rehearsal_id, email) in the service layer.
            $table->unique(['rehearsal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rehearsal_subs');
    }
};
