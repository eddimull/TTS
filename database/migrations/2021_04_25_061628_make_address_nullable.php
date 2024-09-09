<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeAddressNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('Address1')->nullable()->change();
            $table->string('Address2')->nullable()->change();
            $table->string('Address3')->nullable()->change();
            $table->char('Zip',5)->nullable()->change();
            $table->string('City')->nullable()->change();
            $table->string('StateID')->nullable()->change();
            $table->string('CountryID')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
}
