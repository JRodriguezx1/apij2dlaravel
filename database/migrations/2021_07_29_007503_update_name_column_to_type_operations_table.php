<?php

use App\Models\TypeOperation;
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
        TypeOperation::where('id', 21)->update(['name' => 'Transporte']);
        TypeOperation::where('id', 4)->delete();
        TypeOperation::where('id', 7)->delete();
        TypeOperation::where('id', 13)->delete();
        TypeOperation::where('id', 14)->delete();
        TypeOperation::where('id', 15)->delete();
        TypeOperation::where('id', 16)->delete();
        TypeOperation::where('id', 17)->delete();
        TypeOperation::where('id', 18)->delete();
        TypeOperation::where('id', 19)->delete();
        TypeOperation::where('id', 20)->delete();
        DB::table('type_operations')->updateOrInsert(['id' => '22', 'name' => 'Cambiario', 'code' => '13', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
    }

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
	}
};
