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
        Schema::table('software', function (Blueprint $table) {
            $table->string('identifier_eqdocs')->after('pin_payroll');
            $table->string('pin_eqdocs')->after('identifier_eqdocs');
            $table->string('url_eqdocs')->after('url_payroll');
        });

        Schema::table('software', function (Blueprint $table) {
            $table->dropColumn('identifier_sd');
            $table->dropColumn('pin_sd');
            $table->dropColumn('url_sd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('software', function (Blueprint $table) {
            $table->dropColumn('identifier_eqdocs');
            $table->dropColumn('pin_eqdocs');
            $table->dropColumn('url_eqdocs');
        });

        Schema::table('software', function (Blueprint $table) {
            $table->string('identifier_sd')->after('pin_payroll');
            $table->string('pin_sd')->after('identifier_sd');
            $table->string('url_sd')->after('url_payroll');
        });
    }
};
