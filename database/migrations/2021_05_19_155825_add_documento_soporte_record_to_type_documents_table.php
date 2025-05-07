<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('type_documents'))
            DB::table('type_documents')->updateOrInsert(['id' => '11', 'name' => 'Documento Soporte Electrónico', 'code' => '05', 'cufe_algorithm' => 'CUDE-SHA384', 'prefix' => 'dse', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('type_documents'))
            DB::table('type_documents')->where('id', 11)->delete();
    }
};
