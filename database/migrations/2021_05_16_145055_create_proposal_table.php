<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('proposals');
      

       

        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->references('id')->on('bands');
            $table->foreignId('phase_id')->references('id')->on('proposal_phases');
            $table->foreignId('author_id')->references('id')->on('users');
            $table->dateTime('date');
            $table->integer('hours');
            $table->decimal('price',9,3);
            $table->string('color')->nullable()->default('');
            $table->boolean('locked');
            $table->text('notes');
            $table->uuid('key');
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('proposal_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id');
            $table->string('email');
            $table->string('name');
            $table->integer('phonenumber');
            $table->timestamps();
        });

        Schema::create('sent_proposal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id');
            $table->unsignedBigInteger('proposal_contact_id')->references('id')->on('proposal_contacts');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sent_proposal');
        Schema::dropIfExists('proposal_contacts');
        Schema::dropIfExists('proposals');
    }
}
