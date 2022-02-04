<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class QuestionnaireComponents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('questionnaire_components', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('questionnaire_id');
            $table->json('data',70);
            $table->integer('order')->default(-1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('questionnaire_components');
    }
}
