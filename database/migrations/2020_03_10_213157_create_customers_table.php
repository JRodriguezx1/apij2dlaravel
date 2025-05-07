<?php

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
        Schema::create('customers', function (Blueprint $table) {
            $table->string('identification_number', 15)->primary();
            $table->string('dv', 1)->nullable();
            $table->string('name', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 120)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('password', 10)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
