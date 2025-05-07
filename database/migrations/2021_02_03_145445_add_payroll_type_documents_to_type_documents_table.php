<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\TypeDocument;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
//        TypeDocument::updateOrCreate(
//            ['id' => 9],
//            ['name' => 'Nomina Individual',
//             'code' => '1',
//             'cufe_algorithm' => 'CUNE-SHA384',
//             'prefix' => 'ni']
//        );
//
//        TypeDocument::updateOrCreate(
//            ['id' => 10],
//            ['name' => 'Nomina Individual de Ajuste',
//             'code' => '2',
//             'cufe_algorithm' => 'CUNE-SHA384',
//             'prefix' => 'na']
//        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('type_documents', function (Blueprint $table) {
            //
        });
    }
};
