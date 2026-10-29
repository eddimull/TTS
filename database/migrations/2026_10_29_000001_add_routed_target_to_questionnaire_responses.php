<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaire_responses', function (Blueprint $table) {
            // Per-answer override of the field's mapping_target, set when the
            // band sends one answer somewhere else ("Send to…").
            $table->string('routed_target', 60)->nullable()->after('value');
            // sha256 of the value when it was sent to the event; a mismatch
            // marks the answer stale even within the same second.
            $table->string('applied_value_hash', 64)->nullable()->after('applied_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('questionnaire_responses', function (Blueprint $table) {
            $table->dropColumn(['routed_target', 'applied_value_hash']);
        });
    }
};
