<?php

use App\Models\Municipality;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Municipality::where('id', 780)->update(['codefacturador' => 48390]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Municipality::where('id', 780)->update(['codefacturador' => 12879]);
    }
};
