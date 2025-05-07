<?php

use App\Models\TypeDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
        $sdcn = TypeDocument::updateOrCreate(['id' => 13, 'name' => 'Nota de Ajuste al Documento Soporte Electrónico', 'code' => 95, 'cufe_algorithm' => 'CUDS-SHA384', 'prefix' => 'nds']);
        $sdcn->id = 13;
        $sdcn->save();
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
        $sdcn = TypeDocument::where('id', 13)->get();
        if(count($sdcn) > 0)
            $sdcn[0]->delete();
	}
};
