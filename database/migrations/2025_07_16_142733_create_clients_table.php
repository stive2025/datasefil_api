<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('identification');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('micro_activa')->nullable();
            $table->timestamp('birth')->nullable();
            $table->timestamp('death')->nullable();
            $table->string('gender')->nullable();
            $table->string('state_civil')->nullable();
            $table->string('economic_activity')->nullable();
            $table->string('economic_area')->nullable();
            $table->string('nationality')->nullable();
            $table->string('profession')->nullable();
            $table->string('place_birth')->nullable();
            $table->float('salary')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
