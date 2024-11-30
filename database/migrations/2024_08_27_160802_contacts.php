<?php

use App\Models\Bands;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('contacts', function (Blueprint $table)
        {
            $table->id();
            $table->foreignIdFor(Bands::class, 'band_id')->constrained();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->timestamps();

            $table->unique(['band_id', 'email']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('contacts');
    }
};
