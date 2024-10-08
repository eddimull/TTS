<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddingNullableToBandIdBecauseLaravelIsStupid extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('band_events', function (Blueprint $table) {
            $table->unsignedBigInteger('band_id')->default(0)->nullable()->change();
            $table->char('event_name',150)->default('No Name')->change();
            $table->char('venue_name',150)->default('No Venue Name')->change();
            $table->dateTime('finish_time')->nullable()->change();
            $table->dateTime('rhythm_loadin_time')->nullable()->change();
            $table->dateTime('production_loadin_time')->nullable()->change();
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
        });
    }
}
