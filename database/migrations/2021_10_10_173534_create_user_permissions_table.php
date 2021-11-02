<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserPermissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id');
            $table->foreignId('user_id');
            $table->boolean('read_events')->default(false);
            $table->boolean('write_events')->default(false);
            $table->boolean('read_proposals')->default(false);
            $table->boolean('write_proposals')->default(false);
            $table->boolean('read_invoices')->default(false);
            $table->boolean('write_invoices')->default(false);
            $table->boolean('read_colors')->default(false);
            $table->boolean('write_colors')->default(false);
            $table->boolean('read_charts')->default(true);
            $table->boolean('write_charts')->default(true);
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
        Schema::dropIfExists('user_permissions');
    }
}
