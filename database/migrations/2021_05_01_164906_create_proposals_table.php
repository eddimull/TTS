<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProposalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('band_id');
            $table->uuid('key');
            $table->unsignedBigInteger('author_id');
            $table->unsignedBigInteger('edited_id');
            $table->unsignedBigInteger('proposal_phase_id');
            $table->timestamps();

            $table->foreign('author_id')->references('id')->on('users');
            $table->foreign('proposal_phase_id')->references('id')->on('proposal_phases');
            $table->foreign('edited_id')->references('id')->on('users');
            $table->foreign('band_id')->references('id')->on('bands');
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proposals');
    }
}
