<?php

use App\Models\TypeDocumentIdentification;
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
        DB::table('type_document_identifications')->updateOrInsert(['id' => '11', 'name' => 'PEP', 'code' => '47', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
	}
};
