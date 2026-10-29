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
        });
    }

    public function down(): void
    {
        Schema::table('questionnaire_responses', function (Blueprint $table) {
            $table->dropColumn('routed_target');
        });
    }
};
