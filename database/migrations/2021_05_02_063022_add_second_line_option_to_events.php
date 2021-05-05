<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSecondLineOptionToEvents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('band_events', function (Blueprint $table) {
            $table->boolean('second_line')->default(false);
            $table->boolean('onsite')->default(false);
            $table->dateTime('quiet_time')->nullable();
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
            $table->dropColumn('second_line');
            $table->dropColumn('onsite');
            $table->dropColumn('quiet_time');
        });
    }
}
