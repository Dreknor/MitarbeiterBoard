<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Neues Wiki (ersetzt wiki_sites, die alte Tabelle bleibt vorerst als Sicherung stehen).
 *
 * - wiki_seiten: Seitenbaum (parent_id, sortierung), fester Slug, aktueller Inhalt (bereinigtes HTML + Klartext
 *   für die Suche), Schutz/Systemseite, optimistische Sperre über `version`, Papierkorb (Soft-Delete).
 * - wiki_revisionen: jede Speicherung (unbegrenzt), mit Titel, Inhalt, Notiz und Autor.
 * - wiki_tags + wiki_seite_tag, wiki_favoriten, wiki_weiterleitungen (alte Slugs/URLs),
 *   wiki_einschraenkungen (Rollen/Gruppen, vererbt sich auf Unterseiten), wiki_links (Backlinks/rote Links).
 */
return new class extends Migration
{
    private const PERMISSIONS = ['view wiki', 'edit wiki', 'manage wiki'];

    public function up(): void
    {
        Schema::create('wiki_seiten', function (Blueprint $table) {
            $table->id();
            $table->string('titel', 150);
            $table->string('slug', 190)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('wiki_seiten')->nullOnDelete();
            $table->unsignedInteger('sortierung')->nullable();
            $table->longText('inhalt')->nullable();
            $table->longText('inhalt_text')->nullable();
            $table->boolean('geschuetzt')->default(false);
            $table->boolean('systemseite')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('erstellt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('geaendert_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('bearbeitet_at')->nullable();
            $table->foreignId('geloescht_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'sortierung']);
            $table->index('bearbeitet_at');
        });

        Schema::create('wiki_revisionen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seite_id')->constrained('wiki_seiten')->cascadeOnDelete();
            $table->string('titel', 150);
            $table->longText('inhalt')->nullable();
            $table->string('notiz', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('wiederhergestellt_aus')->nullable()->constrained('wiki_revisionen')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['seite_id', 'created_at']);
        });

        Schema::create('wiki_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->timestamps();
        });

        Schema::create('wiki_seite_tag', function (Blueprint $table) {
            $table->foreignId('seite_id')->constrained('wiki_seiten')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('wiki_tags')->cascadeOnDelete();
            $table->primary(['seite_id', 'tag_id']);
        });

        Schema::create('wiki_favoriten', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seite_id')->constrained('wiki_seiten')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'seite_id']);
        });

        Schema::create('wiki_weiterleitungen', function (Blueprint $table) {
            $table->id();
            $table->string('alter_slug', 190)->unique();
            $table->foreignId('seite_id')->constrained('wiki_seiten')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('wiki_einschraenkungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seite_id')->constrained('wiki_seiten')->cascadeOnDelete();
            $table->string('berechtigter_type');
            $table->unsignedBigInteger('berechtigter_id');
            $table->timestamps();

            $table->unique(['seite_id', 'berechtigter_type', 'berechtigter_id'], 'wiki_einschraenkung_unique');
        });

        Schema::create('wiki_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('von_seite_id')->constrained('wiki_seiten')->cascadeOnDelete();
            $table->string('ziel_slug', 190);
            $table->foreignId('ziel_seite_id')->nullable()->constrained('wiki_seiten')->nullOnDelete();

            $table->unique(['von_seite_id', 'ziel_slug']);
            $table->index('ziel_seite_id');
            $table->index('ziel_slug');
        });

        foreach (self::PERMISSIONS as $name) {
            if (! DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // `manage wiki` bekommen zunächst die Rollen, die Rechte verwalten dürfen (Admins) – weitere über „Rechte & Rollen“
        $manage = DB::table('permissions')->where('name', 'manage wiki')->where('guard_name', 'web')->value('id');
        $admin = DB::table('permissions')->where('name', 'edit permissions')->where('guard_name', 'web')->value('id');
        if ($manage && $admin) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $admin)->pluck('role_id') as $rolle) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $manage, 'role_id' => $rolle]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_links');
        Schema::dropIfExists('wiki_einschraenkungen');
        Schema::dropIfExists('wiki_weiterleitungen');
        Schema::dropIfExists('wiki_favoriten');
        Schema::dropIfExists('wiki_seite_tag');
        Schema::dropIfExists('wiki_tags');
        Schema::dropIfExists('wiki_revisionen');
        Schema::dropIfExists('wiki_seiten');

        DB::table('permissions')->where('name', 'manage wiki')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
