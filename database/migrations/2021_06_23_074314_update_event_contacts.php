<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateEventContacts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('event_contacts', function (Blueprint $table) {
            $table->dropColumn('relation');
            $table->string('phonenumber')->nullable();
            $table->string('email')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_contacts', function (Blueprint $table) {
            $table->dropColumn('phonenumber');
            $table->dropColumn('email');
            $table->string('relation');
        });
    }
}
