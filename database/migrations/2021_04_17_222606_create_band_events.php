<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBandEvents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('band_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('band_id');
            $table->foreign('band_id')->references('id')->on('bands');
            $table->char('event_name',150);
            $table->char('venue_name',150);
            $table->char('first_dance',150);
            $table->char('second_dance',150);
            $table->char('money_dance',150);
            $table->char('bouquet_dance',150);
            $table->char('address_street',150);
            $table->char('zip',10);
            $table->longText('notes');
            $table->dateTime('event_time', 0);	
            $table->dateTime('band_loadin_time', 0);	
            $table->dateTime('finish_time', 0);	
            $table->dateTime('rhythm_loadin_time', 0);	
            $table->dateTime('production_loadin_time', 0);	
            $table->float('pay');
            $table->boolean('depositReceived');	
            $table->uuid('event_key');
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
        Schema::dropIfExists('band_events');
    }
}
