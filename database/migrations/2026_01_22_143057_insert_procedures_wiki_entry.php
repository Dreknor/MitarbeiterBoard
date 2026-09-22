<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration {
    /**
     * Fügt einen Wiki-Eintrag für Prozesse/Prozeduren hinzu / aktualisiert ihn.
     */
    public function up(): void
    {
        $title = 'Prozesse & Prozeduren — Anleitung';

        $text = <<<'HTML'
<h2>Prozesse & Prozeduren – Vollständige Anleitung</h2>
<p>Dieses Handbuch erklärt das Prozess-Management-System: Was sind Prozesse, wie nutze ich sie als Administrator oder als zugewiesener Mitarbeiter, und wie verwalte ich Vorlagen und Positionen.</p>

<h3>1. Was sind Prozesse?</h3>
<p>Prozesse (auch Prozeduren genannt) sind strukturierte Arbeitsabläufe mit mehreren Schritten, die in einer bestimmten Reihenfolge abgearbeitet werden müssen. Jeder Schritt wird einer oder mehreren Personen zugewiesen, die für die Erledigung verantwortlich sind.</p>

<h4>Beispiele für Prozesse:</h4>
<ul>
  <li>Einarbeitung neuer Mitarbeiter</li>
  <li>Schuljahreswechsel-Vorbereitungen</li>
  <li>Inventur-Durchführung</li>
  <li>Projektabschluss-Checkliste</li>
  <li>Urlaubsantrag-Prüfung</li>
</ul>

<h3>2. Berechtigungssystem</h3>
<p>Das System unterscheidet zwei Hauptrollen mit unterschiedlichen Berechtigungen:</p>

<h4>2.1 Administrator (Berechtigung: <code>manage procedures</code>)</h4>
<p><strong>Vollzugriff auf alle Funktionen:</strong></p>
<ul>
  <li><i class="fas fa-check text-success"></i> Alle Prozesse sehen und bearbeiten</li>
  <li><i class="fas fa-check text-success"></i> Vorlagen erstellen, bearbeiten und löschen</li>
  <li><i class="fas fa-check text-success"></i> Positionen erstellen und verwalten</li>
  <li><i class="fas fa-check text-success"></i> Wiederkehrende Prozesse einrichten</li>
  <li><i class="fas fa-check text-success"></i> Prozesse starten und beenden</li>
  <li><i class="fas fa-check text-success"></i> Prozess-Namen und Beschreibung nachträglich ändern</li>
  <li><i class="fas fa-check text-success"></i> Schritte erstellen, bearbeiten und löschen</li>
  <li><i class="fas fa-check text-success"></i> Personen zu Schritten zuweisen</li>
  <li><i class="fas fa-check text-success"></i> Alle Schritte als erledigt markieren</li>
</ul>

<h4>2.2 Normaler Mitarbeiter (Berechtigung: <code>view assigned procedures</code> + <code>complete own procedure steps</code>)</h4>
<p><strong>Eingeschränkter Zugriff – nur eigene Aufgaben:</strong></p>
<ul>
  <li><i class="fas fa-check text-success"></i> Nur zugewiesene Prozesse sehen</li>
  <li><i class="fas fa-check text-success"></i> Prozess-Details ansehen</li>
  <li><i class="fas fa-check text-success"></i> Eigene Schritte als erledigt markieren</li>
  <li><i class="fas fa-times text-danger"></i> Prozesse NICHT bearbeiten oder ändern</li>
  <li><i class="fas fa-times text-danger"></i> Keine Vorlagen oder Positionen verwalten</li>
</ul>

<h4>2.3 Zusätzliche Berechtigung: <code>delete procedures</code></h4>
<p>Ermöglicht das Löschen von Prozessen – unabhängig von anderen Rechten.</p>

<h3>3. Navigation & Menüstruktur</h3>

<h4>Für Administratoren:</h4>
<ul>
  <li><strong>Aktive Prozesse</strong>: Übersicht aller laufenden Prozesse</li>
  <li><strong>Vorlagen</strong>: Verwaltung von Prozessvorlagen</li>
  <li><strong>Positionen</strong>: Verwaltung von Rollen/Positionen</li>
  <li><strong>Wiederkehrende Prozesse</strong>: Automatisch wiederkehrende Prozesse</li>
</ul>

<h4>Für normale Mitarbeiter:</h4>
<ul>
  <li><strong>Meine Prozesse</strong>: Nur zugewiesene Prozesse</li>
</ul>

<h3>4. Konzepte & Grundlagen</h3>

<h4>4.1 Vorlagen (Templates)</h4>
<ul>
  <li>Wiederverwendbare Prozess-Blueprints</li>
  <li>Enthalten vordefinierte Schritte mit Dauer und verantwortlichen Positionen</li>
  <li>Können mehrfach gestartet werden</li>
  <li>Änderungen an Vorlagen betreffen keine laufenden Prozesse</li>
</ul>

<h4>4.2 Positionen</h4>
<ul>
  <li>Rollen im System (z.B. "Schulleitung", "Hausmeister", "Sekretariat")</li>
  <li>Einer Position können mehrere Personen zugeordnet sein</li>
  <li>Schritte werden Positionen zugewiesen, nicht einzelnen Personen</li>
  <li>Personen mit dieser Position sehen automatisch die zugehörigen Prozesse</li>
</ul>

<h4>4.3 Schritte (Steps)</h4>
<ul>
  <li>Einzelne Aufgaben innerhalb eines Prozesses</li>
  <li>Haben eine Dauer in Tagen</li>
  <li>Können von anderen Schritten abhängen (Vorgänger-Schritt)</li>
  <li>Werden Positionen zugewiesen</li>
  <li>Können zusätzlich einzelnen Personen zugewiesen werden</li>
</ul>

<h4>4.4 Prozess-Status</h4>
<ul>
  <li><strong>Vorlage</strong>: Noch nicht gestartet (kein Startdatum)</li>
  <li><strong>Aktiv</strong>: Gestartet, aber noch nicht alle Schritte erledigt</li>
  <li><strong>Abgeschlossen</strong>: Alle Schritte erledigt oder manuell beendet</li>
</ul>

<h3>5. Arbeiten mit Prozessen (Administrator)</h3>

<h4>5.1 Vorlage erstellen</h4>
<ol>
  <li>Menü: <strong>Prozesse → Vorlagen</strong></li>
  <li>Klick auf <strong>"Vorlage anlegen"</strong></li>
  <li>Kategorie auswählen oder neu erstellen</li>
  <li>Namen und Beschreibung eingeben</li>
  <li>Speichern → zur Schritt-Bearbeitung</li>
</ol>

<h4>5.2 Schritte zu Vorlage hinzufügen</h4>
<ol>
  <li>In der Vorlage: Klick auf <strong>"+ Schritt erstellen"</strong></li>
  <li>Formular ausfüllen:
    <ul>
      <li><strong>Bezeichnung</strong>: Kurzer, prägnanter Name</li>
      <li><strong>Beschreibung</strong>: Detaillierte Anweisungen (optional)</li>
      <li><strong>Verantwortliche Position</strong>: Welche Rolle ist zuständig?</li>
      <li><strong>Dauer in Tagen</strong>: Wie lange dauert der Schritt?</li>
      <li><strong>Vorgänger-Schritt</strong>: Optionale Abhängigkeit</li>
    </ul>
  </li>
  <li>Speichern</li>
  <li>Weitere Schritte hinzufügen mit <strong>"+ ↓"</strong> (nachfolgend) oder <strong>"+ →"</strong> (parallel)</li>
</ol>

<h4>5.3 Prozess aus Vorlage starten</h4>
<ol>
  <li>Vorlage öffnen (über Vorlagen-Liste)</li>
  <li>Klick auf die Vorlage → Detail-Ansicht</li>
  <li>Formular ausfüllen:
    <ul>
      <li><strong>Bezeichnung des Prozesses</strong>: Individueller Name (z.B. "Einarbeitung Max Mustermann")</li>
      <li><strong>Prozess startet am</strong>: Startdatum wählen</li>
    </ul>
  </li>
  <li>Klick auf <strong>"starten"</strong></li>
  <li>System erstellt alle Schritte und berechnet Fristen automatisch</li>
  <li>Beteiligte Personen werden per E-Mail benachrichtigt</li>
</ol>

<h4>5.4 Laufenden Prozess bearbeiten</h4>
<p><strong>Titel und Beschreibung ändern:</strong></p>
<ol>
  <li>Prozess öffnen (über "Aktive Prozesse")</li>
  <li>Klick auf <strong><i class="fas fa-edit"></i></strong> neben dem Titel</li>
  <li>Name/Beschreibung im Formular ändern</li>
  <li>Klick auf <strong>"Speichern"</strong></li>
  <li>Klick auf <strong>"Abbrechen"</strong> verwirft Änderungen</li>
</ol>

<p><strong>Personen zu Schritten zuweisen:</strong></p>
<ol>
  <li>Im Prozess zum gewünschten Schritt navigieren</li>
  <li>Klick auf <strong>"Zuweisen"</strong> (Person hinzufügen)</li>
  <li>Person aus Liste wählen</li>
  <li>Person wird benachrichtigt</li>
</ol>

<p><strong>Person von Schritt entfernen:</strong></p>
<ul>
  <li>Klick auf <strong><i class="fas fa-user-minus"></i></strong> neben dem Namen</li>
</ul>

<h4>5.5 Schritte löschen</h4>
<ul>
  <li>Nur möglich wenn Schritt keine Nachfolger hat</li>
  <li>Nur möglich wenn Schritt noch nicht erledigt ist</li>
  <li>Klick auf <strong><i class="fas fa-trash"></i> Löschen</strong></li>
</ul>

<h4>5.6 Prozess manuell beenden</h4>
<ol>
  <li>Prozess öffnen</li>
  <li>Klick auf <strong><i class="far fa-times-circle"></i></strong> (Prozess beenden)</li>
  <li>Alle offenen Schritte werden automatisch als erledigt markiert</li>
</ol>

<h3>6. Arbeiten mit Prozessen (Mitarbeiter)</h3>

<h4>6.1 Zugewiesene Prozesse finden</h4>
<ul>
  <li>Menü: <strong>Meine Prozesse</strong></li>
  <li>Liste zeigt nur Prozesse mit eigenen Aufgaben</li>
  <li>Klick auf <strong><i class="fas fa-eye"></i></strong> zum Öffnen</li>
</ul>

<h4>6.2 Dashboard-Karte</h4>
<ul>
  <li>Zeigt offene Schritte auf der Startseite</li>
  <li>Direkt zum Prozess springen</li>
</ul>

<h4>6.3 Schritt als erledigt markieren</h4>
<ol>
  <li>Prozess öffnen</li>
  <li>Zum eigenen Schritt scrollen</li>
  <li>Prüfen: <span class="badge badge-info">Bis [Datum]</span> zeigt Frist</li>
  <li>Aufgabe erledigen</li>
  <li>Klick auf <strong>"Erledigt"</strong> <i class="fas fa-check"></i></li>
  <li>Nachfolgende Schritte werden automatisch aktiviert</li>
  <li>Zuständige Personen der Folgeschritte werden benachrichtigt</li>
</ol>

<h4>6.4 Überfällige Schritte</h4>
<ul>
  <li>Werden mit <span class="badge badge-danger">Überfällig</span> markiert</li>
  <li>Erinnerungs-E-Mails werden automatisch versendet</li>
</ul>

<h3>7. Positionen verwalten (Administrator)</h3>

<h4>7.1 Position erstellen</h4>
<ol>
  <li>Menü: <strong>Prozesse → Positionen</strong></li>
  <li>Klick auf <strong>"neue Position erstellen"</strong></li>
  <li>Namen eingeben (z.B. "Schulleitung", "IT-Verantwortlicher")</li>
  <li>Speichern</li>
</ol>

<h4>7.2 Personen zu Position zuweisen</h4>
<ol>
  <li>In Positionen-Liste: Position finden</li>
  <li>Klick auf <strong>"Person hinzufügen"</strong></li>
  <li>Person aus Dropdown wählen</li>
  <li>Speichern</li>
</ol>

<h4>7.3 Person von Position entfernen</h4>
<ul>
  <li>Klick auf <strong><i class="fas fa-user-minus"></i></strong> neben dem Namen</li>
</ul>

<h3>8. Wiederkehrende Prozesse</h3>

<h4>8.1 Wiederkehrenden Prozess erstellen</h4>
<ol>
  <li>Menü: <strong>Prozesse → Wiederkehrende Prozesse</strong></li>
  <li>Formular ausfüllen:
    <ul>
      <li><strong>Vorlage</strong>: Welche Vorlage soll verwendet werden?</li>
      <li><strong>Fälligkeit</strong>: Datum, vor/nach Ferien, oder monatlich</li>
      <li><strong>Wochen vorher/nachher</strong>: Bei Ferien-Bezug</li>
    </ul>
  </li>
  <li>Speichern</li>
  <li>System startet Prozess automatisch zum festgelegten Zeitpunkt</li>
</ol>

<h4>8.2 Wiederkehrenden Prozess manuell starten</h4>
<ul>
  <li>In Liste: Klick auf <strong>"Jetzt starten"</strong></li>
  <li>Prozess wird sofort aus Vorlage generiert</li>
</ul>

<h3>9. Kategorien</h3>
<ul>
  <li>Dienen der Organisation von Vorlagen</li>
  <li>Beispiele: "Personal", "Verwaltung", "Pädagogik"</li>
  <li>Können beim Anlegen einer Vorlage erstellt werden</li>
</ul>

<h3>10. Besondere Funktionen</h3>

<h4>10.1 Prozess-Hierarchie</h4>
<ul>
  <li>Schritte können parallel oder nacheinander ablaufen</li>
  <li><strong>Nachfolgend</strong> (↓): Schritt startet erst wenn Vorgänger erledigt ist</li>
  <li><strong>Parallel</strong> (→): Schritt läuft gleichzeitig zum Vorgänger</li>
</ul>

<h4>10.2 E-Mail-Benachrichtigungen</h4>
<ul>
  <li>Beim Start eines Prozesses: alle Beteiligten des ersten Schritts</li>
  <li>Bei Erledigung eines Schritts: Beteiligte der Folgeschritte</li>
  <li>Erinnerungen bei überfälligen Schritten (täglich)</li>
</ul>

<h4>10.3 Zeitberechnung</h4>
<ul>
  <li>Frist = Startdatum + Dauer des Schritts in Tagen</li>
  <li>Bei abhängigen Schritten: Start nach Erledigung des Vorgängers</li>
  <li>Fristen werden automatisch aktualisiert</li>
</ul>

<h3>11. Farb- und Status-Legende</h3>
<ul>
  <li><span class="badge badge-success">Erledigt</span>: Schritt abgeschlossen</li>
  <li><span class="badge badge-info">Bis [Datum]</span>: Schritt offen, Frist nicht überschritten</li>
  <li><span class="badge badge-danger">Überfällig</span>: Frist abgelaufen</li>
  <li><span class="border-left-10-success">Grüner Rand</span>: Erledigter Schritt</li>
  <li><span class="border-left-10 border-info">Blauer Rand</span>: Offener Schritt</li>
</ul>

<h3>12. Best Practices & Tipps</h3>

<h4>Für Administratoren:</h4>
<ul>
  <li><strong>Vorlagen sauber strukturieren</strong>: Klare Schrittnamen und Beschreibungen</li>
  <li><strong>Realistische Dauern</strong>: Puffer einplanen</li>
  <li><strong>Positionen nutzen</strong>: Nicht einzelne Personen in Vorlagen, sondern Positionen</li>
  <li><strong>Kategorien sinnvoll wählen</strong>: Erleichtert das Finden von Vorlagen</li>
  <li><strong>Prozess-Namen individualisieren</strong>: "Einarbeitung 2025" statt nur "Einarbeitung"</li>
  <li><strong>Regelmäßig aufräumen</strong>: Alte abgeschlossene Prozesse archivieren/löschen</li>
</ul>

<h4>Für Mitarbeiter:</h4>
<ul>
  <li><strong>Täglich Dashboard prüfen</strong>: Offene Aufgaben im Blick behalten</li>
  <li><strong>Rechtzeitig erledigen</strong>: Überfällige Schritte blockieren Nachfolger</li>
  <li><strong>Bei Problemen melden</strong>: Wenn Schritt nicht erledigt werden kann</li>
  <li><strong>Prozess-Details lesen</strong>: Beschreibungen enthalten wichtige Informationen</li>
</ul>

<h3>13. Häufige Fragen (FAQ)</h3>

<h4>Kann ich einen Schritt delegieren?</h4>
<p>Nein, direkt nicht. Aber ein Administrator kann zusätzliche Personen zum Schritt hinzufügen oder Sie entfernen und eine andere Person zuweisen.</p>

<h4>Was passiert wenn ich einen Schritt als erledigt markiere?</h4>
<p>Alle abhängigen Nachfolgeschritte werden aktiviert und deren zuständige Personen per E-Mail benachrichtigt.</p>

<h4>Kann ich einen Schritt rückgängig machen?</h4>
<p>Nein, nur Administratoren können Schritte bearbeiten. Bei Fehlern wenden Sie sich an einen Administrator.</p>

<h4>Warum sehe ich einen Prozess nicht?</h4>
<ul>
  <li>Sie haben die Berechtigung <code>view assigned procedures</code> aber sind dem Prozess nicht zugewiesen</li>
  <li>Oder: Ihre Position ist in keinem Schritt verwendet</li>
  <li>Lösung: Administrator prüfen lassen</li>
</ul>

<h4>Kann ich die Beschreibung eines Schritts ändern?</h4>
<p>Nein, nur Administratoren können Schritte bearbeiten.</p>

<h4>Was bedeutet "Prozess wurde erfolgreich aktualisiert"?</h4>
<p>Ein Administrator hat den Namen oder die Beschreibung des Prozesses geändert. Die Änderung wird sofort angezeigt.</p>

<h4>Wie viele Personen können einem Schritt zugewiesen sein?</h4>
<p>Beliebig viele. Alle zugewiesenen Personen können den Schritt als erledigt markieren. Es reicht wenn eine Person ihn erledigt.</p>

<h4>Was ist der Unterschied zwischen Vorlage und Prozess?</h4>
<ul>
  <li><strong>Vorlage</strong>: Blueprint ohne Startdatum, kann mehrfach verwendet werden</li>
  <li><strong>Prozess</strong>: Konkrete Instanz mit Startdatum und zugewiesenen Personen</li>
</ul>

<h4>Kann ich eine Vorlage ändern während ein Prozess läuft?</h4>
<p>Ja, aber die Änderung betrifft nur neue Prozesse. Laufende Prozesse bleiben unverändert.</p>

<h4>Datenbank-Struktur:</h4>
<ul>
  <li><strong>procedures</strong>: Prozesse (Vorlagen und gestartete Instanzen)</li>
  <li><strong>procedure_steps</strong>: Schritte innerhalb eines Prozesses</li>
  <li><strong>steps_users</strong>: Zuordnung Personen ↔ Schritte</li>
  <li><strong>positions</strong>: Rollen/Positionen</li>
  <li><strong>procedure_categories</strong>: Kategorien für Vorlagen</li>
</ul>

<h3>16. Troubleshooting</h3>

<table class="table table-bordered">
  <thead>
    <tr>
      <th>Problem</th>
      <th>Mögliche Ursache</th>
      <th>Lösung</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Kann Prozess nicht sehen</td>
      <td>Fehlende Berechtigung oder nicht zugewiesen</td>
      <td>Administrator kontaktieren, Berechtigungen prüfen</td>
    </tr>
    <tr>
      <td>"Erledigt"-Button fehlt</td>
      <td>Berechtigung <code>complete own procedure steps</code> fehlt</td>
      <td>Berechtigung durch Administrator vergeben lassen</td>
    </tr>
    <tr>
      <td>Kann Vorlage nicht erstellen</td>
      <td>Berechtigung <code>manage procedures</code> fehlt</td>
      <td>Nur Administratoren können Vorlagen erstellen</td>
    </tr>
    <tr>
      <td>Prozess-Änderung wird nicht angezeigt</td>
      <td>Cache-Problem (behoben seit Update)</td>
      <td>Seite neu laden (F5)</td>
    </tr>
    <tr>
      <td>E-Mail-Benachrichtigung nicht erhalten</td>
      <td>E-Mail-Einstellungen / Spam-Filter</td>
      <td>Spam-Ordner prüfen, IT kontaktieren</td>
    </tr>
    <tr>
      <td>Schritt kann nicht gelöscht werden</td>
      <td>Schritt hat Nachfolger oder ist erledigt</td>
      <td>Nachfolger zuerst löschen, oder Schritt behalten</td>
    </tr>
  </tbody>
</table>


<hr>
<p><small><em>Letzte Aktualisierung: Januar 2026 | Version 2.0 mit neuem Berechtigungssystem</em></small></p>
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
        $title = 'Prozesse & Prozeduren — Anleitung';
        if (!Schema::hasTable('wiki_sites')) {
            return;
        }
        DB::table('wiki_sites')->where('title', $title)->delete();
    }
};
