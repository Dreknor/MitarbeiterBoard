# API v1 – Pädagogen-App

REST-API für die Pädagogen-App (EPIC-API-01). Spezifikation: [`openapi-v1.yaml`](openapi-v1.yaml) (OpenAPI 3.0, z. B. in Swagger UI / Insomnia / Postman importierbar). Aktuelle API-Version: **1.1.0** (`GET /api/v1/instance`).

## Inbetriebnahme

```bash
composer install                    # u.a. laravel/sanctum (^3.3)
php artisan migrate                 # personal_access_tokens, Entwicklungsziele, Idempotency-Keys, Gruppensessions, Schüler-Beitrittscodes
php artisan config:clear && php artisan route:clear
```

Der Scheduler muss laufen (`php artisan schedule:run` per Cron): Er löscht stündlich Idempotency-Keys älter als 48 h und abgelaufene Schüler-Beitrittscodes (`paed-app:prune-idempotency`) sowie täglich abgelaufene Tokens (`sanctum:prune-expired`).

Optionale `.env`-Werte:

| Variable | Standard | Bedeutung |
|---|---|---|
| `PAED_APP_TOKEN_DAYS` | `90` | Laufzeit von App-Tokens in Tagen, gleitend (jede Nutzung verlängert, max. ein DB-Schreibzugriff pro Tag) |
| `PAED_APP_PASSWORD_LOGIN` | `true` | Login mit lokalem Passwort (`POST /auth/token`) erlauben |
| `PAED_APP_REDIRECT_URIS` | `paeddiary://auth` | Erlaubte `redirect_uri` für den SSO-Login (kommagetrennt) |
| `PAED_APP_SCHOOL_NAME` | `APP_NAME` | Schulname in `GET /instance` (Setting `school_name` in der settings-Tabelle hat Vorrang) |
| `PAED_APP_PRIMARY_COLOR` | `#1E40AF` | Primärfarbe der App |
| `PAED_APP_SSO_LABEL` | `Mit Schulkonto anmelden` | Beschriftung des SSO-Buttons |
| `PAED_APP_MIN_VERSION` | `1.0.0` | Minimal unterstützte App-Version |
| `SANCTUM_EXPIRATION` | *(leer)* | Optionale absolute Höchstlaufzeit ab Ausstellung in Minuten (zusätzlich zu `PAED_APP_TOKEN_DAYS`) |
| `SANCTUM_TOKEN_PREFIX` | `mb_` | Präfix für Tokens (erleichtert Secret-Scanning) |

SSO ist verfügbar, sobald die Keycloak-Verbindung konfiguriert ist (`KEYCLOAK_CLIENT_ID`, `KEYCLOAK_BASE_URL`, `KEYCLOAK_REALM`). An Keycloak selbst muss nichts geändert werden.

> **Hinweis zur Umstellung:** Bis Version 1.0 liefen Tokens 30 Tage nach Ausstellung ab (`SANCTUM_EXPIRATION`). Jetzt steuert `expires_at` je Token die Laufzeit. Bestehende Tokens erhalten per Migration ihr bisheriges Ablaufdatum (Ausstellung + 30 Tage) und werden ab der nächsten Nutzung gleitend verlängert.

## Serverwahl

`GET /api/v1/instance` ist öffentlich (30/min) und liefert Schulname, Logo, Primärfarbe, API-Version und verfügbare Login-Arten. Im Web unter **Mein Profil → Pädagogen-App** zeigt die Kachel „App verbinden“ einen QR-Code mit `paeddiary://connect?server=https://<domain>`.

## Authentifizierung

**Passwort:** `POST /api/v1/auth/token` mit `email`, `password`, `device_name` → `access_token`. Rate-Limit 6/min je E-Mail+IP und 60/min je IP (viele Geräte im Schul-NAT).

**Schulkonto (SSO, Authorization Code + PKCE zwischen App und Backend):**

1. App erzeugt `code_verifier` (43–128 Zeichen) und `code_challenge = BASE64URL(SHA256(code_verifier))` sowie einen zufälligen `state`.
2. App öffnet im System-Browser: `GET /api/v1/auth/sso/start?redirect_uri=paeddiary://auth&code_challenge=…&code_challenge_method=S256&state=…`
3. Das Backend nutzt seinen bestehenden Keycloak-Login (gleiche Benutzerzuordnung wie im Web) und leitet danach auf `paeddiary://auth?code=…&state=…` weiter (bei Fehlern `?error=sso_failed&state=…`). Es wird kein Web-Login hinterlassen.
4. App ruft `POST /api/v1/auth/sso/exchange` mit `code`, `code_verifier`, `device_name` auf → gleiche Antwort wie `POST /auth/token`. Der Code ist 60 s gültig und einmal verwendbar (10 Versuche/min je IP).

Alle weiteren Requests: `Authorization: Bearer <token>`, `Accept: application/json`. Logout: `DELETE /api/v1/auth/token`. Geräteverwaltung: `GET /api/v1/auth/devices`, `DELETE /api/v1/auth/devices/{id}` bzw. im Web unter **Mein Profil → Pädagogen-App → Meine App-Geräte**.

## Robustheit für mobile Clients

| Thema | Umsetzung |
|---|---|
| Idempotenz | Header `Idempotency-Key: <uuid>` auf POST/PUT/PATCH/DELETE (außer `/auth/*`). Wiederholung → gespeicherte Antwort + `Idempotent-Replayed: true`; anderer Body → 422; parallel → 409. Nur 2xx werden gespeichert (48 h). |
| Konfliktschutz | `expected_updated_at` bei `PUT /paed-diary/entries/{id}` und `PUT /diagnostic/goals/{id}` → 409 mit aktuellem Datensatz in `data` |
| Delta-Abfragen | `updated_since` (ISO-8601) bei `GET /students/{id}/paed-diary/entries` und `GET /students/{id}/diagnostic/history` |
| Katalog-Caching | `ETag`/`If-None-Match` → 304 bei `/paed-diary/categories`, `/diagnostic/areas`, `/grading/stages` |
| Rate-Limit | Allgemein 60/min je Token (bzw. je IP ohne Token) |

## Tagebuch: Wochenansicht (Kalender)

- `GET /paed-diary/week?class_id=|group_id=&week_start=` – alle Daten der Web-Wochenansicht (Mo–Fr) in einer Antwort: Tage (inkl. Ferien), Schüler, Einträge der Woche + alle offenen Notizen, Pausen, Abwesenheiten, Tagespausen, Abhak-Spalten mit Werten, offene Aufgaben und Termine. Die Logik liegt in `App\Services\PaedDiaryCalendarService` und wird vom Web (`PaedDiaryController::weekData`) mitbenutzt – inkl. automatischer Pausen an Ferientagen und Klassen-Tagespausen.
- Schreibende Endpunkte setzen einen **Zielzustand** statt umzuschalten (sicher für Wiederholungen aus der Offline-Warteschlange):
  - `POST /paed-diary/entries/{id}/complete` (`date?`, `schueler_id?`)
  - `PUT /paed-diary/entries/{id}/pause` (`schueler_id`, `date`, `paused`)
  - `PUT /paed-diary/absences` (`schueler_id`, `date`, `absent`)
  - `PUT /paed-diary/day-pauses` (`class_id`|`group_id`, `date`, `paused`, `reason?`)
  - `PUT /paed-diary/column-values` (`column_id`, `schueler_id`, `date`, `value`)
  - `POST /paed-diary/tasks/{id}/close`

## Graduierung: Gruppensessions

- `POST /grading/sessions` mit `{type: "group", class_id, schueler_ids?, answer_order_mode?}`. Eine Session gehört genau zu **einer Klasse** (`grading_documentation_sessions.klasse_id`); alle `schueler_ids` müssen dieser Klasse angehören. Ohne `schueler_ids` nehmen alle Schüler der Klasse teil (wie im Web).
- Eigene offene Gruppensession für dieselbe Klasse → wird fortgesetzt (`meta.resumed = true`).
- `GET /classes/{id}/grading/sessions?status=open|completed` – Liste mit `progress`.
- `PATCH /grading/sessions/{id}` – `answer_order_mode` ändern (nur Ersteller, offene Session).
- `POST /grading/sessions/{id}/assessments` – bei Gruppensessions ist `schueler_id` Pflicht; `finalize` schließt nur diesen Schüler ab (inkl. Stufenvergabe, Historie, Tagebucheintrag). Die Session ist abgeschlossen, sobald alle Teilnehmer abgeschlossen sind. Im Web schließt „Session abschließen“ weiterhin alle Schüler auf einmal ab.

## Selbsteinschätzung auf Schüler-iPads

1. Lehrkraft (Ersteller der Session): `POST /grading/sessions/{id}/join-codes` → je Schüler Code (`K7M-4QX`) und `qr_payload` (`paeddiary://join?server=…&code=K7M4QX`), gültig bis Session-Ende, max. 8 h.
2. Schüler-iPad: `POST /student/join` mit `code`, `device_name` (10/min je IP) → Schüler-Token.
3. Schüler-iPad: `GET /student/session`, `POST /student/session/answers` (`question_id`, `self_rating` 1–5).
4. Im Modus `by_question` gibt die Lehrkraft Fragen mit `POST /grading/sessions/{id}/current-question` frei.
5. `DELETE /grading/sessions/{id}/join-codes` widerruft alle Codes und Schüler-Tokens. Abgeschlossene Sessions machen Schüler-Tokens ebenfalls ungültig (401).

Schüler-Tokens gehören einem eigenen Modell (`GradingStudentDevice`), nicht der Lehrkraft, und tragen die Ability `student-grading:{session_id}:{schueler_id}`. Sie gelten ausschließlich für `/student/*`; alle übrigen Endpunkte antworten mit 403.

> **Hinweis:** Das Rate-Limit von 10 Beitritten/min gilt je IP. Treten viele Schüler gleichzeitig hinter einem gemeinsamen Schul-NAT bei, sollten die Beitritte gestaffelt werden.

## Dossier als PDF

`GET /students/{id}/dossier.pdf` – gleiche Parameter und Rechte wie `/dossier`, `Content-Disposition: inline`, `Cache-Control: no-store`. Im Web: Schüleransicht des Tagebuchs → „Dossier (PDF)“ (gleiche Vorlage `resources/views/pdf/dossier.blade.php`).

## Rechte

| Permission | Wirkung |
|---|---|
| `view paed diary` | Grundrecht für alle Endpunkte; Zugriff auf Schüler der über `klasse_user` zugeordneten Klassen |
| `view diagnostics` | Bereich Diagnose + Diagnosedaten in Schüler-View/Dossier |
| `manage grading systems` | Graduierungsstufe vergeben (`grading_stage_id` beim Abschluss) |
| `manage diagnostics` | Entwicklungsziele endgültig löschen (`DELETE …?force=true`) |
| `view all students` | Klassenübergreifender Zugriff (Schulleitung) |
| `view confidential diary entries` | Vertrauliche Tagebucheinträge (`dossier_only`) fremder Autoren lesen |

Die Rolle `Admin` hat beide Sonderrechte implizit; die Migration vergibt sie zusätzlich an die Rollen `Admin` und `Schulleitung` (falls vorhanden).

## Abbildung des Konzepts auf das Datenmodell

| Konzept | Umsetzung |
|---|---|
| `student` | `Schueler` (`vorname`, `nachname`, `geburtsdatum`) |
| Klassen der Lehrkraft | Pivot `klasse_user` (`User::paed_klassen`) |
| Lerngruppen | `PaedDiaryClassGroup` des Benutzers (`learning_groups` in `GET /classes`) |
| `is_dossier_only` | Spalte `paed_diary_entries.dossier_only` |
| Tagebucheintrag für mehrere Schüler | Ein Eintrag pro Klasse, Schüler über Pivot `paed_diary_entry_schueler` (wie im Web) |
| Graduierungsstufe `level` | `grading_stages.sort_order` |
| `rating_value` | Pädagogenbewertung `grading_teacher_assessments.teacher_rating` (1–5) |
| `teacher_assessment` | Coaching-Notiz `grading_coaching_notes.note` |
| Teilnehmer einer Gruppensession | `grading_session_students` (inkl. `finalized_at` je Schüler); ohne Einträge = ganze Klasse |
| Freigegebene Frage (`by_question`) | `grading_documentation_sessions.current_question_id` |
| Schüler-Beitritt | `grading_join_codes`, `grading_student_devices` (Besitzer der Schüler-Tokens) |
| Idempotenz | `api_idempotency_keys` |
| Entwicklungsziele (`goals`) | Tabelle `diagnostic_development_goals` (Titel, Zieldatum, Status, Abschlussnotiz) |
| Kriterien des Diagnosekatalogs | `diagnostic_goals` – in der API als `criterion_id` bezeichnet, um Verwechslungen zu vermeiden |
| Im Web markierte „aktuelle Ziele“ | `current_criterion_goals` (DiagnosticAssessment mit `is_current_goal`) |

## Tests

```bash
php artisan test tests/Feature/API/v1
```
