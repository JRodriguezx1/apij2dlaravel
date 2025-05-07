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
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('acu_recibo')->default(false);
            $table->boolean('rec_bienes')->default(false);
            $table->boolean('aceptacion')->default(false);
            $table->boolean('rechazo')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('acu_recibo');
            $table->dropColumn('rec_bienes');
            $table->dropColumn('aceptacion');
            $table->dropColumn('rechazo');
        });
    }
};
