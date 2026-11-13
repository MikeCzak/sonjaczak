    # Phase 2: Umsetzungsplan Admin-Bereich (Entwurf)

Stand: 2026-11-13, **freigegeben**. Grundlage: `datenmodell.md` und `rollen.md` (beide freigegeben 2026-11-13).
Fortschritt wird im Projektstand abgehakt.

## Spielregeln

- **Kleine Schritte.** Jeder Schritt ist ein eigener Commit (oder wenige), läuft durch die CI und geht live. Bei
  Datenbankänderungen genau eine Migration je Schritt, erzeugt mit `doctrine:migrations:diff` und vor dem Commit
  gemeinsam gelesen.
- **Vor jedem Schritt** nenne ich kurz Dateien, neue Pakete mit Version (aus Packagist/`composer.lock`) und offene
  Entscheidungen. Bei den Lernschwerpunkten (Doctrine, Security) erkläre ich ausführlicher, mit Fallstricken.
- **Tests in jedem Schritt:** Zugriff (anonym → Login, falsche Rolle → 403, Admin → 200), Fachregeln über
  Foundry-Factories, Formulare. Dazu ein Test, der **alle** Routen unter `/admin` aus dem Router liest und prüft, dass
  keine ohne Login erreichbar ist. Damit fällt eine vergessene Absicherung sofort auf.
- **Am Server** (Konten anlegen, Cronjobs, Backups) frage ich vorher.
- **Echte Daten erst nach Meilenstein F** (Backups und Datenschutz-Information).

## A. Grundlagen

**A1 Zeitzone und Werkzeuge** – erledigt 2026-11-13

- `date.timezone = Europe/Vienna` in `frankenphp/conf.d/10-app.ini`, Test dazu.
- `zenstruck/foundry` und `symfony/maker-bundle` als `require-dev` (Maker für `make:user`, `make:entity`,
  `make:migration`; erzeugter Code wird gelesen und angepasst, nicht blind übernommen). `doctrine/doctrine-fixtures-bundle`
  war geplant, ist aber überflüssig: Foundry lädt Fixtures selbst (`foundry:load-fixtures`, Stories mit `#[AsFixture]`)
  und setzt die Datenbank dabei über die Migrationen neu auf.
- PHPStan mit `objectManagerLoader`, damit es Entities und DQL prüft (Projektstand: „sobald es Entities gibt“).

## B. Login und Konten

**B1 User und Login mit Passwort** (`rollen.md` 1–4) – erledigt 2026-11-13, Konten am Server noch offen

- `symfony/security-bundle`, Entity `User` (`make:user`), erste Migration.
- Firewall nur für `^/admin`, `access_control`, Rollenhierarchie, `form_login` mit CSRF, `login_throttling`, Logout.
- Admin-Layout (eigenes Basis-Template, Navigation, `noindex`), Startseite `/admin` als Platzhalter, Domain `admin`
  für Übersetzungen.
- Konsolenbefehl `app:user:create` (Passwort verdeckt).
- Datenschutzerklärung: Absatz zum Session-Cookie im Verwaltungsbereich (`rollen.md` 10, Rechtsgrundlage vorher prüfen).
- Tests: öffentliche Seiten weiterhin ohne `Set-Cookie`, Login-Seite ohne Absenden ohne Cookie, Zugriffsmatrix,
  Routen-Test (siehe Spielregeln).
- Danach am Server (nach Rückfrage): Konten für Mike und Sonja anlegen.

**B2 Sessions in PostgreSQL** (`rollen.md` 6) – erledigt 2026-11-13 (zusammen mit B1 committet)

- `PdoSessionHandler` mit der Doctrine-Verbindung, Migration für die Tabelle `sessions`.
- `cookie_path: /admin`, `gc_maxlifetime: 28800`, `cookie_lifetime: 43200`.
- Prüfen nach dem Deploy: angemeldet bleiben über einen Deploy hinweg.

**B3 Passwort vergessen** (`rollen.md` 4.2) – erledigt 2026-11-13

- `symfonycasts/reset-password-bundle`, Routen unter `/admin`, Mail-Template (Text), immer gleiche Antwort.
- „Mein Konto“: Passwort ändern.

**B4 Benutzerverwaltung** (nur `ROLE_SUPER_ADMIN`) – erledigt 2026-11-13

- Liste, anlegen (mit Einladungslink über den Mechanismus aus B3 statt Passwort im Formular), Rolle ändern,
  deaktivieren. Ein Konto darf sich nicht selbst die letzte Super-Admin-Rolle nehmen (Voter).

**B5 Passkeys** (`rollen.md` 5, 10)

- `web-auth/webauthn-symfony-bundle`, Tabelle für Passkeys, Login-Knopf, „Mein Konto“: Passkey hinzufügen,
  Liste mit Gerät und letzter Nutzung, löschen.
- Vor dem Einbau: Konfiguration und Datenmodell im Code des Bundles prüfen (die Doku ist lückenhaft).
- Am Ende: Sonja richtet Passkeys auf Mac und Samsung-Handy ein (mit Mike).

## C. Unterricht

**C1 Schüler:innen und Elternteile** – erledigt 2026-11-13

- Embeddable `Address`, Entities `Guardian` und `Student` (mit Beziehung zu `User`, vorerst ungenutzt).
- CRUD mit eigenen Controllern und Form-Types (`AddressType` wiederverwendbar), Liste mit Suche.
- `StudentVoter::DELETE` (keine Honorarnoten; greift ab D1, bis dahin immer erlaubt).
- Foundry-Factories und `AppFixtures` mit erfundenen Daten.

**C2 Kurse und Ferien** – erledigt 2026-11-13

- Entities `Course` (Enum `Term`) und `CourseBreak` (Enum `BreakSource`), CRUD.
- C2b: Ferien-Vorschlag aus der OpenHolidays API (Steiermark `AT-SM`, Schulferien und Feiertage), mit Test gegen
  eine aufgezeichnete Antwort (`MockHttpClient`), damit die CI nicht vom fremden Dienst abhängt.

**C3 Teilnahmen und Einheiten** – erledigt 2026-11-13

- Entities `Enrollment` (Enum `Billing`) und `Lesson` (Enum `LessonStatus`, Selbstreferenz `makeUpFor`).
- Service, der die Einheiten erzeugt (bis `plannedLessons`, ohne Ferien), Neuberechnung bei neuen Ferien.
- Nachholregel als Service mit Unit-Tests. Schwellwert vorerst als Parameter, ab C6 aus den Einstellungen.
- `EnrollmentVoter::DELETE`.

**C4 Kursraster** (`datenmodell.md` 4)

- **Entscheidung vorher:** Symfony UX (Stimulus + `fetch`, Turbo Frames oder Live Components). Ich stelle die drei mit
  einem kleinen Beispiel aus dem Raster gegenüber.
- Raster nach Kalenderwochen, Bearbeiten direkt in der Zelle, Tastatur, Ferien per Zeile, Zählung je Spalte.
- Design gemeinsam mit Sonjas Rückmeldung beim Demo-Termin nachschärfen.

**C5 Schnupperstunden**

- Entity `TrialLesson`, Anlegen und Liste, Übernahme in eine Teilnahme (Transaktion, `fromTrial`).

**C6 Einstellungen**

- Entity `Settings` (eine Zeile): Theme, Standarddauer, Schwellwert, Absenderdaten, Unterschrift (Upload, PNG, in der
  Datenbank). `App\Theme\SiteSettings` liest das Theme dann von hier.

## D. Honorarnoten

**D1 Honorarnoten erstellen**

- Entities `Invoice` (Embeddable `Recipient`, Nummer je Jahr mit Unique-Constraint und Wiederholung bei Kollision)
  und `Payment`. Erstellen aus einer Teilnahme mit Snapshot, Unveränderlichkeit über fehlende Setter.

**D2 PDF und Download**

- **Entscheidung vorher:** PDF-Bibliothek, die im PHP-Container läuft (kein zusätzlicher Container). Kandidaten
  stelle ich mit Version, Lizenz und RAM-Bedarf vor; Maßstab ist das Muster `HN16_…`.
- PDF beim Erstellen erzeugen und in `invoice_pdf` ablegen, Dateiname nach Schema, Download einzeln und als ZIP je
  Kurs mit Hinweis auf fehlende Honorarnoten.

**D3 Zahlungen und Gutschriften**

- Zahlung erfassen (ein Klick für den Rest, Datum änderbar), Status berechnet, Gutschrift mit `credits` und
  `InvoiceVoter::CREDIT`.

**D4 Erinnerung bei überschrittener Frist**

- Befehl `app:invoices:remind-overdue` mit `remindedFor`, Tests mit festgelegtem Datum (`ClockInterface`/`MockClock`,
  damit Tests nicht vom heutigen Tag abhängen). Danach Cronjob am Server (nach Rückfrage, `deployment.md`).

**D5 Export der Einnahmen**

- CSV je Jahr nach `Payment.paidOn` (Semikolon, Dezimalkomma, UTF-8 mit BOM). Mit Sonjas Excel prüfen.

## E. Dashboard

- Nächste Einheiten (inkl. Schnupperstunden), zuletzt gehaltene Einheit je Schüler:in mit Inhalt, Ausfälle, offene
  Honorarnoten, Einnahmen je Kurs und Jahr.

## F. Vor den echten Daten

- **Backups** der Datenbank: Mac zieht täglich einen verschlüsselten Dump per SSH (launchd, eingeschränkter Schlüssel),
  Rücksicherung einmal testen. Plan dazu als eigener Schritt, Server-Teil nach Rückfrage.
- **Datenschutz-Information** für Schüler:innen und Eltern (Art. 13 DSGVO), Entwurf zum Prüfen.
- **Aufbewahrungsfrist** der Honorarnoten in der BAO nachlesen, Lösch-/Anonymisierungsregel festhalten.

## Meilensteine

| Meilenstein | Nach Schritt | Was Sonja sehen kann |
|---|---|---|
| **Demo für Sonja** | C5 + D1–D3 (mit Fixtures, lokal oder in Produktion mit erfundenen Daten) | Login, Schüler:innen, Kurs mit Ferien, Kursraster, Schnupperstunde übernehmen, Honorarnote als PDF, Zahlung |
| **Echtbetrieb** | F | Sonja trägt ihre sechs Schüler:innen ein |
| **Komplett** | D4, D5, E, B5 | Erinnerungen, Export, Dashboard, Passkeys |

B5 (Passkeys) kann vor oder nach der Demo kommen; spätestens vor dem Echtbetrieb, wenn Sonja auf Passkeys umsteigen
will. Mein Vorschlag: nach der Demo, damit sie den Workflow früher sieht.

**Demo in Produktion mit erfundenen Daten?** Bequemer für Sonja (eigener Laptop, echte Adresse), aber die
Fixture-Daten müssten vor dem Echtbetrieb vollständig wieder raus, und Rechnungsnummern 1, 2, 3 … wären verbraucht.
Vorschlag: Demo lokal auf deinem Mac oder über einen getrennten Test-Datenbestand. Entscheiden wir vor der Demo.

## Später (außerhalb dieses Plans)

`HomeSlide` mit Bild-Upload, Zugang für Schüler:innen (`ROLE_STUDENT`, ggf. Login für Elternteile), Repertoireliste,
CMS für Texte, FAQ und Unterrichtsregeln von Sonja.
