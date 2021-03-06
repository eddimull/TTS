<?php

use Database\Seeders\ProposalPhasesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProposalPhasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proposal_phases', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $seed = new ProposalPhasesSeeder();
        $seed->run();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proposal_phases');
    }
}
