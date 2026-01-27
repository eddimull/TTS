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
        Schema::create('chunked_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('upload_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->bigInteger('filesize');
            $table->string('mime_type');
            $table->integer('total_chunks');
            $table->integer('chunks_uploaded')->default(0);
            $table->enum('status', ['initiated', 'uploading', 'completed', 'failed'])->default('initiated');
            $table->foreignId('media_id')->nullable()->constrained('media_files')->onDelete('set null');
            $table->timestamp('last_chunk_at')->nullable();
            $table->timestamps();

            // Composite index for query optimization
            $table->index(['upload_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chunked_uploads');
    }
};
