<?php

use App\Models\ReferencePrice;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
        ReferencePrice::where('id', '1')->update(['code' => '1']);
        ReferencePrice::where('id', '2')->update(['code' => '2']);
        ReferencePrice::where('id', '3')->update(['code' => '3']);
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
        ReferencePrice::where('id', '1')->update(['code' => '01']);
        ReferencePrice::where('id', '2')->update(['code' => '02']);
        ReferencePrice::where('id', '3')->update(['code' => '03']);
	}
};
