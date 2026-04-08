<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pers_consent_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('legal_basis');
            $table->string('key')->unique(); // Maschinenlesbarer Schlüssel
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pers_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('consent_type_id')->constrained('pers_consent_types')->cascadeOnDelete();
            $table->datetime('granted_at');
            $table->datetime('revoked_at')->nullable();
            $table->string('granted_via')->default('self_service'); // 'self_service', 'papierform', 'onboarding'
            $table->timestamps();

            $table->unique(['employe_id', 'consent_type_id']); // Nur eine Einwilligung pro Typ pro MA
            $table->index('employe_id');
        });

        // Seed-Daten für Standard-Einwilligungstypen
        DB::table('pers_consent_types')->insert([
            [
                'name'        => 'Foto im Organigramm',
                'description' => 'Mein Profilfoto darf im internen Organigramm des Schulzentrums angezeigt werden.',
                'legal_basis' => 'Art. 6 Abs. 1 lit. a DSGVO',
                'key'         => 'foto_organigramm',
                'is_required' => false,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'name'        => 'Geburtstags-Anzeige',
                'description' => 'Mein Geburtstag darf der Personalleitung und Abteilungsleitung angezeigt werden.',
                'legal_basis' => 'Art. 6 Abs. 1 lit. a DSGVO',
                'key'         => 'geburtstag_anzeige',
                'is_required' => false,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'name'        => 'Mitarbeiterverzeichnis',
                'description' => 'Meine Kontaktdaten (Name, E-Mail, Telefon) dürfen im internen Mitarbeiterverzeichnis gelistet werden.',
                'legal_basis' => 'Art. 6 Abs. 1 lit. a DSGVO',
                'key'         => 'mitarbeiterverzeichnis',
                'is_required' => false,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);

        // Permission
        Permission::findOrCreate('manage personal_consents', 'web');
    }

    public function down(): void
    {
        Schema::dropIfExists('pers_consents');
        Schema::dropIfExists('pers_consent_types');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::findByName('manage personal_consents', 'web')?->delete();
    }
};

