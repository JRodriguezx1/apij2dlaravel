<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function __construct()
    {
        DB::getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('json', 'float');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('received_documents', function (Blueprint $table) {
            $table->float('sale', 15, 2)->change();
            $table->float('subtotal', 15, 2)->change();
            $table->float('total', 15, 2)->change();
            $table->float('total_discount', 15, 2)->change();
            $table->float('total_tax', 15, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('received_documents', function (Blueprint $table) {
            $table->float('sale', 10, 2)->change();
            $table->float('subtotal', 10, 2)->change();
            $table->float('total', 10, 2)->change();
            $table->float('total_discount', 10, 2)->change();
            $table->float('total_tax', 10, 2)->change();
        });
    }
};
