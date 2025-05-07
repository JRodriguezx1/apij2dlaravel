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
            $table->unsignedBigInteger('payroll_type_environment_id')->after('type_environment_id');
        });
        Company::where('payroll_type_environment_id', 0)->update(['payroll_type_environment_id' => 2]);
        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('payroll_type_environment_id')->references('id')->on('type_environments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('payroll_type_environment_id');
        });
    }
};
