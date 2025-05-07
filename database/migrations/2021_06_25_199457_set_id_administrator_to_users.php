<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $u = User::where('id', '>', 0)->get();
        foreach($u as $user){
            $user->id_administrator = 1;
            $user->save();
        }
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('id_administrator')->references('id')->on('administrators');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
