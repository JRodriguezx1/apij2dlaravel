<?php

use App\Models\Tax;
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
        Tax::where('code', 'ZZ')->update(['name' => 'No aplica', 'description' => 'No aplica']);
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
	}
};
