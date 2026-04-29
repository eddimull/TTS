<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained('bands')->onDelete('cascade');
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['band_id', 'slug']);
            $table->index(['band_id', 'archived_at']);
        });

        Schema::create('questionnaire_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained('questionnaires')->onDelete('cascade');
            $table->string('type', 40);
            $table->string('label', 255);
            $table->text('help_text')->nullable();
            $table->boolean('required')->default(false);
            $table->integer('position');
            $table->json('settings')->nullable();
            $table->json('visibility_rule')->nullable();
            $table->string('mapping_target', 60)->nullable();
            $table->timestamps();

            $table->index(['questionnaire_id', 'position']);
        });

        Schema::create('questionnaire_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->nullable()->constrained('questionnaires')->nullOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('recipient_contact_id')->constrained('contacts');
            $table->foreignId('sent_by_user_id')->constrained('users');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('sent');
            $table->timestamp('sent_at');
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['booking_id', 'status']);
            $table->index('recipient_contact_id');
        });

        Schema::create('questionnaire_instance_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('questionnaire_instances')->onDelete('cascade');
            $table->unsignedBigInteger('source_field_id')->nullable(); // reference only; no FK
            $table->string('type', 40);
            $table->string('label', 255);
            $table->text('help_text')->nullable();
            $table->boolean('required')->default(false);
            $table->integer('position');
            $table->json('settings')->nullable();
            $table->json('visibility_rule')->nullable();
            $table->string('mapping_target', 60)->nullable();
            $table->timestamps();

            $table->index(['instance_id', 'position']);
        });

        Schema::create('questionnaire_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('questionnaire_instances')->onDelete('cascade');
            $table->foreignId('instance_field_id')->constrained('questionnaire_instance_fields')->onDelete('cascade');
            $table->text('value')->nullable();
            $table->timestamp('applied_to_event_at')->nullable();
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['instance_id', 'instance_field_id']);
            $table->index('instance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_responses');
        Schema::dropIfExists('questionnaire_instance_fields');
        Schema::dropIfExists('questionnaire_instances');
        Schema::dropIfExists('questionnaire_fields');
        Schema::dropIfExists('questionnaires');
    }
};
