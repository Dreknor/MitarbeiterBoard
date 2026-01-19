<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration {
    /**
     * Fügt einen Wiki-Eintrag für das Raum-Planungstool hinzu / aktualisiert ihn.
     */
    public function up(): void
    {
        $title = 'Raum-Planungstool — Anleitung';

        $text = <<<'HTML'
<h2>Raum-Planungstool – Anleitung für Endanwender</h2>
<p>Dieses Handbuch erklärt die tägliche Nutzung des Raum-Planungstools: Räume finden, Buchungen anlegen, ändern und einen Kalender-Feed abonnieren.</p>

<h3>1. Überblick</h3>
<ul>
  <li><strong>Räume-Liste</strong>: Alle angelegten Räume mit Name, Nummer und aktuellem Status (frei / belegt).</li>
  <li><strong>Freie Räume Karte</strong>: Dashboard-Karte zeigt spontan nutzbare Räume an.</li>
  <li><strong>Wochenansicht eines Raums</strong>: Zeitraster (15-Minuten-Schritte) mit Buchungen nach Tagen.</li>
  <li><strong>Buchungstypen</strong>: Wiederkehrende Buchungen (Serien) und Einzeltermine.</li>
  <li><strong>A-/B‑Woche</strong>: Optionales Wochenmodell; Buchungen können nur in einer Woche oder in jeder Woche gelten.</li>
  <li><strong>Kalender-Feed</strong>: Raumbelegung extern abonnieren (Outlook, Google Calendar).</li>
</ul>

<h3>2. Voraussetzungen & Rechte</h3>
<ul>
  <li>Zum Anzeigen der Räume wird die Berechtigung <code>view roomBooking</code> benötigt.</li>
  <li>Für das Anlegen / Bearbeiten von Räumen oder Buchungen sind zusätzliche Rechte (<code>manage rooms</code>) erforderlich.</li>
  <li>Nicht buchbare Räume werden mit einem roten Hinweis „Raum nicht buchbar“ angezeigt.</li>
</ul>

<h3>3. Räume ansehen</h3>
<ul>
  <li>Menüpunkt „Räume“ öffnen – Tabelle zeigt Status: <span class="text-success">frei</span> oder <span class="text-danger">belegt</span>.</li>
  <li>Über die Schaltfläche <i class="fa fa-eye"></i> den Detailplan eines Raums öffnen.</li>
  <li>Freie Räume Karte (Dashboard): zeigt eine Liste verfügbarer Räume und deren nächste Belegung.</li>
</ul>

<h3>4. Wochenansicht & Navigation</h3>
<ul>
  <li>Oben: Pfeile „Vorherige Woche“ / „Nächste Woche“ sowie Anzeige der Kalenderwoche und des Datumsbereichs.</li>
  <li>Bei Nutzung des A/B‑Modells erscheinen Umschalt-Buttons „A‑Woche“ / „B‑Woche“.</li>
  <li>Zeitraster: kleinste Einheit 15 Minuten zwischen konfiguriertem Start und Ende (siehe Systemkonfiguration).</li>
  <li>Farbgebung: Buchungen sind zusammenhängend mit <code>rowspan</code> dargestellt (Block über mehrere Zeitslots).</li>
</ul>

<h3>5. Neue Buchung – Wiederkehrende Buchung</h3>
<p>Für regelmäßig stattfindende Termine (z. B. Unterricht, wöchentliches Meeting).</p>
<ol>
  <li>„Neue Reservierung“ anklicken → Tab „Wiederkehrende Buchung“.</li>
  <li><strong>Wochentag(e)</strong> wählen (Mehrfachauswahl möglich).</li>
  <li><strong>Start / Ende</strong> festlegen (Zeiten liegen im erlaubten Buchungsfenster).</li>
  <li><strong>Woche</strong>: A, B oder „Jede“ (leer) für beide Wochen.</li>
  <li><strong>Bezeichnung</strong>: kurzer, verständlicher Name (z. B. „Mathe 7B“).</li>
  <li>Speichern – das System prüft automatisch auf Kollisionen.</li>
</ol>
<p><em>Hinweis:</em> Mehrere Wochentage erzeugen getrennte Buchungen – Kollisionen führen zu einer Warnung und verhindern das Speichern.</p>

<h3>6. Neue Buchung – Einzeltermin</h3>
<p>Für einmalige Reservierungen (z. B. Klausur, Projekttag).</p>
<ol>
  <li>Tab „Einzeltermin“ wählen.</li>
  <li><strong>Datum</strong> setzen (Kalenderfeld).</li>
  <li><strong>Start / Ende</strong> eingeben.</li>
  <li><strong>Bezeichnung</strong> ergänzen.</li>
  <li>Speichern – Kollisionen werden geprüft (gleichzeitige Nutzung verhindert).</li>
</ol>
<p>Einzeltermine erhalten eine Badge „Einzeltermin“ im Plan.</p>

<h3>7. Kollisionen & Fehlermeldungen</h3>
<ul>
  <li>Bei Überschneidungen erscheint eine Warnung „Raum ist bereits belegt“ – Buchung wird nicht angelegt.</li>
  <li>Bei Serienbuchungen wird jeder ausgewählte Wochentag einzeln geprüft.</li>
  <li>Kollisionen berücksichtigen Start und Ende inkl. Teilüberlappungen.</li>
</ul>

<h3>8. Buchung bearbeiten oder löschen</h3>
<ul>
  <li>Im Wochenplan auf einen Buchungsblock klicken → Bearbeitungsseite.</li>
  <li><strong>Serienbuchung ändern</strong>: Start/Ende, Woche oder Bezeichnung anpassen. Der Wochentag bleibt gleich.</li>
  <li><strong>Einzeltermin ändern</strong>: Datum und Zeiten aktualisieren.</li>
  <li><strong>Löschen</strong>: Buchung entfernen (Achtung: keine Rückfrage bei fehlender Sicherheitsabfrage).</li>
  <li>Nach Änderungen werden Cache-Daten für den Raum invalidiert (aktualisierte Anzeige).</li>
</ul>

<h3>9. Nicht buchbare Räume</h3>
<ul>
  <li>Räume können als „nicht buchbar“ markiert sein – keine neuen Reservierungen möglich.</li>
  <li>Anzeigen weiterhin möglich; vorhandene historische Buchungen bleiben sichtbar.</li>
</ul>

<h3>10. Kalender-Feed abonnieren</h3>
<ul>
  <li>Im Raum-Detail unten erscheint (falls generiert) eine Feed-URL.</li>
  <li>URL in externen Kalender importieren („Kalender abonnieren“ / „Neuen Kalender per URL hinzufügen“).</li>
  <li>Änderungen erscheinen nach Synchronisationsintervall des externen Dienstes.</li>
  <li>Widerruf des Tokens macht bestehende Abonnements ungültig.</li>
</ul>

<h3>11. Farb-Legende</h3>
<ul>
  <li><span class="badge bg-gradient-radial-blue text-white">Wiederkehrend – zukünftig</span></li>
  <li><span class="badge bg-gradient-x-light-blue text-white">Wiederkehrend – vergangen</span></li>
  <li><span class="badge bg-gradient-x-teal text-white">Einzeltermin – zukünftig</span></li>
  <li><span class="badge bg-gradient-x-teal-light text-white">Einzeltermin – vergangen</span></li>
</ul>

<h3>12. Tipps & Best Practices</h3>
<ul>
  <li>Serien nur anlegen, wenn wirklich regelmäßig – sonst Einzeltermine nutzen.</li>
  <li>Kurze, eindeutige Bezeichnungen erleichtern die Übersicht.</li>
  <li>Kollisionen vermeiden: erst prüfen, ob Slot frei (Status „frei“ oder keine Belegung im Raster).</li>
  <li>Feed nutzen statt manueller Pflege externer Kalender.</li>
</ul>

<h3>13. Häufige Fragen (FAQ)</h3>
<ul>
  <li><strong>Buchung erscheint nicht?</strong> Seite aktualisieren – evtl. Cache / Berechtigung prüfen.</li>
  <li><strong>Falsche Woche?</strong> Prüfen, ob A/B‑Woche gesetzt wurde.</li>
  <li><strong>Block startet zu spät/früh?</strong> Zeiten außerhalb des konfigurierten Bereichs werden abgelehnt – Start/Ende prüfen.</li>
  <li><strong>Feed zeigt alte Daten?</strong> Externe Kalender synchronisieren verzögert – Wartezeit einplanen.</li>
</ul>

HTML;

        if (!Schema::hasTable('wiki_sites')) {
            return; // Tabelle fehlt – kein Einfügen möglich.
        }

        DB::table('wiki_sites')->updateOrInsert(
            ['title' => $title],
            [
                'author_id' => 1, // System / Standard-Benutzer
                'text' => $text,
                'previous_version' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Entfernt den Eintrag (optional).
     */
    public function down(): void
    {
        $title = 'Raum-Planungstool — Anleitung';
        if (!Schema::hasTable('wiki_sites')) {
            return;
        }
        DB::table('wiki_sites')->where('title', $title)->delete();
    }
};
