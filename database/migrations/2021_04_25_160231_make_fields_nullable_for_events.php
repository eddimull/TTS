<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Doctrine\DBAL\Types\StringType; use Doctrine\DBAL\Types\Type;

class MakeFieldsNullableForEvents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('band_events', function (Blueprint $table) {
           
            if (!Type::hasType('char')) {
                Type::addType('char', StringType::class);
            }
        
            //
            $table->char('first_dance',150)->nullable()->change();
            $table->char('second_dance',150)->nullable()->change();
            $table->char('money_dance',150)->nullable()->change();
            $table->char('bouquet_dance',150)->nullable()->change();
            $table->char('address_street',150)->nullable()->change();
            $table->char('address_street',150)->nullable()->change();
            $table->char('zip',5)->nullable()->change();
            $table->longText('notes')->nullable()->change();
            $table->dateTime('event_time')->nullable()->change();
            $table->dateTime('band_loadin_time')->nullable()->change();
            $table->float('pay')->nullable()->change();
            $table->boolean('depositReceived')->nullable()->change();
            $table->boolean('public')->nullable()->default(false);
            $table->unsignedBigInteger('event_type_id')->default(1);
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
