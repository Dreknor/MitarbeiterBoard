<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class InsertPaedDiaryWikiHandbook extends Migration
{
    /**
     * Run the migrations.
     * Fügt einen Wiki-Eintrag (Handbuch) für das Pädagogische Tagebuch hinzu.
     * Wenn ein Eintrag mit gleichem Titel existiert, wird er aktualisiert.
     *
     * @return void
     */
    public function up()
    {
        $title = 'Pädagogisches Tagebuch — Handbuch';

        $text = <<<'HTML'
<h2>Pädagogisches Tagebuch — Kurzanleitung / Handbuch</h2>

<p>Dieses Dokument gibt einen kompakten Überblick über den Funktionsumfang des pädagogischen Tagebuchs und erklärt die wichtigsten Arbeitsabläufe.</p>

<h3>1. Übersicht</h3>
<ul>
  <li>Wochenansicht (Montag–Freitag) mit einer Zeile pro Schüler und einer Spalte pro Tag.</li>
  <li>Notizen (Einträge) für einzelne Schüler an bestimmten Tagen.</li>
  <li>Zusätzliche, frei definierbare Spalten (Columns) pro Klasse zur Erfassung von Zusatzinformationen (z. B. Anwesenheit, Verhalten).</li>
  <li>Aufgaben (Tasks) und Termine (Appointments) mit Zuweisung an Klassen, Gruppen oder einzelne Schüler.</li>
  <li>Gruppenmodus: mehrere Klassen können zu einer Gruppe gekoppelt werden (Klassenkopplung).</li>
  <li>Exportfunktionen für Excel/CSV.</li>
</ul>

<h3>2. Begriffe</h3>
<ul>
  <li><strong>Eintrag / Notiz</strong>: Ein Tagebucheintrag für einen oder mehrere Schüler an einem Datum.</li>
  <li><strong>Spalte</strong>: Zusätzliche, benutzerdefinierte Felder für Schüler/Datum (boolean, text, number).</li>
  <li><strong>Gruppe</strong>: Sammlung mehrerer Klassen zur parallelen Ansicht und Bearbeitung.</li>
  <li><strong>Aufgabe</strong>: Offene To‑Do‑Einträge, die hervorgehoben und abgeschlossen werden können.</li>
  <li><strong>Termin</strong>: Einzel- oder wiederkehrender Termin, sichtbar in Kopf- und Schülerzellen.</li>
</ul>

<h3>3. Tagebuch bedienen</h3>
<ul>
  <li><strong>Klasse auswählen</strong>: Oben links die Klasse wählen. Bei Klassenkopplung die Gruppe auswählen.</li>
  <li><strong>Woche navigieren</strong>: ← / Heute / → für Wochenwechsel.</li>
  <li><strong>Neue Notiz</strong>: Zelle anklicken oder „Neue Notiz“ öffnen, Datum wählen, Schüler markieren und Text eingeben.</li>
  <li><strong>Notiz bearbeiten / löschen</strong>: Notiz klicken → bearbeiten oder löschen.</li>
  <li><strong>Offene Notizen</strong>: Nicht abgeschlossene Einträge erscheinen in allen folgenden Wochen; beim Abschließen werden Einträge bei Bedarf automatisch für die dazwischenliegenden Tage geklont.</li>
</ul>

<h3>4. Spaltenverwaltung</h3>
<ul>
  <li><strong>Spalten erstellen</strong>: Über „Spalten verwalten“ neue Spalten für eine Klasse hinzufügen. Typen: boolean, text, number.</li>
  <li><strong>Kategorien</strong>: Spalten können kategorisiert werden, um die Anzeige pro Zelle zu gruppieren.</li>
  <li><strong>Deaktivieren</strong>: Spalte kann ab einer bestimmten Woche deaktiviert werden (Werte dieser und späterer Wochen werden entfernt). Reaktivierung möglich.</li>
  <li><strong>Inline-Bearbeitung</strong>: Werte werden direkt in der Tabelle eingegeben; Booleans per Klick‑Button.</li>
</ul>

<h3>5. Gruppenmodus</h3>
<ul>
  <li>Mehrere Klassen werden zu einer Ansicht kombiniert; Schüler werden nach Klassen gruppiert angezeigt.</li>
  <li>Manche Funktionen (z. B. Spaltenverwaltung) stehen nur im Klassenmodus zur Verfügung.</li>
  <li>Beim Erstellen von Einträgen/Terminen kann die Aktion auf alle Klassen der Gruppe angewendet werden.</li>
</ul>

<h3>6. Termine &amp; Aufgaben</h3>
<ul>
  <li><strong>Termine</strong>: Können Klassen, Gruppen oder einzelne Schüler betreffen; wiederkehrende Termine möglich.</li>
  <li><strong>Aufgaben</strong>: Einzelne Schüler zuweisen. Offene Aufgaben erscheinen in einem separaten Panel und können als erledigt markiert werden.</li>
</ul>

<h3>7. Export</h3>
<p>Export als Excel/CSV pro Klasse oder Gruppe für die gewählte Woche.</p>

<h3>8. Rechte &amp; Sicherheit</h3>
<ul>
  <li>Zugriff auf Klassen, Gruppen, Spalten und Aktionen erfolgt anhand der Benutzerberechtigungen.</li>
  <li>Änderungen an Stufen (grading stages) und an Terminen sind nur für berechtigte Nutzer möglich.</li>
</ul>

<h3>9. Tipps</h3>
<ul>
  <li>Bei gruppenweitem Arbeiten zuerst eine Gruppe anlegen und testen.</li>
  <li>Spaltenkategorien nutzen, um die Zellen übersichtlich zu halten.</li>
  <li>Offene Notizen regelmäßig abschließen, wenn sie auf mehrere Tage gelten sollen.</li>
</ul>

<hr>
<p>Bei Bedarf kann dieser Eintrag erweitert werden (z. B. Screenshots, detaillierte Workflows oder häufige Probleme &amp; Lösungen).</p>
HTML;

        // Sicherstellen, dass die Tabelle existiert
        if (!Schema::hasTable('wiki_sites')) {
            // Tabelle nicht vorhanden – Migration kann nicht einfügen. Abbruch ohne Fehler.
            return;
        }

        DB::table('wiki_sites')->updateOrInsert(
            ['title' => $title],
            [
                'author_id' => 1, // Fallback auf System-Benutzer (falls vorhanden)
                'text' => $text,
                'previous_version' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     * Entfernt den Wiki-Eintrag anhand des Titel.
     *
     * @return void
     */
    public function down()
    {
        $title = 'Pädagogisches Tagebuch — Handbuch';
        if (!Schema::hasTable('wiki_sites')) {
            return;
        }
        DB::table('wiki_sites')->where('title', $title)->delete();
    }
}

