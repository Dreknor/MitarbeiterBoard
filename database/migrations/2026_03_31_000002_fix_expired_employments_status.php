<?php

use App\Enums\TerminationReason;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Daten-Korrektur: Bestehende Anstellungen, deren Enddatum in der Vergangenheit liegt,
 * wurden durch die P0-04-Migration fälschlicherweise mit status='aktiv' belegt.
 *
 * Diese Migration setzt sie auf 'beendet' und gibt einen Standard-Beendigungsgrund an.
 */
return new class extends Migration
{
    public function up(): void
    {
        $updated = DB::table('employments')
            ->where('status', 'aktiv')
            ->whereNotNull('end')
            ->where('end', '<', now()->toDateString())
            ->update([
                'status'             => 'beendet',
                'termination_reason' => TerminationReason::Befristungsablauf->value,
                'updated_at'         => now(),
            ]);

        if ($updated > 0) {
            \Illuminate\Support\Facades\Log::info(
                "Daten-Migration: {$updated} abgelaufene Anstellungen von 'aktiv' auf 'beendet' korrigiert."
            );
        }
    }

    public function down(): void
    {
        // Nicht umkehrbar – war ein Datenfehler
    }
};


