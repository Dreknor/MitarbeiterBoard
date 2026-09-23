<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pädagogen-App: Token-Laufzeit wird ab jetzt über expires_at je Token gesteuert
 * (gleitend, PAED_APP_TOKEN_DAYS) statt über die globale Sanctum-Laufzeit (bisher 30 Tage ab Ausstellung).
 *
 * Bereits ausgestellte Tokens ohne expires_at erhalten ihr bisheriges effektives Ablaufdatum
 * (Ausstellung + 30 Tage), damit sie durch die Umstellung nicht unbegrenzt gültig werden.
 * Bei der nächsten Nutzung wird die Laufzeit wie bei neuen Tokens verlängert.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            return;
        }

        DB::table('personal_access_tokens')
            ->whereNull('expires_at')
            ->orderBy('id')
            ->each(function ($token) {
                $created = $token->created_at ? \Carbon\Carbon::parse($token->created_at) : now();

                DB::table('personal_access_tokens')
                    ->where('id', $token->id)
                    ->update(['expires_at' => $created->addDays(30)]);
            });
    }

    public function down(): void
    {
        // Datenmigration: Nicht umkehrbar. Das bisherige Verhalten (30 Tage ab Ausstellung)
        // lässt sich über SANCTUM_EXPIRATION=43200 wiederherstellen.
    }
};
