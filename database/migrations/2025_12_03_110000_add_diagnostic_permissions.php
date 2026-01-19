<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Prüfen ob Permissions bereits existieren
        if (!DB::table('permissions')->where('name', 'view diagnostics')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'view diagnostics',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!DB::table('permissions')->where('name', 'manage diagnostics')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'manage diagnostics',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->where('name', 'view diagnostics')->delete();
        DB::table('permissions')->where('name', 'manage diagnostics')->delete();
    }
};

