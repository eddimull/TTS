<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // utf8mb4_bin (byte-exact): the app's default utf8mb4_unicode_ci
            // collates many distinct emoji as equal (e.g. 👍 == 🎉), which
            // would make the unique index below reject a second, different
            // emoji from the same user. The reaction key must compare emoji
            // by codepoint, not by linguistic weight.
            $table->string('emoji', 16)->collation('utf8mb4_bin');
            $table->timestamps();
            $table->unique(['message_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
