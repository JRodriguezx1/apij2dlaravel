<?php

use App\Models\TypeDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
        TypeDocument::create(['name' => 'Eventos (ApplicationResponse)', 'code' => '96']);
        TypeDocument::where('code', '96')->update(['id' => 14, 'name' => 'Eventos (ApplicationResponse)', 'code' => '96']);
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
        TypeDocument::destroy(14);
	}
};
