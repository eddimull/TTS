<?php

use App\Models\BandCalendars;
use App\Models\Bands;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Bands::all()->each(function ($band) {
            if($band->calendar_id === null) {
                return;
            }
            BandCalendars::create([
                'band_id' => $band->id,
                'calendar_id' => $band->calendar_id,
                'type' => 'event',
            ]);
        });
        Schema::table('bands', function (Blueprint $table) {
            $table->dropColumn('calendar_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bands', function (Blueprint $table) {
            $table->string('calendar_id')->nullable();
        });
        BandCalendars::all()->each(function ($calendar) {
           $band = $calendar->band;
           $band->calendar_id = $calendar->calendar_id;
           $band->save();
        });
    }
};
