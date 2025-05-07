<?php

use App\Models\TypeRejection;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
        TypeRejection::where('id', '1')->update(['name' => 'Documento con inconsistencias', 'code' => '1']);
        TypeRejection::where('id', '2')->update(['name' => 'Mercancía no entregada', 'code' => '2']);
        TypeRejection::where('id', '3')->update(['name' => 'Mercancía  entregada parcialmente', 'code' => '3']);
        TypeRejection::where('id', '4')->update(['name' => 'Servicio no prestado', 'code' => '4']);
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
        TypeRejection::where('id', '1')->update(['name' => 'Documento con inconsistencias', 'code' => '01']);
        TypeRejection::where('id', '2')->update(['name' => 'Mercancía no entregada totalmente', 'code' => '02']);
        TypeRejection::where('id', '3')->update(['name' => 'Mercancía  entregada parcialmente', 'code' => '03']);
        TypeRejection::where('id', '4')->update(['name' => 'Servicio no prestado', 'code' => '04']);
	}
};
