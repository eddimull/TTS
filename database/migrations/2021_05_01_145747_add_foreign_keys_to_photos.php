<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToPhotos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('colorway_photos', function (Blueprint $table) {
            //
            $table->foreign('colorway_id')->references('id')->on('colorways');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('colorway_photos', function (Blueprint $table) {
            //
            $table->dropForeign(['colorway_id']);
        });
    }
}
