<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Company;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('sd_type_environment_id')->after('payroll_type_environment_id');
        });
        Company::where('sd_type_environment_id', 0)->update(['sd_type_environment_id' => 2]);
        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('sd_type_environment_id')->references('id')->on('type_environments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('sd_type_environment_id');
        });
    }
};
