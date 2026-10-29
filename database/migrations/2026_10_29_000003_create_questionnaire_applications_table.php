<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per batch "Send to event". `before` snapshots what the batch
        // overwrote so it can be undone while the event is otherwise untouched.
        Schema::create('questionnaire_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('questionnaire_instances')->onDelete('cascade');
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before');
            $table->json('applied_response_ids');
            // events.updated_at right after the batch; undo refuses when it moved.
            $table->timestamp('event_updated_at')->nullable();
            // Hash of the routed event fields right after the batch, to catch
            // an edit within the same second as the apply.
            $table->string('event_fingerprint', 64)->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            $table->index('instance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_applications');
    }
};
