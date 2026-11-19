<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * employes_data.geschlecht darf leer bleiben.
 *
 * Die Create-Migration beschreibt die Spalte als nullable, in bestehenden
 * MySQL-Installationen ist sie aber set('männlich','weiblich','anderes') NOT NULL
 * ohne Standardwert. Dadurch kann kein Stammdaten-Eintrag ohne Geschlecht angelegt
 * werden (z. B. beim Geburtstags-Import). Der Spaltentyp bleibt unverändert.
 */
return new class extends Migration
{
    public function up(): void
    {
        $spalte = $this->spalte();
        if (!$spalte || $spalte->Null === 'YES') {
            return;
        }

        DB::statement("ALTER TABLE `employes_data` MODIFY `geschlecht` {$spalte->Type} NULL DEFAULT NULL");
    }

    public function down(): void
    {
        $spalte = $this->spalte();
        if (!$spalte || $spalte->Null === 'NO') {
            return;
        }

        // Nur zurück auf NOT NULL, wenn keine leeren Werte existieren
        if (DB::table('employes_data')->whereNull('geschlecht')->exists()) {
            return;
        }

        DB::statement("ALTER TABLE `employes_data` MODIFY `geschlecht` {$spalte->Type} NOT NULL");
    }

    private function spalte(): ?object
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) || !Schema::hasTable('employes_data')) {
            return null; // SQLite (Tests): Spalte ist bereits nullable
        }

        return DB::selectOne("SHOW COLUMNS FROM `employes_data` WHERE Field = 'geschlecht'");
    }
};
