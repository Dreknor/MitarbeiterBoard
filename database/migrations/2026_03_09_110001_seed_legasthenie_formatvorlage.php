<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Nur anlegen wenn noch nicht vorhanden
        if (\App\Models\Wochenplan\WpFormatvorlage::where('name', 'Legasthenie-Unterstützung')->exists()) {
            return;
        }

        \App\Models\Wochenplan\WpFormatvorlage::create([
            'name'           => 'Legasthenie-Unterstützung',
            'beschreibung'   => 'OpenDyslexic-Schrift, große Zeichen, weite Abstände',
            'schriftgroesse' => 'gross',
            'schriftart'     => 'OpenDyslexic',
            'blade_template' => 'wochenplan.pdf.gross',
            'is_default'     => false,
            'created_by'     => 1,
            'layout_config'  => [
                'typografie' => [
                    'schriftgroesse_pt' => 16,
                    'zeilenabstand'     => 1.8,
                ],
                'abstände' => [
                    'zwischen_fächern'     => 8,
                    'zwischen_aufgaben'    => 4,
                    'min_fach_zeilenhoehe' => 10,
                ],
                'header' => [
                    'zeige_name_feld'         => true,
                    'zeige_klasse'            => true,
                    'zeige_zeitraum'          => true,
                    'zeige_logo'              => false,
                    'logo_pfad'               => null,
                    'freitext'                => '',
                    'namenszeile_zeilenhoehe' => 20,
                ],
                'footer' => [
                    'zeige_selbsteinschaetzung' => true,
                    'freitext'                  => '',
                ],
                'seitenraender' => [
                    'oben'   => 20,
                    'unten'  => 20,
                    'links'  => 15,
                    'rechts' => 15,
                ],
                'spalten' => [
                    'fach'                      => '15%',
                    'aufgaben'                  => '60%',
                    'check'                     => '5%',
                    'unterschrift'              => '20%',
                    'zeige_dauer'               => false,
                    'zeige_check_spalte'        => true,
                    'zeige_unterschrift_spalte' => true,
                    'zeige_kontrolliert_spalte' => false,
                ],
                'papier' => [
                    'groesse'     => 'A4',
                    'ausrichtung' => 'portrait',
                ],
            ],
        ]);
    }

    public function down(): void
    {
        \App\Models\Wochenplan\WpFormatvorlage::where('name', 'Legasthenie-Unterstützung')->delete();
    }
};

