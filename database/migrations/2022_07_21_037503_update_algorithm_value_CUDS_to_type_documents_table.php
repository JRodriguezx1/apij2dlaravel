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
        TypeDocument::where('id', 11)->update(['cufe_algorithm' => 'CUDS-SHA384']);
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
        TypeDocument::where('id', 11)->update(['cufe_algorithm' => 'CUDE-SHA384']);
	}
};
