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
        Schema::create('employees', function (Blueprint $table) {
            $table->string('identification_number', 15)->primary();
            $table->string('first_name', 120)->nullable();
            $table->string('middle_name', 120)->nullable();
            $table->string('surname', 120)->nullable();
            $table->string('second_surname', 120)->nullable();
            $table->string('address', 120)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('password', 191)->nullable();
            $table->string('newpassword', 191)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
