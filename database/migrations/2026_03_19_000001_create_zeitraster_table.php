<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeitraster', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100)->unique();
            $table->text('beschreibung')->nullable();
            $table->boolean('ist_standard')->default(false);
            $table->timestamps();
        });

        // Standard-Eintrag anlegen
        DB::table('zeitraster')->insert([
            'name'         => 'Standard',
            'beschreibung' => 'Automatisch angelegtes Standard-Zeitraster',
            'ist_standard' => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Permission registrieren
        \Spatie\Permission\Models\Permission::firstOrCreate([
            'name'       => 'manage zeitraster',
            'guard_name' => 'web',
        ]);
    }

    public function down(): void
    {
        // Permission löschen
        \Spatie\Permission\Models\Permission::where('name', 'manage zeitraster')
            ->where('guard_name', 'web')
            ->delete();

        // Permission-Cache leeren
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Schema::dropIfExists('zeitraster');
    }
};

