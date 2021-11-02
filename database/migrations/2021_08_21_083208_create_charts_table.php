<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChartsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('charts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->string('title')->default('Untitled')->nullable();
            $table->string('composer')->default('No Composer');
            $table->string('arranger')->default('');
            $table->text('description')->nullable();
            $table->boolean('public')->default(false);
            $table->bigInteger('price')->default(50);
            $table->foreignId('band_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('charts');
    }
}
