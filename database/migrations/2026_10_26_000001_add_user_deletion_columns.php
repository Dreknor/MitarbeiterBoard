<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Löschkonzept: Spalten für die drei Löschstufen (Deaktivieren, Anonymisieren, endgültig Löschen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('left_at')->nullable()->comment('Austrittsdatum, Beginn aller Löschfristen');
            $table->unsignedTinyInteger('deletion_stage')->default(0)->comment('0 aktiv, 1 deaktiviert, 2 anonymisiert');
            $table->timestamp('anonymized_at')->nullable();
            $table->boolean('legal_hold')->default(false)->comment('Hält die endgültige Löschung an (Rechtsstreit, Prüfung)');
            $table->string('legal_hold_reason')->nullable();
            $table->string('sso_uid_hash', 64)->nullable()->index()->comment('HMAC der SSO-Kennung, blockiert Neuanlage nach Anonymisierung');
            $table->boolean('is_placeholder')->default(false);
            $table->unsignedTinyInteger('deletion_notified_stage')->default(0)->comment('Stufe, für die Admins zuletzt informiert wurden');
            $table->timestamp('deletion_reviewed_at')->nullable()->comment('Admin-Entscheidung "vorerst behalten"');
            $table->text('deletion_review_note')->nullable();
        });

        Schema::table('pers_document_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('retention_years')->nullable()
                ->comment('Aufbewahrung nach Austritt in Jahren, leer = Lohnunterlagen (8 Jahre)');
        });

        // Protokoll der Löschschritte, bewusst ohne Fremdschlüssel und ohne Personendaten
        Schema::create('user_deletion_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('action', 40);
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->json('counts')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Bestehende deaktivierte Konten: Austritt = Datum der Deaktivierung
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->update(['deletion_stage' => 1]);

        foreach (DB::table('users')->whereNotNull('deleted_at')->get(['id', 'deleted_at']) as $user) {
            DB::table('users')->where('id', $user->id)
                ->update(['left_at' => substr((string) $user->deleted_at, 0, 10)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_deletion_logs');

        Schema::table('pers_document_types', function (Blueprint $table) {
            $table->dropColumn('retention_years');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['sso_uid_hash']);
            $table->dropColumn([
                'left_at', 'deletion_stage', 'anonymized_at', 'legal_hold', 'legal_hold_reason',
                'sso_uid_hash', 'is_placeholder', 'deletion_notified_stage',
                'deletion_reviewed_at', 'deletion_review_note',
            ]);
        });
    }
};
