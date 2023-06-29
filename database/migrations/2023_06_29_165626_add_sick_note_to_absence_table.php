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
        Schema::table('absences', function (Blueprint $table) {
            $table->boolean('sick_note_required')->default(0);
            $table->timestamp('sick_note_date')->nullable();
        });

        \Spatie\Permission\Models\Permission::insert([
            'name' => 'manage sick_notes',
            'guard_name'    => "web"
        ]);

        Artisan::call('cache:clear');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->removeColumn('sick_note_required');
            $table->removeColumn('sick_note_date');
        });

        \Spatie\Permission\Models\Permission::where([
            'name' => 'manage sick_notes',
            'guard_name'    => "web"
        ])->delete();

        Artisan::call('cache:clear');
    }
};
