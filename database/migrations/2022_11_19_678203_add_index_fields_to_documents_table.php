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
            $table->index('identification_number');
            $table->index('customer');
            $table->index('prefix');
            $table->index('number');
            $table->index('cufe');
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
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('identification_number');
            $table->dropIndex('customer');
            $table->dropIndex('prefix');
            $table->dropIndex('number');
            $table->dropIndex('cufe');
            $table->dropIndex('type_document_id');
            $table->dropIndex('date_issue');
            $table->dropIndex('state_document_id');
        });
    }
};
