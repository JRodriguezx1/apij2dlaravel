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
        Schema::table('users', function (Blueprint $table) {
            $table->string('mail_host')->nullable()->default('');
            $table->string('mail_port')->nullable()->default('');
            $table->string('mail_username')->nullable()->default('');
            $table->string('mail_password')->nullable()->default('');
            $table->string('mail_encryption')->nullable()->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mail_host');
            $table->dropColumn('mail_port');
            $table->dropColumn('mail_username');
            $table->dropColumn('mail_password');
            $table->dropColumn('mail_encryption');
        });
    }
};
