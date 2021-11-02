<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChartUploads extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chart_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chart_id');
            $table->foreignId('upload_type_id');
            $table->timestamps();
            $table->softDeletes();
            $table->string('url');
            $table->string('fileType');
            $table->string('displayName')->default('Untitled')->nullable();
            $table->string('name')->default('Untitled')->nullable();
            $table->string('notes')->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chart_uploads');
    }
}
