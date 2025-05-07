<?php

use App\Models\Municipality;
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
        Municipality::where('name', 'Bucarasica')->update(['codefacturador' => 48362]);
        Municipality::where('name', 'Buenaventura')->update(['codefacturador' => 48320]);
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
	}
};
