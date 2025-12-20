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
        // Media files table - central media storage
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null')->comment('Uploader');

            // File metadata
            $table->string('filename')->comment('Original filename');
            $table->string('stored_filename', 500)->comment('Path in S3 with UUID');
            $table->string('mime_type', 100);
            $table->bigInteger('file_size')->unsigned()->comment('Bytes');
            $table->string('disk', 50)->default('s3');

            // Organizational metadata
            $table->string('title')->nullable()->comment('User-friendly title');
            $table->text('description')->nullable();
            $table->enum('media_type', ['image', 'video', 'audio', 'document', 'other']);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['band_id', 'media_type']);
            $table->index(['band_id', 'created_at']);
            $table->fullText(['filename', 'title', 'description']);
        });

        // Media tags table - tagging system
        Schema::create('media_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained()->onDelete('cascade');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('color', 7)->nullable()->comment('Hex color for UI');

            $table->timestamps();

            // Unique constraint on band_id + slug
            $table->unique(['band_id', 'slug']);
            $table->index(['band_id', 'name']);
        });

        // Media file tags pivot table
        Schema::create('media_file_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_file_id')->constrained()->onDelete('cascade');
            $table->foreignId('media_tag_id')->constrained()->onDelete('cascade');

            $table->timestamp('created_at')->nullable();

            $table->unique(['media_file_id', 'media_tag_id']);
            $table->index('media_tag_id');
        });

        // Media associations table - polymorphic links to Events/Bookings
        Schema::create('media_associations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_file_id')->constrained()->onDelete('cascade');
            $table->string('associable_type')->comment('App\\Models\\Events or App\\Models\\Bookings');
            $table->unsignedBigInteger('associable_id');

            $table->timestamp('created_at')->nullable();

            $table->index(['associable_type', 'associable_id']);
            $table->index('media_file_id');
        });

        // Media shares table - public download links
        Schema::create('media_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_file_id')->constrained()->onDelete('cascade');
            $table->string('token', 64)->unique()->comment('Random token for URL');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Access control
            $table->timestamp('expires_at')->nullable()->comment('NULL = permanent');
            $table->unsignedInteger('download_limit')->nullable()->comment('NULL = unlimited');
            $table->unsignedInteger('download_count')->default(0);
            $table->string('password_hash')->nullable()->comment('Optional password protection');

            // Metadata
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['token', 'is_active']);
        });

        // Band storage quotas table - per-band storage limits
        Schema::create('band_storage_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained()->onDelete('cascade');

            // Quota limits (in bytes)
            $table->bigInteger('quota_limit')->unsigned()->default(5368709120)->comment('Default 5GB');
            $table->bigInteger('quota_used')->unsigned()->default(0);

            // Metadata
            $table->timestamp('last_calculated_at')->nullable();

            $table->timestamps();

            $table->unique('band_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_file_tags');
        Schema::dropIfExists('media_associations');
        Schema::dropIfExists('media_shares');
        Schema::dropIfExists('band_storage_quotas');
        Schema::dropIfExists('media_tags');
        Schema::dropIfExists('media_files');
    }
};
