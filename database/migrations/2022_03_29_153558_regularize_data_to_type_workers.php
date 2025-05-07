<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Helpers\RegularizeDataHelper;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        
        $table_columns = [
            'columns' => 'id, name, code, @created_at, @updated_at',
        ];

        RegularizeDataHelper::regularizeDataFromTable('type_workers', $table_columns);
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
