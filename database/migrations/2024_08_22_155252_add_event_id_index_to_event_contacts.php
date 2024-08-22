<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEventIdIndexToEventContacts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('event_contacts', function (Blueprint $table) {
            $table->index('event_id', 'event_contacts_event_id_desc_index')->algorithm('btree')->descending();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_contacts', function (Blueprint $table) {
            $table->dropIndex('event_contacts_event_id_desc_index');
        });
    }
}
