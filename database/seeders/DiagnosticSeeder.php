<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DiagnosticSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Foreign Key Checks deaktivieren, um Tabellen leeren zu können
        Schema::disableForeignKeyConstraints();
        DB::table('diagnostic_goals')->truncate();
        DB::table('diagnostic_stages')->truncate();
        DB::table('diagnostic_areas')->truncate();
        Schema::enableForeignKeyConstraints();

        $areas = [
            [
                'name' => 'Verhalten',
                'description' => 'Bereichsziel: Verhalten',
                'sort_order' => 1,
                'stages' => [
                    [
                        'code' => 'I',
                        'name' => 'Stufe I',
                        'goal_description' => 'Mit Freude auf die Umwelt reagieren. Den eigenen körperlichen Fähigkeiten vertrauen.',
                        'goals' => [
                            ['code' => 'V-1', 'desc' => 'Lässt Wahrnehmung eines sensorischen Reizes erkennen durch beliebige Bewegungsreaktionen von der Reizquelle weg oder zu ihr hin.'],
                            ['code' => 'V-2', 'desc' => 'Reagiert auf sensorischen Reiz mit Zuwendung zur Reizquelle, entweder durch körperliche Reaktion oder durch Hinsehen.'],
                            ['code' => 'V-3', 'desc' => 'Reagiert auf einen Reiz mit kurzzeitig anhaltender Aufmerksamkeit.'],
                            ['code' => 'V-4', 'desc' => 'Reagiert von sich aus auf einfache Umgebungsreize mit einer motorischen Handlung.'],
                            ['code' => 'V-5', 'desc' => 'Reagiert auf komplexe Umgebungsreize und verbale Impulse mit motorischer Handlung.'],
                            ['code' => 'V-6', 'desc' => 'Beteiligt sich aktiv am Erlernen von Selbsthilfe-Fähigkeiten.'],
                            ['code' => 'V-7', 'desc' => 'Reagiert eigenständig auf verschiedene Spielmaterialien.'],
                            ['code' => 'V-8', 'desc' => 'Zeigt Wiedererkennen von Routineabläufen durch eigenständigen Wechsel von einem Aktivitätsbereich zum nächsten.'],
                        ]
                    ],
                    [
                        'code' => 'II',
                        'name' => 'Stufe II',
                        'goal_description' => 'Erfolgreich auf die Umwelt reagieren. Erfolgreich an Routineabläufen und Aktivitäten teilnehmen.',
                        'goals' => [
                            ['code' => 'V-9', 'desc' => 'Geht mit Spielmaterialien sachgerecht um (Bewusstheit der Funktion im realen Leben und in vorgeblichen Bezügen).'],
                            ['code' => 'V-10', 'desc' => 'Wartet ohne körperliche Steuerungshilfe durch den Erwachsenen.'],
                            ['code' => 'V-11', 'desc' => 'Beteiligt sich verbal und physisch an Aktivitäten im Sitzen ohne körperliche Steuerungshilfe.'],
                            ['code' => 'V-12', 'desc' => 'Beteiligt sich verbal und physisch an Bewegungsaktivitäten ohne körperliche Steuerungshilfe.'],
                            ['code' => 'V-13', 'desc' => 'Nimmt von sich aus verbal und physisch an Aktivitäten teil ohne körperliche Steuerungshilfe.'],
                            ['code' => 'V-14', 'desc' => 'Akzeptiert Lob oder Erfolg ohne unangemessenes Verhalten oder Kontrollverlust.'],
                        ]
                    ],
                    [
                        'code' => 'III',
                        'name' => 'Stufe III',
                        'goal_description' => 'Erwerben von Fähigkeiten zur erfolgreichen Teilnahme in Gruppen. Erworbene Fähigkeiten anwenden, um innerhalb einer Gruppe das eigene Verhalten erfolgreich zu steuern.',
                        'goals' => [
                            ['code' => 'V-15', 'desc' => 'Beendet kurze, individuelle Aufgaben mit vertrautem Material selbstständig ohne jede Intervention Erwachsener.'],
                            ['code' => 'V-16', 'desc' => 'Lässt Bewusstsein für Verhaltensweisen erkennen, die zu Hause, in der Schule und in der Öffentlichkeit erwartet werden.'],
                            ['code' => 'V-17', 'desc' => 'Nennt Gründe für Verhaltenserwartungen, die zu Hause, in der Schule und in der Öffentlichkeit bedeutsam sind.'],
                            ['code' => 'V-18', 'desc' => 'Beschreibt alternative, angemessenere Verhaltensmöglichkeiten für eine gegebene Situation.'],
                            ['code' => 'V-19', 'desc' => 'Reagiert angemessen auf Gruppenwahl als Anführer bzw. Teilnehmer.'],
                            ['code' => 'V-20', 'desc' => 'Hält sich von inakzeptablem Verhalten zurück, wenn andere in der Gruppe die Selbstkontrolle verlieren.'],
                            ['code' => 'V-21', 'desc' => 'Behält während der Gruppenaktivitäten akzeptable physische und verbale Selbstkontrolle, auch bei Übergängen.'],
                        ]
                    ],
                    [
                        'code' => 'IV',
                        'name' => 'Stufe IV',
                        'goal_description' => 'Sich einbringen in Gruppenprozesse. Persönliche Fähigkeiten einsetzen, um zum Gruppenerfolg beizutragen.',
                        'goals' => [
                            ['code' => 'V-22', 'desc' => 'Zeigt beginnendes Bewusstsein für eigenen Verhaltensfortschritt.'],
                            ['code' => 'V-23', 'desc' => 'Lässt Flexibilität erkennen, wenn Abläufe aufgrund sich ändernder Anforderungen an die Gruppe umgestaltet werden müssen.'],
                            ['code' => 'V-24', 'desc' => 'Beteiligt sich verbal und physisch kontrolliert an neuen Erfahrungen bzw. Aktivitäten.'],
                            ['code' => 'V-25', 'desc' => 'Wendet alternative, sozial akzeptable Verhaltensweisen an.'],
                            ['code' => 'V-26', 'desc' => 'Reagiert von sich aus auf Provokationen mit verbal und physisch kontrolliertem Verhalten.'],
                            ['code' => 'V-27', 'desc' => 'Akzeptiert Verantwortung für die Folgen des eigenen Verhaltens und eigener Einstellungen.'],
                            ['code' => 'V-28', 'desc' => 'Reagiert in kritischen Situationen auf Probleme zwischen einzelnen Personen oder innerhalb der Gruppe mit konstruktiven Lösungsvorschlägen.'],
                        ]
                    ],
                    [
                        'code' => 'V',
                        'name' => 'Stufe V',
                        'goal_description' => 'Anwenden von individuellen und gruppenbezogenen Fähigkeiten in neuen Situationen. Realen Lebenserfahrungen mit konstruktivem Verhalten begegnen.',
                        'goals' => [
                            ['code' => 'V-29', 'desc' => 'Entwickelt neue persönliche Gewohnheiten oder Fähigkeiten mit Bezug zur Arbeitswelt.'],
                            ['code' => 'V-30', 'desc' => 'Sucht und entwickelt eine begehrte positive Rolle innerhalb einer Gruppe.'],
                            ['code' => 'V-31', 'desc' => 'Zeigt verbal oder durch bewusste Entscheidung für bestimmte Verhaltensoptionen Verständnis und Akzeptanz von Rechts- und Ordnungsprinzipien.'],
                            ['code' => 'V-32', 'desc' => 'Befürwortet Verfahren zur Selbstverantwortung und Regelung des Gruppenlebens und beteiligt sich daran.'],
                            ['code' => 'V-33', 'desc' => 'Löst persönliche Probleme anhand von Einsicht, Analyse und Generalisierung.'],
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Kommunikation',
                'description' => 'Bereichsziel: Kommunikation',
                'sort_order' => 2,
                'stages' => [
                    [
                        'code' => 'I',
                        'name' => 'Stufe I',
                        'goal_description' => 'Mit Freude auf die Umwelt reagieren. Gebraucht Wörter, um Bedürfnisse zu befriedigen.',
                        'goals' => [
                            ['code' => 'K-1', 'desc' => 'Produziert Laute. (Das Kind wiederholt eigene Lautmuster).'],
                            ['code' => 'K-2', 'desc' => 'Richtet die Aufmerksamkeit auf eine sprechende Person.'],
                            ['code' => 'K-3', 'desc' => 'Reagiert auf einen verbalen Impuls mit einer Bewegung oder Handlung.'],
                            ['code' => 'K-4', 'desc' => 'Reagiert verbal auf Fragen oder Aufforderungen von Erwachsenen mit erkennbaren Wort-Annäherungen.'],
                            ['code' => 'K-5', 'desc' => 'Verwendet von sich aus erkennbare, relevante Wort-Annäherungen (oder Wörter) bei verschiedenen Aktivitäten.'],
                            ['code' => 'K-6', 'desc' => 'Produziert einzelne erkennbare Wörter während verschiedener Aktivitäten, um eine gewünschte Reaktion des Erwachsenen zu erhalten.'],
                            ['code' => 'K-7', 'desc' => 'Produziert einzelne erkennbare Wörter, um eine erwünschte Reaktion von einem gleichaltrigen Kind zu erhalten.'],
                            ['code' => 'K-8', 'desc' => 'Produziert eine sinnvolle Wortsequenz ohne Vorbild durch Erwachsene.'],
                        ]
                    ],
                    [
                        'code' => 'II',
                        'name' => 'Stufe II',
                        'goal_description' => 'Erfolgreich auf die Umwelt reagieren. Gebraucht Wörter, um andere in konstruktiver Weise zu beeinflussen.',
                        'goals' => [
                            ['code' => 'K-9', 'desc' => 'Beantwortet Fragen, Bitten oder Aufforderungen eines anderen Kindes oder eines Erwachsenen mit erkennbaren Wörtern.'],
                            ['code' => 'K-10', 'desc' => 'Zeigt ein rezeptives Vokabular, das nicht mehr als zwei Jahre hinter normalen Erwartungen zurückliegt.'],
                            ['code' => 'K-11', 'desc' => 'Verwendet von sich aus einfache Wortsequenzen, um etwas zu fordern, zu erfragen oder zu erbitten.'],
                            ['code' => 'K-12', 'desc' => 'Verwendet von sich aus Wörter, um mit einem Erwachsenen minimale Informationen auszutauschen.'],
                            ['code' => 'K-13', 'desc' => 'Beschreibt einfache, konkrete Merkmale sowohl von sich als auch von anderen.'],
                            ['code' => 'K-14', 'desc' => 'Verwendet von sich aus Wörter, um mit einem anderen Kind minimale Informationen auszutauschen.'],
                        ]
                    ],
                    [
                        'code' => 'III',
                        'name' => 'Stufe III',
                        'goal_description' => 'Erwerben von Fähigkeiten zur erfolgreichen Teilnahme in Gruppen. Gebraucht Wörter, um sich auf konstruktive Weise innerhalb einer Gruppe zu äußern.',
                        'goals' => [
                            ['code' => 'K-15', 'desc' => 'Verwendet von sich aus Wörter, um eigene Erfahrungen, Vorstellungen oder Arbeit zu beschreiben.'],
                            ['code' => 'K-16', 'desc' => 'Verwendet Wörter oder Gesten, um angemessene positive oder negative Gefühlsreaktionen zu zeigen.'],
                            ['code' => 'K-17', 'desc' => 'Beteiligt sich an Gruppengesprächen in einer Weise, die sich nicht störend auf die Gruppe auswirkt.'],
                            ['code' => 'K-18', 'desc' => 'Verwendet von sich aus Wörter, um Stolz auf eigene Arbeit oder Aktivitäten zu zeigen.'],
                            ['code' => 'K-19', 'desc' => 'Beschreibt charakteristische Eigenschaften, Stärken und Schwächen bei sich selbst.'],
                            ['code' => 'K-20', 'desc' => 'Beschreibt charakteristische Eigenschaften bei anderen.'],
                            ['code' => 'K-21', 'desc' => 'Erkennt Gefühle anderer.'],
                            ['code' => 'K-22', 'desc' => 'Verwendet von sich aus Wörter, um Stolz auf Gruppenleistungen auszudrücken (Wir-Gefühl).'],
                        ]
                    ],
                    [
                        'code' => 'IV',
                        'name' => 'Stufe IV',
                        'goal_description' => 'Sich einbringen in Gruppenprozesse. Verwendet Wörter, um Verständnis von Gefühlen und Verhaltensweisen von sich und anderen zu zeigen.',
                        'goals' => [
                            ['code' => 'K-23', 'desc' => 'Kanalisiert Gefühle oder Erfahrungen durch kreative Ausdrucksmittel wie Kunst, Musik, Tanz oder szenisches Spiel.'],
                            ['code' => 'K-24', 'desc' => 'Zeigt beginnendes Bewusstsein für eigenen Verhaltensfortschritt.'],
                            ['code' => 'K-25', 'desc' => 'Erklärt, wie eigenes Verhalten das Verhalten anderer beeinflusst.'],
                            ['code' => 'K-26', 'desc' => 'Verwendet Wörter, um in der Gruppe von sich aus eigene Gefühle auf angemessene Weise auszudrücken.'],
                            ['code' => 'K-27', 'desc' => 'Verwendet Wörter, um positive Beziehungen sowohl mit Gleichaltrigen als auch mit Erwachsenen anzuknüpfen.'],
                            ['code' => 'K-28', 'desc' => 'Verwendet Wörter, um von sich aus eine andere Person zu loben oder persönlich zu unterstützen.'],
                            ['code' => 'K-29', 'desc' => 'Beschreibt von sich aus den Ursache-Wirkungs-Zusammenhang von Gefühlen und Verhalten bei sich selbst und anderen.'],
                        ]
                    ],
                    [
                        'code' => 'V',
                        'name' => 'Stufe V',
                        'goal_description' => 'Anwenden von individuellen und gruppenbezogenen Fähigkeiten in neuen Situationen. Verwendet Wörter, um Beziehungen auszubauen und zu pflegen.',
                        'goals' => [
                            ['code' => 'K-30', 'desc' => 'Formuliert Aussagen, die weitgehend komplex strukturiert sind und inhaltlich bildhaft oder abstrakt sind.'],
                            ['code' => 'K-31', 'desc' => 'Wählt bei Provokationen in der Gruppe von sich aus einen Sprachgebrauch, der auf versöhnliche oder schlichtende Absichten hindeutet.'],
                            ['code' => 'K-32', 'desc' => 'Unterstützt andere durch Anerkennung ihrer Beiträge und bezieht ihre Ideen in eigene Äußerungen mit ein.'],
                            ['code' => 'K-33', 'desc' => 'Beschreibt verschiedene Motive und Wertvorstellungen in sozialen Situationen.'],
                            ['code' => 'K-34', 'desc' => 'Beschreibt von sich aus eigene Wertvorstellungen, Ideale, persönliche Bindungen und Überzeugungen.'],
                            ['code' => 'K-35', 'desc' => 'Verwendet kommunikative Fähigkeiten, um positive zwischenmenschliche Beziehungen, auch innerhalb der Gruppe, mitzutragen und zu erhalten.'],
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Sozialisation',
                'description' => 'Bereichsziel: Sozialisation',
                'sort_order' => 3,
                'stages' => [
                    [
                        'code' => 'I',
                        'name' => 'Stufe I',
                        'goal_description' => 'Mit Freude auf die Umwelt reagieren. Einem Erwachsenen genügend vertrauen, um auf ihn zu reagieren.',
                        'goals' => [
                            ['code' => 'SOZ-1', 'desc' => 'Ist sich der Gegenwart anderer bewusst.'],
                            ['code' => 'SOZ-2', 'desc' => 'Richtet Aufmerksamkeit auf Handlungen anderer.'],
                            ['code' => 'SOZ-3', 'desc' => 'Reagiert, wenn ein Erwachsener den Namen des Kindes nennt.'],
                            ['code' => 'SOZ-4', 'desc' => 'Beschäftigt sich mit organisiertem Spiel und spielt dabei für sich allein.'],
                            ['code' => 'SOZ-5', 'desc' => 'Interagiert nonverbal mit Erwachsenen, um Bedürfnisse auszudrücken.'],
                            ['code' => 'SOZ-6', 'desc' => 'Reagiert auf die verbale und nonverbale Aufforderung des Erwachsenen, zu ihm zu kommen.'],
                            ['code' => 'SOZ-7', 'desc' => 'Zeigt, dass es einzelne, verbale Aufforderungen oder Anweisungen des Erwachsenen versteht.'],
                            ['code' => 'SOZ-8', 'desc' => 'Produziert einzelne erkennbare Wörter, um eine gewünschte Reaktion des Erwachsenen zu erhalten.'],
                            ['code' => 'SOZ-9', 'desc' => 'Zeigt deutliche Anzeichen für eine beginnende Herausbildung des Selbst (z.B. Spiegel, Pronomen).'],
                            ['code' => 'SOZ-10', 'desc' => 'Nimmt von sich aus an parallelem Spiel teil. Lässt dabei erkennen, dass es sich der Gegenwart anderer bewusst ist.'],
                            ['code' => 'SOZ-11', 'desc' => 'Produziert einzelne erkennbare Wörter, um eine erwünschte Reaktion von einem gleichaltrigen Kind zu erhalten.'],
                            ['code' => 'SOZ-12', 'desc' => 'Sucht in unterschiedlichen Umgebungen Kontakt mit einem vertrauten Erwachsenen.'],
                        ]
                    ],
                    [
                        'code' => 'II',
                        'name' => 'Stufe II',
                        'goal_description' => 'Erfolgreich auf die Umwelt reagieren. Sich erfolgreich an Aktivitäten beteiligen.',
                        'goals' => [
                            ['code' => 'SOZ-13', 'desc' => 'Beschäftigt sich von sich aus in verschiedenen Situationen mit Fantasie- und "So-tun-als-ob"-Spielen.'],
                            ['code' => 'SOZ-14', 'desc' => 'Wartet ohne körperliche Steuerungshilfe durch den Erwachsenen.'],
                            ['code' => 'SOZ-15', 'desc' => 'Zeigt Ansätze, einen angemessenen sozialen Kontakt zu einem anderen Kind aufzunehmen.'],
                            ['code' => 'SOZ-16', 'desc' => 'Beteiligt sich an einer verbal gesteuerten Aktivität, die Teilen erfordert.'],
                            ['code' => 'SOZ-17', 'desc' => 'Beteiligt sich erfolgreich an interaktivem Spiel mit einem anderen Kind.'],
                            ['code' => 'SOZ-18', 'desc' => 'Kooperiert selbstständig mit einem anderen Kind in strukturierten Aktivitäten und Spiel.'],
                        ]
                    ],
                    [
                        'code' => 'III',
                        'name' => 'Stufe III',
                        'goal_description' => 'Erwerben von Fähigkeiten zur erfolgreichen Teilnahme in Gruppen. Gruppenaktivitäten als befriedigend erleben.',
                        'goals' => [
                            ['code' => 'SOZ-19', 'desc' => 'Teilt von sich aus Materialien und wechselt sich mit anderen ab ohne verbalen Hinweis durch Erwachsene.'],
                            ['code' => 'SOZ-20', 'desc' => 'Ahmt von sich aus angemessenes Verhalten eines anderen Kindes nach.'],
                            ['code' => 'SOZ-21', 'desc' => 'Bezeichnet einfache soziale Situationen mit wertenden Aussagen (richtig/falsch, fair/unfair).'],
                            ['code' => 'SOZ-22', 'desc' => 'Leitet eine Gruppenaktivität oder demonstriert eine Aktivität für die Gruppe.'],
                            ['code' => 'SOZ-23', 'desc' => 'Nimmt an einer Aktivität teil, die ein gleichaltriges Kind vorgeschlagen hat, ohne unangemessene Reaktion.'],
                            ['code' => 'SOZ-24', 'desc' => 'Beschreibt eigene Erfahrungen in der Reihenfolge, in der sie sich ereignet haben.'],
                            ['code' => 'SOZ-25', 'desc' => 'Lässt beginnende Freundschaft erkennen durch Vorliebe für ein bestimmtes Kind.'],
                            ['code' => 'SOZ-26', 'desc' => 'Sucht von sich aus Hilfe oder Lob durch ein anderes Kind.'],
                            ['code' => 'SOZ-27', 'desc' => 'Hilft anderen von sich aus bei der Einhaltung von Gruppenregeln.'],
                        ]
                    ],
                    [
                        'code' => 'IV',
                        'name' => 'Stufe IV',
                        'goal_description' => 'Sich einbringen in Gruppenprozesse. Nimmt von sich aus und erfolgreich als Gruppenmitglied an Aktivitäten teil.',
                        'goals' => [
                            ['code' => 'SOZ-28', 'desc' => 'Identifiziert sich mit erwachsenen Führungspersonen, Vorbildern oder Persönlichkeiten des öffentlichen Lebens.'],
                            ['code' => 'SOZ-29', 'desc' => 'Beschreibt soziale Gruppenerfahrungen in der Reihenfolge, in der sie sich ereignet haben.'],
                            ['code' => 'SOZ-30', 'desc' => 'Schlägt von sich aus eine geeignete Gruppenaktivität vor und richtet den Vorschlag direkt an die Gruppe.'],
                            ['code' => 'SOZ-31', 'desc' => 'Lässt erkennen, dass es sich bewusst ist, wie sich die eigenen sozialen Handlungen von denen anderer unterscheiden.'],
                            ['code' => 'SOZ-32', 'desc' => 'Hört und respektiert die Vorstellungen, Gedanken und Meinungen anderer.'],
                            ['code' => 'SOZ-33', 'desc' => 'Bekundet offen sein Interesse an der Meinung Gleichaltriger über die eigene Person.'],
                            ['code' => 'SOZ-34', 'desc' => 'Reagiert in kritischen Situationen auf Probleme mit konstruktiven Lösungsvorschlägen.'],
                            ['code' => 'SOZ-35', 'desc' => 'Erkennt und unterscheidet gegensätzliche Werte in sozialen Situationen.'],
                            ['code' => 'SOZ-36', 'desc' => 'Zieht Schlussfolgerungen aus sozialen Situationen.'],
                        ]
                    ],
                    [
                        'code' => 'V',
                        'name' => 'Stufe V',
                        'goal_description' => 'Anwenden von individuellen und gruppenbezogenen Fähigkeiten in neuen Situationen. Beginnt und pflegt selbständig dauerhafte Beziehungen.',
                        'goals' => [
                            ['code' => 'SOZ-37', 'desc' => 'Lässt erkennen, dass er persönliche Situationen, Gefühle und Sichtweisen anderer versteht und achtet (Empathie).'],
                            ['code' => 'SOZ-38', 'desc' => 'Interagiert erfolgreich mit anderen in unterschiedlichen sozialen Rollen.'],
                            ['code' => 'SOZ-39', 'desc' => 'Trifft in sozialen Situationen persönliche Entscheidungen aufgrund eigener Wertvorstellungen und Prinzipien.'],
                            ['code' => 'SOZ-40', 'desc' => 'Lässt realistisches Verständnis und Einschätzung des eigenen Selbst erkennen.'],
                            ['code' => 'SOZ-41', 'desc' => 'Zeigt die Fähigkeit, dauerhafte und tragfähige Beziehungen mit Einzelnen und in der Gruppe aufzubauen und zu erhalten.'],
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Kognition',
                'description' => 'Bereichsziel: Kognition',
                'sort_order' => 4,
                'stages' => [
                    [
                        'code' => 'I',
                        'name' => 'Stufe I',
                        'goal_description' => 'Mit Freude auf die Umwelt reagieren. Auf die Umgebung reagieren mit gezielten Körperbewegungen und elementaren mentalen Verarbeitungsprozessen.',
                        'goals' => [
                            ['code' => 'KOG-1', 'desc' => 'Reagiert auf sensorischen Reiz mit Zuwendung zur Reizquelle, entweder durch körperliche Reaktion oder durch Hinsehen.'],
                            ['code' => 'KOG-2', 'desc' => 'Reagiert auf einen Reiz mit kurzzeitig anhaltender Aufmerksamkeit.'],
                            ['code' => 'KOG-3', 'desc' => 'Zeigt Kurzzeitgedächtnis durch Körperbewegung und Lautäußerung bei spontanem Wiedererkennen von Personen oder Objekten.'],
                            ['code' => 'KOG-4', 'desc' => 'Reagiert auf komplexe Umgebungsreize und verbale Impulse mit motorischer Handlung.'],
                            ['code' => 'KOG-5', 'desc' => 'Imitiert von sich aus einfache, vertraute Handlungen des Erwachsenen.'],
                            ['code' => 'KOG-6', 'desc' => 'Zeigt rudimentäre fein- und grobmotorische Fähigkeiten auf dem Niveau eines Kindes von 18 Monaten.'],
                            ['code' => 'KOG-7', 'desc' => 'Lässt Verständnis von Bezeichnungen für vertraute Objekte erkennen.'],
                            ['code' => 'KOG-8', 'desc' => 'Reagiert verbal auf Fragen oder Aufforderungen von Erwachsenen mit erkennbaren Wort-Annäherungen.'],
                            ['code' => 'KOG-9', 'desc' => 'Verwendet von sich aus erkennbare, relevante Wort-Annäherungen (oder Wörter), um zu beschreiben oder zu fragen.'],
                            ['code' => 'KOG-10', 'desc' => 'Passt ein Objekt in eine dafür passende Lücke ein.'],
                            ['code' => 'KOG-11', 'desc' => 'Identifiziert eigene Körperteile.'],
                            ['code' => 'KOG-12', 'desc' => 'Erkennt einfache Details in Bildern durch Gesten oder Wörter.'],
                            ['code' => 'KOG-13', 'desc' => 'Ordnet zwei Sorten von Objekten mit minimal unterschiedlichen Merkmalen einander zu.'],
                            ['code' => 'KOG-14', 'desc' => 'Äußert einzelne, erkennbare Wörter, um auf einfachen Abbildungen vertraute Dinge, Tiere oder Menschen zu bezeichnen.'],
                        ]
                    ],
                    [
                        'code' => 'II',
                        'name' => 'Stufe II',
                        'goal_description' => 'Erfolgreich auf die Umwelt reagieren. Beteiligung an Aktivitäten, die Fähigkeiten der Selbsthilfe, Koordination und Sprache erfordern.',
                        'goals' => [
                            ['code' => 'KOG-15', 'desc' => 'Erkennt Gebrauchswert vertrauter Gegenstände in entsprechenden "So-tun-als-ob"-Spielen oder durch Zeigen.'],
                            ['code' => 'KOG-16', 'desc' => 'Führt zwei einfache motorische Aktivitäten aus, die Körperkoordination auf dem Niveau eines 3-jährigen Kindes erfordern.'],
                            ['code' => 'KOG-17', 'desc' => 'Ordnet zwei identische Bilder einander zu, wenn zwei gleiche und ein unterschiedliches Bild gezeigt werden.'],
                            ['code' => 'KOG-18', 'desc' => 'Führt mindestens zwei feinmotorische Aktivitäten aus, die dem Entwicklungsniveau eines 3-jährigen Kindes entsprechen.'],
                            ['code' => 'KOG-19', 'desc' => 'Erkennt dasjenige Objekt, das sich von den anderen unterscheidet.'],
                            ['code' => 'KOG-20', 'desc' => 'Versteht mindestens drei einfache Gegenteile (z.B. hoch/runter, groß/klein).'],
                            ['code' => 'KOG-21', 'desc' => 'Gebraucht Kategorien beim Zuordnen einfacher Bilder mit ähnlichen Charakteristika.'],
                            ['code' => 'KOG-22', 'desc' => 'Zählt bis 4 und wendet dabei 1-zu-1 Zuordnung an.'],
                            ['code' => 'KOG-23', 'desc' => 'Identifiziert vier Farben und drei Formen durch Benennen oder Zeigen.'],
                            ['code' => 'KOG-24', 'desc' => 'Gibt korrekte Antworten bei Zuordnung gleicher Bilder und Erkennen des unterschiedlichen Bildes (Alternation).'],
                            ['code' => 'KOG-25', 'desc' => 'Zählt mit 1-zu-1 Zuordnung bis 10.'],
                            ['code' => 'KOG-26', 'desc' => 'Führt mindestens zwei Aktivitäten aus, die eine Auge-Hand-Koordination auf dem Niveau eines 5-jährigen Kindes erfordern.'],
                            ['code' => 'KOG-27', 'desc' => 'Unterscheidet zwischen Ziffern, Zeichen und Großbuchstaben.'],
                            ['code' => 'KOG-28', 'desc' => 'Führt zwei motorische Aktivitäten aus, die Körperkoordination auf dem Niveau eines 5-jährigen Kindes erfordern.'],
                            ['code' => 'KOG-29', 'desc' => 'Erkennt die Anzahl von Objekten in einer Menge bis zu 5, ohne zu zählen.'],
                            ['code' => 'KOG-30', 'desc' => 'Gibt Auswendiggelerntes wieder auf dem Niveau eines 5-jährigen Kindes.'],
                            ['code' => 'KOG-31', 'desc' => 'Ordnet in richtiger Reihenfolge drei einfache Bilder an, die eine Geschichte wiedergeben.'],
                        ]
                    ],
                    [
                        'code' => 'III',
                        'name' => 'Stufe III',
                        'goal_description' => 'Erwerben von Fähigkeiten zur erfolgreichen Teilnahme in Gruppen. Beteiligt sich erfolgreich in einer Lerngruppe.',
                        'goals' => [
                            ['code' => 'KOG-32', 'desc' => 'Führt mindestens zwei Aktivitäten aus, die Auge-Hand-Koordination auf dem Niveau eines 6-jährigen Kindes erfordern.'],
                            ['code' => 'KOG-33', 'desc' => 'Führt mindestens zwei motorische Aktivitäten aus, die Körperkoordination auf dem Niveau eines 6-jährigen Kindes erfordern.'],
                            ['code' => 'KOG-34', 'desc' => 'Liest 50 Wörter des Grundwortschatzes.'],
                            ['code' => 'KOG-35', 'desc' => 'Erkennt und schreibt Zahlen, die Mengen bis 10 repräsentieren.'],
                            ['code' => 'KOG-36', 'desc' => 'Schreibt 50 Wörter des Grundwortschatzes nach Diktat oder aus dem Gedächtnis.'],
                            ['code' => 'KOG-37', 'desc' => 'Hört einer Geschichte auf Grundschulniveau zu und lässt Verständnis der Fakten und des Handlungsablaufes erkennen.'],
                            ['code' => 'KOG-38', 'desc' => 'Erklärt das Verhalten anderer.'],
                            ['code' => 'KOG-39', 'desc' => 'Liest einfache Sätze und lässt dabei Verständnis des Inhalts erkennen.'],
                            ['code' => 'KOG-40', 'desc' => 'Beherrscht alle numerischen Operationen mit Addition und Subtraktion im Zahlenraum bis 9.'],
                            ['code' => 'KOG-41', 'desc' => 'Erkennt Unstimmigkeiten in einfachen Situationen.'],
                            ['code' => 'KOG-42', 'desc' => 'Schreibt einfache Sätze als Antworten auf Fragen, die der Erwachsene zu einer Geschichte stellt.'],
                            ['code' => 'KOG-43', 'desc' => 'Zeigt mindestens zwei motorische Kompetenzen oder Spielaktivitäten wie Kinder im Grundschulalter.'],
                            ['code' => 'KOG-44', 'desc' => 'Formuliert und schreibt einfache Sätze.'],
                            ['code' => 'KOG-45', 'desc' => 'Wendet grundlegende numerische Konzepte an, die Addition, Subtraktion, Zeit und Geld beinhalten.'],
                            ['code' => 'KOG-46', 'desc' => 'Liest und erklärt quantitative Begriffe für Maßeinheiten von Zeit, Länge und Flüssigkeitsvolumen.'],
                            ['code' => 'KOG-47', 'desc' => 'Liest kurze Geschichten oder Artikel und erzählt anderen von den Personen und Ereignissen.'],
                            ['code' => 'KOG-48', 'desc' => 'Führt grundlegende Rechenoperationen durch (Stellenwert, Übertrag, Multiplikation, Größenanordnung).'],
                        ]
                    ],
                    [
                        'code' => 'IV',
                        'name' => 'Stufe IV',
                        'goal_description' => 'Sich einbringen in Gruppenprozesse. Gebraucht kognitive und schulische Fähigkeiten, um sich erfolgreich an sozialen Gruppenerfahrungen zu beteiligen.',
                        'goals' => [
                            ['code' => 'KOG-49', 'desc' => 'Schreibt, um Informationen, Ereignisse oder Gefühle mitzuteilen.'],
                            ['code' => 'KOG-50', 'desc' => 'Rechnet Multiplikations- und Divisionsaufgaben im Zahlenraum bis 100.'],
                            ['code' => 'KOG-51', 'desc' => 'Liest aus Freude am Lesen und zum persönlichen Informationsgewinn.'],
                            ['code' => 'KOG-52', 'desc' => 'Berechnet Wert für Geldmengen bis zu 10 Euro bzw. 1000 Cent.'],
                            ['code' => 'KOG-53', 'desc' => 'Beschreibt fiktive Charaktere aus Büchern, Fernsehen oder Filmen und erklärt deren Motive.'],
                            ['code' => 'KOG-54', 'desc' => 'Verwendet grammatische Regeln beim Schreiben von Sätzen, Abschnitten, kurzen Aufsätzen.'],
                            ['code' => 'KOG-55', 'desc' => 'Erkennt und unterscheidet gegensätzliche Werte in sozialen Situationen.'],
                            ['code' => 'KOG-56', 'desc' => 'Gebraucht Maßeinheiten und andere quantitative Begriffe, um einfache logische Probleme zu lösen.'],
                        ]
                    ],
                    [
                        'code' => 'V',
                        'name' => 'Stufe V',
                        'goal_description' => 'Anwenden von individuellen und gruppenbezogenen Fähigkeiten in neuen Situationen. Setzt erfolgreich kognitive Fähigkeiten zur Bereicherung persönlicher Erfahrungen ein.',
                        'goals' => [
                            ['code' => 'KOG-57', 'desc' => 'Sucht die Meinung anderer zu aktuellen Problemen zu erfahren.'],
                            ['code' => 'KOG-58', 'desc' => 'Unterscheidet in Texten zwischen Fakten und Meinungen.'],
                            ['code' => 'KOG-59', 'desc' => 'Erkennt unlogisches und unstimmiges Verhalten bei anderen in sozialen Situationen.'],
                            ['code' => 'KOG-60', 'desc' => 'Löst Textaufgaben, die Bruchrechnung, Dezimalrechnung und das Rechnen mit negativen Zahlen erfordern.'],
                            ['code' => 'KOG-61', 'desc' => 'Löst persönliche Probleme anhand von Einsicht, Analyse und Generalisierung.'],
                            ['code' => 'KOG-62', 'desc' => 'Gebraucht selbständig kognitive Verfahren in der Rolle als Bürger/in und Arbeitnehmer/in.'],
                        ]
                    ]
                ]
            ]
        ];

        DB::transaction(function () use ($areas) {
            foreach ($areas as $areaData) {
                // Bereich erstellen
                $areaId = DB::table('diagnostic_areas')->insertGetId([
                    'name' => $areaData['name'],
                    'description' => $areaData['description'],
                    'slug' => Str::slug($areaData['name']),
                    'sort_order' => $areaData['sort_order'],
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $stageOrder = 1;
                foreach ($areaData['stages'] as $stageData) {
                    // Stufe erstellen
                    $stageId = DB::table('diagnostic_stages')->insertGetId([
                        'diagnostic_area_id' => $areaId,
                        'name' => $stageData['name'],
                        'code' => $stageData['code'],
                        'goal_description' => $stageData['goal_description'],
                        'sort_order' => $stageOrder++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $goalOrder = 1;
                    foreach ($stageData['goals'] as $goalData) {
                        // Ziel erstellen
                        DB::table('diagnostic_goals')->insert([
                            'diagnostic_stage_id' => $stageId,
                            'code' => $goalData['code'],
                            'description' => $goalData['desc'],
                            'sort_order' => $goalOrder++,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        });

        $this->command->info('✅ Diagnostic Seeder erfolgreich ausgeführt!');
        $this->command->info('Erstellt:');
        $this->command->info('  - 4 Bereiche (Verhalten, Kommunikation, Sozialisation, Kognition)');
        $this->command->info('  - Je 5 Stufen pro Bereich (I-V)');
        $this->command->info('  - Insgesamt ' . DB::table('diagnostic_goals')->count() . ' Ziele');
    }
}
