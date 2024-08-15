<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        \Illuminate\Support\Facades\DB::table('permissions')->insert([
            'name' => 'view vertretungen',
            'guard_name'=>'web'
        ]);

        \Illuminate\Support\Facades\Artisan::call('cache:clear');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('view_vertretungen', function (Blueprint $table) {
            //
        });
    }
};
