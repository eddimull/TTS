<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeProposalContactPhonenumberFieldType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposal_contacts', function (Blueprint $table) {
            $table->string('phonenumber')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('proposal_contacts', function (Blueprint $table) {
            //
            $table->tinyInteger('phonenumber')->nullable()->change();
        });
    }
}