<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('questionnaire_components');
        Schema::dropIfExists('questionnairres');
    }

    public function down(): void
    {
        // Intentionally empty: the old tables are dead and not coming back.
        // If a rollback is ever needed for this migration, recreate them by
        // running an older migration snapshot from history.
    }
};
