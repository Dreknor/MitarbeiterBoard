<p align="center"><img src="https://mitarbeiter.esz-radebeul.de/img/logo.png" width="400"></p>

## Über das MitarbeiterBoard

Das MitarbeiterBoard ist eine umfassende Intranet- und Management-Plattform, die ursprünglich für das Evangelische Schulzentrum Radebeul entwickelt wurde. Es hat sich von einem Tool für Dienstberatungen zu einer vollständigen Organisationslösung für Schulen und Bildungseinrichtungen entwickelt. Es erleichtert die Verwaltung von Personal, Ressourcen, pädagogischen Prozessen und interner Kommunikation.

## Features

Das System ist modular aufgebaut und umfasst unter anderem folgende Bereiche:

### 🏢 Organisation & Verwaltung
*   **Dienstberatungen**: Planung und Durchführung von Meetings, inkl. Themen-Einreichung, Protokollierung und Aufgabenmanagement.
*   **Terminlisten**: Digitale Einschreibelisten für Veranstaltungen oder Aufgaben.
*   **Ticketsystem**: Interner IT- und Hausmeister-Support.
*   **Wiki**: Zentrale Wissensdatenbank und Dokumentation.
*   **Prozesse & Abläufe**: Definition und Nachverfolgung wiederkehrender Abläufe (Onboarding, Jahresplanung etc.).
*   **Inventar**: Verwaltung von Inventar, Standorten und Lieferanten inkl. Barcode-Scan.
*   **Raumbuchung**: Verwaltung und Buchung von Räumen, inkl. Kalender-Feeds.

### 👥 Personalwesen (HR)
*   **Dienstpläne (Roster)**: Schichtplanung, automatische Planungsvorschläge, PDF-Export.
*   **Arbeitszeiterfassung**: Digitale Stempeluhr und Stundenzettel.
*   **Urlaubsverwaltung**: Beantragung und Genehmigung von Urlaub und Abwesenheiten.
*   **Vertretungsplan**: Management von Ausfällen und Vertretungen.

### 🎓 Pädagogik
*   **Pädagogisches Tagebuch**: Dokumentation von Beobachtungen, Tagesverläufen und Gruppenereignissen.
*   **Diagnostik**: Erfassung von Entwicklungsständen, Zielen und Förderplänen.
*   **Wochenpläne**: Planung und Dokumentation der pädagogischen Arbeit in den Gruppen.


### 🛠 Technik & Sicherheit
*   **Rollen & Rechte**: Detailliertes Berechtigungssystem (RBAC).
*   **SSO Integration**: Unterstützung für SAML2 / Keycloak.
*   **Benachrichtigungen**: E-Mail und Push-Notifications (WebPush).
*   **Logging**: Protokollierung wichtiger Systemvorgänge.

## Systemvoraussetzungen

 * **PHP**: >= 8.1
 * **Datenbank**: MySQL / MariaDB
 * **Webserver**: Apache oder Nginx
 * **Node.js & NPM**: Für das Bauen der Frontend-Assets (Vite / TailwindCSS)
 * **Composer**: >= 2.x

## Installation

1. **Repository klonen**
   ```bash
   git clone <repository-url>
   cd mitarbeiter-board
   ```

2. **Backend-Abhängigkeiten installieren**
   ```bash
   composer install
   ```

3. **Frontend-Assets bauen**
   ```bash
   npm install
   npm run build
   ```

4. **Umgebungsvariablen konfigurieren**
   Kopieren Sie die `.env.example` zu `.env` und passen Sie die Werte an (Datenbank, Mail, URL etc.).
   ```bash
   cp .env.example .env
   # Bearbeiten Sie die .env Datei
   ```

5. **App-Key generieren**
   ```bash
   php artisan key:generate
   ```

6. **VAPID Keys für WebPush generieren**
   ```bash
   php artisan webpush:vapid
   ```

7. **Datenbank migrieren**
   ```bash
   php artisan migrate
   ```
   *Hinweis: Während der Migration wird ggf. ein initialer Admin-User angelegt (siehe Konsolen-Output).*

8. **Storage verlinken**
   ```bash
   php artisan storage:link
   ```

## Konfiguration & CronJobs

Damit Benachrichtigungen, automatische Planungen und Wartungsaufgaben funktionieren, müssen der Scheduler und die Queue eingerichtet werden.

**CronJob Eintrag:**
```bash
* * * * * cd /pfad/zum/projekt && php artisan schedule:run >> /dev/null 2>&1
```

**Queue Worker:**
Für den Mailversand und Hintergrundaufgaben sollte ein Queue-Worker laufen (z.B. via Supervisor):
```bash
php artisan queue:work
```

## Nutzung & Lizenz

Die Software wurde primär für das Evangelische Schulzentrum Radebeul entwickelt. Sie kann für nicht-kommerzielle Bildungsprojekte genutzt werden.
Es besteht kein Anspruch auf Support oder Haftung.
Änderungen und Weiterentwicklungen sollten der Community als Open-Source zur Verfügung gestellt werden.

