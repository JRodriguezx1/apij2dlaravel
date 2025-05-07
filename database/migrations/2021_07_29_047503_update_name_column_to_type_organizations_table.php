<?php

use App\Models\TypeOrganization;
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
        TypeOrganization::where('id', 1)->update(['name' => 'Persona Jurídica y asimiladas']);
        TypeOrganization::where('id', 2)->update(['name' => 'Persona Natural y asimiladas']);
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
	}
};
