<?php

use App\Models\TypeDocument;
use Illuminate\Support\Facades\Log;
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
        TypeDocument::where('id', 1)->update(['name' => 'Factura electrónica de Venta']);
        TypeDocument::where('id', 2)->update(['name' => 'Factura electrónica de venta - exportación']);
        TypeDocument::where('id', 3)->update(['name' => 'Instrumento electrónico de transmisión – tipo 03']);
        DB::table('type_documents')->updateOrInsert(['id' => '12', 'name' => 'Factura electrónica de Venta - tipo 04', 'code' => '04', 'cufe_algorithm' => 'CUFE-SHA384', 'prefix' => 'fv', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
	}
};
