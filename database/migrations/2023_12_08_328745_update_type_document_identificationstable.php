<?php

use App\Models\TypeDocumentIdentification;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
        TypeDocumentIdentification::where('code', '47')->update(['id' => 11, 'name' => 'PEP (Permiso Especial de Permanencia)', 'code' => '47']);
        TypeDocumentIdentification::create(['name' => 'PPT (Permiso Protección Temporal)', 'code' => '48']);
        TypeDocumentIdentification::where('code', '48')->update(['id' => 12, 'name' => 'PPT (Permiso Protección Temporal)', 'code' => '48']);
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
        TypeDocumentIdentification::where('code', '47')->update(['id' => 11, 'name' => 'PEP', 'code' => '47']);
        TypeDocumentIdentification::destroy(12);
	}
};
