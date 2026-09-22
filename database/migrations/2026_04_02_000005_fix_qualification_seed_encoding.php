<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Korrigiert fehlerhafte UTF-8-Sonderzeichen in den Seed-Daten der
 * pers_qualification_types-Tabelle (eingefügt durch Migration 2026_04_02_000002).
 */
return new class extends Migration
{
    public function up(): void
    {
        $fixes = [
            ['old' => 'Erweitertes Fhrungszeugnis', 'new' => 'Erweitertes Führungszeugnis'],
            ['old' => 'Gem  30a BZRG',              'new' => 'Gemäß § 30a BZRG'],
            ['old' => 'Gem  20 Abs. 9 IfSG',        'new' => 'Gemäß § 20 Abs. 9 IfSG'],
            ['old' => 'Schwimmfhigkeitsnachweis',   'new' => 'Schwimmfähigkeitsnachweis'],
            ['old' => 'Religionspdagogische Qualifikation', 'new' => 'Religionspädagogische Qualifikation'],
            ['old' => 'Fachkraft nach 21 SchsKitaG', 'new' => 'Fachkraft nach §21 SächsKitaG'],
        ];

        foreach ($fixes as $fix) {
            DB::table('pers_qualification_types')
                ->where('name', $fix['old'])
                ->update(['name' => $fix['new']]);

            // Auch Description korrigieren falls vorhanden
            DB::table('pers_qualification_types')
                ->where('description', $fix['old'])
                ->update(['description' => $fix['new']]);
        }

        // Description-Korrekturen separat
        DB::table('pers_qualification_types')
            ->where('name', 'Erweitertes Führungszeugnis')
            ->where(function ($q) {
                $q->where('description', 'Gem  30a BZRG')
                  ->orWhere('description', 'Gemäß  30a BZRG');
            })
            ->update(['description' => 'Gemäß § 30a BZRG']);

        DB::table('pers_qualification_types')
            ->where('name', 'Masernschutz-Nachweis')
            ->where(function ($q) {
                $q->where('description', 'Gem  20 Abs. 9 IfSG')
                  ->orWhere('description', 'Gemäß  20 Abs. 9 IfSG');
            })
            ->update(['description' => 'Gemäß § 20 Abs. 9 IfSG']);
    }

    public function down(): void
    {
        // Nicht rückgängig machbar
    }
};

