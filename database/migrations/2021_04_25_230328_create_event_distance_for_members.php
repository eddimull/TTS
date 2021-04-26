<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventDistanceForMembers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('event_distance_for_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->default(0);
            $table->unsignedBigInteger('user_id')->default(0);
            $table->unsignedDecimal('miles',8,2)->default(0);
            $table->unsignedDecimal('minutes',8,2)->default(0);
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
        Schema::dropIfExists('event_distance_for_members');
    }
}
