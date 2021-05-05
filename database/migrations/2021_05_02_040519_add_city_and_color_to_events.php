<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCityAndColorToEvents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('band_events', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('colorway_id')->nullable();
            $table->string('city')->nullable();
            $table->boolean('outside')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('band_events', function (Blueprint $table) {
            //
            $table->dropColumn('colorway_id');
            $table->dropColumn('city');
            $table->dropColumn('outside');
        });
    }
}
