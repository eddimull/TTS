<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterNamesOfDancesAndAddProductionBackline extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('band_events', function (Blueprint $table) {
            $table->string('mother_groom')->default('')->nullable();
            $table->renameColumn('second_dance','father_daughter');
            $table->renameColumn('bouquet_dance','bouquet_garter');
            $table->boolean('production_needed')->default(true)->nullable();
            $table->boolean('backline_provided')->default(false)->nullable();
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
            $table->renameColumn('father_daughter','second_dance');
            $table->renameColumn('bouquet_garter','bouquet_dance');
            $table->dropColumn('production_needed');
            $table->dropColumn('backline_provided');
            $table->dropColumn('mother_groom');
        });
    }
}
