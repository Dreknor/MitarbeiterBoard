<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Nachrichten: ausführliche Gruppen-Nachrichten mit optionaler Lesebestätigung.
 *
 * - posts: Kurzfassung, „auch auf dem Dashboard“, Ablaufdatum, Lesebestätigung mit Frist,
 *   Veröffentlichungs-/Archivierungszeitpunkt, letzte manuelle Erinnerung.
 * - post_lesestatus: pro Person und Nachricht gelesen/bestätigt, persönliche Frist, Erinnerungen.
 * - Rechte: "request read confirmation", "manage posts".
 */
return new class extends Migration
{
    private const PERMISSIONS = ['request read confirmation', 'manage posts'];

    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('kurzfassung')->nullable()->after('header');
            $table->boolean('auf_dashboard')->default(true)->after('text');
            $table->date('sichtbar_bis')->nullable()->after('auf_dashboard');
            $table->boolean('bestaetigung_erforderlich')->default(false)->after('sichtbar_bis');
            $table->date('bestaetigung_frist')->nullable()->after('bestaetigung_erforderlich');
            $table->timestamp('bestaetigung_angefordert_at')->nullable()->after('bestaetigung_frist');
            $table->timestamp('manuell_erinnert_at')->nullable()->after('bestaetigung_angefordert_at');
            $table->timestamp('veroeffentlicht_at')->nullable()->after('released');
            $table->timestamp('archiviert_at')->nullable()->after('archived');
        });

        // Bestehende veröffentlichte Nachrichten: Veröffentlichung = Erstellzeitpunkt
        DB::table('posts')->where('released', 1)->update(['veroeffentlicht_at' => DB::raw('created_at')]);
        DB::table('posts')->where('archived', 1)->update(['archiviert_at' => DB::raw('updated_at')]);

        Schema::create('post_lesestatus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('gelesen_at')->nullable();
            $table->timestamp('bestaetigt_at')->nullable();
            // Ab wann die Person bestätigen muss (Veröffentlichung bzw. Gruppenbeitritt)
            $table->timestamp('zugewiesen_at')->nullable();
            $table->date('frist')->nullable();
            $table->timestamp('erinnert_vorab_at')->nullable();
            $table->timestamp('erinnert_frist_at')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
            $table->index(['user_id', 'bestaetigt_at']);
        });

        foreach (self::PERMISSIONS as $name) {
            $vorhanden = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists();
            if (! $vorhanden) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('post_lesestatus');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'kurzfassung', 'auf_dashboard', 'sichtbar_bis', 'bestaetigung_erforderlich', 'bestaetigung_frist',
                'bestaetigung_angefordert_at', 'manuell_erinnert_at', 'veroeffentlicht_at', 'archiviert_at',
            ]);
        });

        foreach (self::PERMISSIONS as $name) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            if ($id !== null) {
                DB::table('model_has_permissions')->where('permission_id', $id)->delete();
                DB::table('role_has_permissions')->where('permission_id', $id)->delete();
                DB::table('permissions')->where('id', $id)->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
