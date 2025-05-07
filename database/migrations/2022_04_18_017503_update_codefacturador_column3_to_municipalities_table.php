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
        Municipality::where('name', 'Yotoco')->update(['codefacturador' => 48354]);
        Municipality::where('name', 'Yumbo')->update(['codefacturador' => 48355]);
        Municipality::where('name', 'Yopal')->update(['codefacturador' => 12918]);
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
	}
};
