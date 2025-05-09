<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        $table_name = 'payroll_type_document_identifications';
        
        $exist_records =  DB::table($table_name)->count();

        if($exist_records > 0)
        {

            DB::table($table_name)->delete();

            $prefix = 'csv';
            $key = $table_name;
            $table = [
                'columns' => 'id, name, code, @created_at, @updated_at',
            ];

            $rutafile = public_path($prefix.DIRECTORY_SEPARATOR."{$key}.{$prefix}");
            $rutafile = str_replace('\\', '/', $rutafile);

            DB::connection()
                ->getpdo()
                ->exec("LOAD DATA LOCAL INFILE '".$rutafile."' INTO TABLE $key({$table['columns']}) SET created_at = NOW(), updated_at = NOW()");

        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
