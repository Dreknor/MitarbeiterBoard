<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Einstellungen je Person und Kategorie – fehlende Zeile = Standard aus config/benachrichtigungen.php
        if (!Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('kategorie', 50);
                $table->boolean('push')->default(false);
                $table->enum('mail', ['sofort', 'zusammenfassung', 'aus'])->default('sofort');
                $table->timestamps();

                $table->unique(['user_id', 'kategorie']);
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (!Schema::hasColumn('notifications', 'kategorie')) {
                    $table->string('kategorie', 50)->nullable()->after('type')->index();
                }
                if (!Schema::hasColumn('notifications', 'zusammenfassung_versendet_at')) {
                    $table->timestamp('zusammenfassung_versendet_at')->nullable()->after('read_at');
                }
            });
        }

        // Tagesübersicht („Dein Tag“) – fehlende Zeile = Standardwerte
        if (!Schema::hasTable('tagesvorschau_einstellungen')) {
            Schema::create('tagesvorschau_einstellungen', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->boolean('per_mail')->default(true);
                $table->boolean('per_push')->default(false);
                $table->enum('zeitpunkt', ['morgens', 'vorabend'])->default('morgens');
                $table->time('uhrzeit')->default('06:30:00');
                $table->json('bereiche')->nullable()->comment('null = alle sichtbaren Bereiche');
                $table->json('kalender_ids')->nullable();
                $table->boolean('eingeladene_termine')->default(true);
                $table->date('zuletzt_fuer_tag')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tagesvorschau_einstellungen');

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (Schema::hasColumn('notifications', 'kategorie')) {
                    $table->dropIndex(['kategorie']);
                    $table->dropColumn('kategorie');
                }
                if (Schema::hasColumn('notifications', 'zusammenfassung_versendet_at')) {
                    $table->dropColumn('zusammenfassung_versendet_at');
                }
            });
        }

        Schema::dropIfExists('notification_preferences');
    }
};
