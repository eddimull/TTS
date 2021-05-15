<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeTagsNullableOnColorways extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('colorways', function (Blueprint $table) {
            //
            $table->text('colorway_description')->nullable()->default('')->change();
            $table->text('color_tags')->nullable()->default('')->change();
            $table->text('color_title')->nullable()->default('')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
