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
        Schema::table('document_payrolls', function (Blueprint $table) {
            $table->index('identification_number');
            $table->index('employee_id');
            $table->index('prefix');
            $table->index('consecutive');
            $table->index('cune');
            $table->index('type_document_id');
            $table->index('date_issue');
            $table->index('state_document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_payrolls', function (Blueprint $table) {
            $table->dropIndex('identification_number');
            $table->dropIndex('employee_id');
            $table->dropIndex('prefix');
            $table->dropIndex('consecutive');
            $table->dropIndex('cune');
            $table->dropIndex('type_document_id');
            $table->dropIndex('date_issue');
            $table->dropIndex('state_document_id');
        });
    }
};
