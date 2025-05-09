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
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('type_plan_id')->after('newpassword');
            $table->foreign('type_plan_id')->references('id')->on('type_plans')->onDelete('cascade');
            $table->dateTime('start_plan_date')->after('type_plan_id')->nullable();
            $table->boolean('state')->after('start_plan_date')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['type_plan_id']);
            $table->dropColumn('type_plan_id');
            $table->dropColumn('start_plan_date');
            $table->dropColumn('state');
        });
    }
};
