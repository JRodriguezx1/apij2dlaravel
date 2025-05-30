<?php

use App\Models\CreditNoteDiscrepancyResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
        CreditNoteDiscrepancyResponse::where('id', '5')->update(['name' => 'Descuento comercial por pronto pago', 'code' => '5']);
        CreditNoteDiscrepancyResponse::create(['name' => 'Descuento comercial por volumen de ventas', 'code' => '6']);
        CreditNoteDiscrepancyResponse::where('code', '6')->update(['id' => 6, 'name' => 'Descuento comercial por volumen de ventas', 'code' => '6']);
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
        CreditNoteDiscrepancyResponse::where('id', '5')->update(['name' => 'Otros', 'code' => '5']);
        CreditNoteDiscrepancyResponse::destroy(6);
	}
};
