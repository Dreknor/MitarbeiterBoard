<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'edit klassen vertretungen', 'guard_name' => 'web']
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Spatie\Permission\Models\Permission::where('name', 'edit klassen vertretungen')->delete();
    }
};
