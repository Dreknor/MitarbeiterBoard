<?php

namespace App\Console\Commands;

use App\Models\Klasse;
use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryClassGroup;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryColumnValue;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeedPaedDiaryDemoData extends Command
{
    protected $signature = 'paed-diary:demo-data
        {--keep : Bestehende Daten beibehalten (nur neue Demo-Daten hinzufuegen)}
        {--force : Ohne Rueckfrage bestehende Daten loeschen}';

    protected $description = 'Erzeugt Demo-Daten fuer das Paedagogische Tagebuch (Klassen, Schueler, Notizen, Aufgaben, Spalten, Termine)';

    /** Prefix fuer Demo-Kuerzel, damit Cleanup nur eigene Daten trifft. */
    private const DEMO_PREFIX = 'DEMO-';

    /** Deutsche Vornamen fuer Schueler-Demo-Daten. */
    private array $vornamen = [
        'Anna', 'Ben', 'Clara', 'David', 'Emilia', 'Felix', 'Greta', 'Henri',
        'Ida', 'Jakob', 'Karla', 'Linus', 'Mia', 'Noah', 'Olivia', 'Paul',
        'Rosalie', 'Samuel', 'Thea', 'Valentin', 'Wanda', 'Xaver', 'Yara', 'Zoe',
        'Luisa', 'Moritz', 'Nele', 'Oskar', 'Frieda', 'Leo', 'Martha', 'Tom',
    ];

    private array $nachnamen = [
        'Mueller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner',
        'Becker', 'Schulz', 'Hoffmann', 'Koch', 'Richter', 'Wolf', 'Schroeder',
        'Neumann', 'Schwarz', 'Braun', 'Zimmermann', 'Krueger', 'Hartmann',
        'Lange', 'Werner', 'Krause', 'Lehmann', 'Koehler', 'Herrmann',
    ];

    /** Beispiel-Notizen fuer realistische Eintraege. */
    private array $notizTexte = [
        'Hat heute sehr konzentriert gearbeitet und alle Aufgaben selbststaendig geloest.',
        'Benoetigt weiterhin Unterstuetzung beim Lesen laengerer Texte.',
        'Sehr gute Mitarbeit im Morgenkreis, hat von seinem Wochenende erzaehlt.',
        'Hatte heute einen Konflikt mit einem Mitschueler in der Pause - konnte aber gut geloest werden.',
        'Hat das Einmaleins-Training erfolgreich abgeschlossen.',
        'War heute sehr unruhig und konnte sich schwer konzentrieren.',
        'Elterngespraech vereinbart fuer naechste Woche.',
        'Hat beim Sachunterrichts-Projekt toll mitgemacht und eigene Ideen eingebracht.',
        'Zeigt grosse Fortschritte in der Feinmotorik beim Schreiben.',
        'Hat heute freiwillig einem Mitschueler bei der Aufgabe geholfen - toll!',
        'Musste mehrfach an die Gespraechsregeln erinnert werden.',
        'Hat sein Wochenziel erreicht und sich sehr gefreut.',
        'Arbeitet zunehmend selbststaendig an den Wochenplan-Aufgaben.',
        'Hat heute das Vorlese-Projekt vorgestellt - sehr mutig!',
        'Fuehlt sich in der neuen Sitzordnung wohl und arbeitet gut mit dem Sitznachbarn.',
        'Benoetigt bei Mathe-Textaufgaben noch zusaetzliche Erklaerungen.',
        'Hat in der Kunst-Stunde ein besonders kreatives Bild gemalt.',
        'Vergisst haeufig die Hausaufgaben - Eltern informieren.',
        'Gute Leistung im Diktat, nur 2 Fehler.',
        'Hat heute zum ersten Mal ohne Hilfe eine Rechengeschichte geloest.',
    ];

    private array $aufgabenTitel = [
        'Lesetagebuch vervollstaendigen',
        'Einmaleins-Reihe 7 ueben',
        'Referat zum Sachunterrichts-Thema vorbereiten',
        'Schoenschrift-Uebung nachholen',
        'Fehlende Unterschrift der Eltern nachreichen',
        'Gedicht auswendig lernen',
        'Mathe-Arbeitsheft S. 24-26 nachholen',
        'Buchvorstellung vorbereiten',
        'Wochenplan-Rueckstand aufarbeiten',
        'Entschuldigung fuer fehlenden Sportunterricht mitbringen',
    ];

    private array $aufgabenBeschreibung = [
        'Bitte bis Freitag erledigen.',
        'Eltern wurden informiert.',
        'Wird in der naechsten Woche kontrolliert.',
        'Gemeinsam mit Sitznachbar bearbeiten.',
        null,
        null,
        'Ggf. mit Foerderlehrer besprechen.',
    ];

    public function handle(): int
    {
        // 0. User ermitteln
        $user = User::first();
        if (!$user) {
            $this->error('Kein Benutzer in der Datenbank vorhanden. Bitte zuerst einen User anlegen.');
            return self::FAILURE;
        }
        $this->info('Verwende User: ' . $user->name . ' (ID ' . $user->id . ')');

        // 1. Ggf. bestehende Demo-Daten loeschen
        if (!$this->option('keep')) {
            if (!$this->option('force') && !$this->confirm('Alle bestehenden PaedDiary-Demo-Daten werden geloescht. Fortfahren?', true)) {
                $this->info('Abgebrochen.');
                return self::SUCCESS;
            }
            $this->cleanupDemoData();
        }

        // 2. Klassen anlegen
        $klassenDef = [
            ['name' => 'Demo 1a', 'kuerzel' => self::DEMO_PREFIX . '1a', 'color' => '#4A90D9'],
            ['name' => 'Demo 2b', 'kuerzel' => self::DEMO_PREFIX . '2b', 'color' => '#7CB342'],
            ['name' => 'Demo 3c', 'kuerzel' => self::DEMO_PREFIX . '3c', 'color' => '#FF7043'],
        ];
        $klassen = collect();
        foreach ($klassenDef as $kDef) {
            $klasse = Klasse::firstOrCreate(
                ['kuerzel' => $kDef['kuerzel']],
                ['name' => $kDef['name'], 'color' => $kDef['color']]
            );
            $klasse->paed_users()->syncWithoutDetaching([$user->id]);
            $klassen->push($klasse);
        }
        $this->info('[OK] ' . $klassen->count() . ' Klassen angelegt/gefunden und User zugewiesen.');

        // 3. Schueler anlegen
        $schuelerCount = 0;
        $schuelerPerKlasse = [];
        $usedNames = [];

        foreach ($klassen as $klasse) {
            $anzahl = rand(6, 8);
            $schuelerList = collect();
            for ($i = 0; $i < $anzahl; $i++) {
                do {
                    $vorname = $this->vornamen[array_rand($this->vornamen)];
                    $nachname = $this->nachnamen[array_rand($this->nachnamen)];
                    $nameKey = $vorname . '|' . $nachname;
                } while (in_array($nameKey, $usedNames));
                $usedNames[] = $nameKey;

                $s = Schueler::create([
                    'vorname' => $vorname,
                    'nachname' => $nachname,
                    'geburtsdatum' => Carbon::now()->subYears(rand(7, 12))->subDays(rand(0, 364)),
                    'klasse_id' => $klasse->id,
                    'import_key' => self::DEMO_PREFIX . Str::random(8),
                ]);
                $schuelerList->push($s);
                $schuelerCount++;
            }
            $schuelerPerKlasse[$klasse->id] = $schuelerList;
        }
        $this->info('[OK] ' . $schuelerCount . ' Schueler angelegt.');

        // 4. Klassengruppe anlegen
        $group = PaedDiaryClassGroup::create([
            'user_id' => $user->id,
            'name' => 'Demo Lerngruppe 1/2',
        ]);
        $group->klassen()->attach([$klassen[0]->id, $klassen[1]->id]);
        $this->info('[OK] Klassengruppe "' . $group->name . '" angelegt (' . $klassen[0]->name . ', ' . $klassen[1]->name . ').');

        // 5. Kategorien anlegen
        $kategorieNamen = ['Verhalten', 'Lernentwicklung', 'Elterngespraech', 'Allgemein'];
        $kategorien = collect();
        foreach ($kategorieNamen as $katName) {
            $kat = PaedDiaryCategory::firstOrCreate(
                ['name' => $katName, 'user_id' => $user->id]
            );
            $kategorien->push($kat);
        }
        $this->info('[OK] ' . $kategorien->count() . ' Notiz-Kategorien angelegt.');

        // 6. Spalten (Columns) anlegen
        $spaltenDef = [
            // Spaltengruppe Verhalten
            ['name' => '+', 'slug' => 'verhalten-plus',  'type' => 'boolean', 'category' => 'Verhalten', 'sort_order' => 1],
            ['name' => 'o', 'slug' => 'verhalten-o',     'type' => 'boolean', 'category' => 'Verhalten', 'sort_order' => 2],
            ['name' => '-', 'slug' => 'verhalten-minus',  'type' => 'boolean', 'category' => 'Verhalten', 'sort_order' => 3],
            // Spaltengruppe Wochenplan
            ['name' => 'Deu.', 'slug' => 'wp-deu', 'type' => 'text', 'category' => 'Wochenplan', 'sort_order' => 4],
            ['name' => 'Ma.',  'slug' => 'wp-ma',  'type' => 'text', 'category' => 'Wochenplan', 'sort_order' => 5],
            ['name' => 'SU',   'slug' => 'wp-su',  'type' => 'text', 'category' => 'Wochenplan', 'sort_order' => 6],
        ];

        $spaltenPerKlasse = [];
        foreach ($klassen as $klasse) {
            $spalteListe = collect();
            foreach ($spaltenDef as $sDef) {
                $col = PaedDiaryColumn::firstOrCreate(
                    ['klasse_id' => $klasse->id, 'slug' => $sDef['slug']],
                    [
                        'name' => $sDef['name'],
                        'type' => $sDef['type'],
                        'sort_order' => $sDef['sort_order'],
                        'active' => true,
                        'category' => $sDef['category'],
                    ]
                );
                $spalteListe->push($col);
            }
            $spaltenPerKlasse[$klasse->id] = $spalteListe;
        }
        $this->info('[OK] Spalten angelegt (Verhalten: +/o/-, Wochenplan: Deu./Ma./SU) fuer jede Klasse.');

        // 7. Notizen (Entries) anlegen
        $entryCount = 0;
        $today = Carbon::today();
        $werkTage = $this->getWerktage($today->copy()->subDays(21), $today);

        foreach ($klassen as $klasse) {
            $sList = $schuelerPerKlasse[$klasse->id];
            $anzahlEntries = rand(8, 14);
            for ($e = 0; $e < $anzahlEntries; $e++) {
                $datum = $werkTage[array_rand($werkTage)];
                $kategorie = $kategorien->random();

                $entry = PaedDiaryEntry::create([
                    'klasse_id' => $klasse->id,
                    'user_id' => $user->id,
                    'datum' => $datum,
                    'content' => $this->notizTexte[array_rand($this->notizTexte)],
                    'category_id' => $kategorie->id,
                    'completed_at' => rand(0, 3) === 0 ? null : Carbon::parse($datum)->setHour(rand(10, 16)),
                    'dossier_only' => rand(0, 9) === 0,
                ]);

                // 1-4 zufaellige Schueler zuweisen
                $zugewiesene = $sList->random(min(rand(1, 4), $sList->count()));
                $entry->schueler()->attach($zugewiesene->pluck('id'));

                $entryCount++;
            }
        }
        $this->info('[OK] ' . $entryCount . ' Notizen/Eintraege angelegt.');

        // 8. Aufgaben (Tasks) anlegen
        $taskCount = 0;
        foreach ($klassen as $klasse) {
            $sList = $schuelerPerKlasse[$klasse->id];
            $anzahlTasks = rand(3, 5);
            for ($t = 0; $t < $anzahlTasks; $t++) {
                $schueler = $sList->random();
                $isOpen = rand(0, 2) > 0;
                PaedDiaryTask::create([
                    'klasse_id' => $klasse->id,
                    'schueler_id' => $schueler->id,
                    'title' => $this->aufgabenTitel[array_rand($this->aufgabenTitel)],
                    'description' => $this->aufgabenBeschreibung[array_rand($this->aufgabenBeschreibung)],
                    'due_date' => $isOpen
                        ? Carbon::today()->addDays(rand(1, 14))
                        : Carbon::today()->subDays(rand(1, 7)),
                    'status' => $isOpen ? 'open' : 'closed',
                    'highlighted' => rand(0, 2) === 0,
                    'created_by' => $user->id,
                    'closed_at' => $isOpen ? null : Carbon::now()->subDays(rand(0, 3)),
                ]);
                $taskCount++;
            }
        }
        $this->info('[OK] ' . $taskCount . ' Aufgaben angelegt.');

        // 9. Spaltenwerte (ColumnValues)
        $valueCount = 0;
        $wpTexte = ['erledigt', 'halb', 'offen', 'gut', 'n.b.', ''];

        foreach ($klassen as $klasse) {
            $spalten = $spaltenPerKlasse[$klasse->id];
            $sList = $schuelerPerKlasse[$klasse->id];

            $wertTage = $this->getWerktage($today->copy()->subDays(14), $today);

            foreach ($sList as $schueler) {
                foreach ($wertTage as $tag) {
                    // Nicht fuer jeden Tag jede Spalte - realistischer
                    if (rand(0, 3) === 0) {
                        continue;
                    }

                    foreach ($spalten as $col) {
                        // ~60% Chance, dass ein Wert gesetzt wird
                        if (rand(0, 4) < 2) {
                            continue;
                        }

                        if ($col->type === 'boolean') {
                            $val = (string) rand(0, 1);
                        } else {
                            $val = $wpTexte[array_rand($wpTexte)];
                        }

                        PaedDiaryColumnValue::updateOrCreate(
                            [
                                'paed_diary_column_id' => $col->id,
                                'schueler_id' => $schueler->id,
                                'datum' => $tag,
                            ],
                            ['value' => $val]
                        );
                        $valueCount++;
                    }
                }
            }
        }
        $this->info('[OK] ' . $valueCount . ' Spaltenwerte eingetragen.');

        // 10. Termine (Appointments) anlegen
        $appointmentCount = 0;

        // Termin 1: Gruppen-Termin (wiederkehrend, woechentlich)
        $apt1 = PaedDiaryAppointment::create([
            'user_id' => $user->id,
            'title' => 'Morgenkreis Lerngruppe 1/2',
            'description' => 'Gemeinsamer Morgenkreis beider Klassen.',
            'start_date' => Carbon::today()->startOfWeek()->addDay(),
            'start_time' => '08:00',
            'end_time' => '08:30',
            'is_recurring' => true,
            'recurring_type' => 'weekly',
            'recurring_interval' => 1,
            'recurring_end_date' => Carbon::today()->addMonths(3),
            'is_paused' => false,
        ]);
        $apt1->groups()->attach([$group->id]);
        $appointmentCount++;

        // Termin 2: Klassen-Termin (einmalig)
        $apt2 = PaedDiaryAppointment::create([
            'user_id' => $user->id,
            'title' => 'Klassenfahrt-Vorbereitung',
            'description' => 'Organisatorisches zur Klassenfahrt besprechen.',
            'start_date' => Carbon::today()->addDays(5),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'is_recurring' => false,
            'is_paused' => false,
        ]);
        $apt2->klassen()->attach([$klassen[2]->id]);
        $appointmentCount++;

        // Termin 3: Schueler-Termin (Elterngespraech)
        $einSchueler = $schuelerPerKlasse[$klassen[0]->id]->random();
        $apt3 = PaedDiaryAppointment::create([
            'user_id' => $user->id,
            'title' => 'Elterngespraech ' . $einSchueler->vorname . ' ' . $einSchueler->nachname,
            'description' => 'Lernstandsgespraech mit den Eltern.',
            'start_date' => Carbon::today()->addDays(7),
            'start_time' => '14:00',
            'end_time' => '14:45',
            'is_recurring' => false,
            'is_paused' => false,
        ]);
        $apt3->schueler()->attach([$einSchueler->id]);
        $apt3->klassen()->attach([$klassen[0]->id]);
        $appointmentCount++;

        // Termin 4: Wiederkehrender Klassen-Termin
        $apt4 = PaedDiaryAppointment::create([
            'user_id' => $user->id,
            'title' => 'Wochenabschlusskreis',
            'description' => 'Gemeinsame Reflexion der Woche.',
            'start_date' => Carbon::today()->startOfWeek()->addDays(4),
            'start_time' => '12:00',
            'end_time' => '12:30',
            'is_recurring' => true,
            'recurring_type' => 'weekly',
            'recurring_interval' => 1,
            'recurring_end_date' => Carbon::today()->addMonths(2),
            'is_paused' => false,
        ]);
        $apt4->klassen()->attach([$klassen[0]->id, $klassen[1]->id]);
        $appointmentCount++;

        // Termin 5: Einmaliger Schueler-Termin
        $zweiterSchueler = $schuelerPerKlasse[$klassen[1]->id]->random();
        $apt5 = PaedDiaryAppointment::create([
            'user_id' => $user->id,
            'title' => 'Foerderplan-Gespraech ' . $zweiterSchueler->vorname,
            'description' => 'Foerderplan erstellen und Ziele besprechen.',
            'start_date' => Carbon::today()->addDays(3),
            'start_time' => '13:00',
            'end_time' => '13:30',
            'is_recurring' => false,
            'is_paused' => false,
        ]);
        $apt5->schueler()->attach([$zweiterSchueler->id]);
        $apt5->klassen()->attach([$klassen[1]->id]);
        $appointmentCount++;

        $this->info('[OK] ' . $appointmentCount . ' Termine angelegt (Gruppen-, Klassen- und Schueler-Termine).');

        // Fertig
        $this->newLine();
        $this->info('Demo-Daten fuer das Paedagogische Tagebuch erfolgreich angelegt!');
        $this->table(
            ['Objekt', 'Anzahl'],
            [
                ['Klassen', $klassen->count()],
                ['Schueler', $schuelerCount],
                ['Klassengruppe', 1],
                ['Kategorien', $kategorien->count()],
                ['Spalten pro Klasse', count($spaltenDef)],
                ['Notizen/Eintraege', $entryCount],
                ['Aufgaben', $taskCount],
                ['Spaltenwerte', $valueCount],
                ['Termine', $appointmentCount],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Entfernt alle Demo-Daten (Klassen mit DEMO-Prefix und verknuepfte Daten).
     */
    private function cleanupDemoData(): void
    {
        $this->info('Loesche bestehende Demo-Daten...');

        $demoKlassenIds = Klasse::where('kuerzel', 'like', self::DEMO_PREFIX . '%')
            ->pluck('id')
            ->all();

        if (empty($demoKlassenIds)) {
            $this->info('  Keine bestehenden Demo-Klassen gefunden.');
            return;
        }

        $demoSchuelerIds = Schueler::whereIn('klasse_id', $demoKlassenIds)->pluck('id')->all();

        Schema::disableForeignKeyConstraints();

        // Entry-Pivot und Pauses
        if (!empty($demoSchuelerIds)) {
            DB::table('paed_diary_entry_schueler')->whereIn('schueler_id', $demoSchuelerIds)->delete();
            if (Schema::hasTable('paed_diary_entry_pauses')) {
                DB::table('paed_diary_entry_pauses')->whereIn('schueler_id', $demoSchuelerIds)->delete();
            }
        }

        // Entries
        PaedDiaryEntry::whereIn('klasse_id', $demoKlassenIds)->delete();

        // Column Values und Columns
        $demoColumnIds = PaedDiaryColumn::whereIn('klasse_id', $demoKlassenIds)->pluck('id')->all();
        if (!empty($demoColumnIds)) {
            PaedDiaryColumnValue::whereIn('paed_diary_column_id', $demoColumnIds)->delete();
        }
        PaedDiaryColumn::whereIn('klasse_id', $demoKlassenIds)->delete();

        // Tasks
        PaedDiaryTask::whereIn('klasse_id', $demoKlassenIds)->forceDelete();

        // Appointments: Pivot-Tabellen zuerst
        if (!empty($demoKlassenIds)) {
            DB::table('paed_diary_appointment_klassen')->whereIn('klasse_id', $demoKlassenIds)->delete();
        }
        if (!empty($demoSchuelerIds)) {
            DB::table('paed_diary_appointment_schueler')->whereIn('schueler_id', $demoSchuelerIds)->delete();
        }

        // ClassGroups die mit Demo beginnen
        $demoGroups = PaedDiaryClassGroup::where('name', 'like', 'Demo%')->pluck('id')->all();
        if (!empty($demoGroups)) {
            DB::table('paed_diary_class_group_klasse')->whereIn('group_id', $demoGroups)->delete();
            DB::table('paed_diary_appointment_groups')->whereIn('paed_diary_class_group_id', $demoGroups)->delete();
            PaedDiaryClassGroup::whereIn('id', $demoGroups)->delete();
        }

        // Verwaiste Appointments loeschen (ohne Klassen, Gruppen oder Schueler)
        $orphanedApts = PaedDiaryAppointment::whereDoesntHave('klassen')
            ->whereDoesntHave('groups')
            ->whereDoesntHave('schueler')
            ->pluck('id')->all();
        if (!empty($orphanedApts)) {
            PaedDiaryAppointment::whereIn('id', $orphanedApts)->delete();
        }

        // Schueler loeschen
        Schueler::whereIn('klasse_id', $demoKlassenIds)->forceDelete();

        // Klasse-User Pivot
        DB::table('klasse_user')->whereIn('klasse_id', $demoKlassenIds)->delete();

        // Klassen loeschen
        Klasse::whereIn('id', $demoKlassenIds)->forceDelete();

        Schema::enableForeignKeyConstraints();

        $klassenAnzahl = count($demoKlassenIds);
        $schuelerAnzahl = count($demoSchuelerIds);
        $this->info('  [OK] Demo-Daten geloescht (' . $klassenAnzahl . ' Klassen, ' . $schuelerAnzahl . ' Schueler).');
    }

    /**
     * Gibt Werktage (Mo-Fr) im Zeitraum zurueck.
     *
     * @return string[]
     */
    private function getWerktage(Carbon $von, Carbon $bis): array
    {
        $tage = [];
        $cursor = $von->copy();
        while ($cursor->lte($bis)) {
            if ($cursor->isWeekday()) {
                $tage[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }
        return $tage;
    }
}


