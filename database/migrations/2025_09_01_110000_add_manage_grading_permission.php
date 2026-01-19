<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            try {
                if (!\DB::table('permissions')->where('name','manage grading systems')->exists()) {
                    \DB::table('permissions')->insert([
                        'name' => 'manage grading systems',
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            try {
                \DB::table('permissions')->where('name','manage grading systems')->delete();
            } catch (\Throwable $e) {}
        }
    }
};

