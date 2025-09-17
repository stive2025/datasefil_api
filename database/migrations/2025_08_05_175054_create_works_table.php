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
        Schema::create('works', function (Blueprint $table) {
            $table->id();
            $table->string('type'); //own, job, society
            $table->string('address');
            $table->string('province');
            $table->string('ruc');
            $table->string('activities_start_date');
            $table->string('suspension_request_date');
            $table->string('legal_name');
            $table->string('activities_restart_date');
            $table->string('phone');
            $table->string('taxpayer_status');
            $table->string('email');
            $table->string('economic_activity');
            $table->string('business_name');
            $table->foreignId('client_id');
            $table->foreign('client_id')
                ->references('id')
                ->on('clients');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('works');
    }
};
