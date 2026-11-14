    # Phase 2: Umsetzungsplan Admin-Bereich (Entwurf)

Stand: 2026-11-13, **freigegeben**. Grundlage: `datenmodell.md` und `rollen.md` (beide freigegeben 2026-11-13).
Fortschritt und offene Punkte stehen im Projektstand (`docs/projektstand.md`), nicht hier.

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

- **Entscheidung vorher:** Symfony UX (Stimulus + `fetch`, Turbo Frames oder Live Components). Frage und Antwort im
  Projektstand („Jetzt“).

  *Optionen (vorgestellt 2026-11-13).* Alle drei laufen mit AssetMapper ohne Node, bauen auf
  `symfony/stimulus-bundle` 3.7.0 auf (PHP ≥ 8.4, Packagist) und brauchen am Server keinen zusätzlichen Prozess.

  | | Stimulus + `fetch` | Turbo Frames (`symfony/ux-turbo` 3.7.0) | Live Components (`symfony/ux-live-component` 3.7.2) |
  |---|---|---|---|
  | Pro | Volle Kontrolle über Tastatur und Zellen; eine Anfrage pro Änderung; durchschaubar (kleines eigenes JavaScript plus JSON-Endpunkt) | Fast kein eigenes JavaScript; Symfony-Forms und Validierung bleiben; Tests mit WebTestCase | Kaum JavaScript; das Raster wird nach jeder Änderung neu gerendert, Zählungen stimmen automatisch; später passend für die Preisschätzung im Booking-Formular |
  | Contra | Mehr eigenes JavaScript; Zählungen und Fehler im Browser nachführen; JavaScript braucht eigene Tests | Je Zelle zwei Anfragen (Formular laden, absenden); Zählungen außerhalb des Frames brauchen Turbo Streams; Turbo Drive gilt sonst seitenweit und müsste auf `/admin` beschränkt werden | Eigene Konzepte (LiveProp, writable, Hydration), schwerer zu debuggen; jede Änderung schickt den Zustand des ganzen Rasters hin und zurück; zwei zusätzliche Pakete |

  **Empfehlung Claude:** Stimulus + `fetch` für das Raster (tastaturlastig, viele kleine Änderungen). Live Components
  erst beim Booking-Formular erwägen. Bei Unsicherheit: eine Rasterzeile in zwei Varianten zum Vergleichen.
- Raster nach Kalenderwochen, Bearbeiten direkt in der Zelle, Tastatur, Ferien per Zeile, Zählung je Spalte.
- Design mit Sonjas Rückmeldung nachschärfen, wenn Mike es ihr in Produktion zeigt.

**C5 Schnupperstunden** – erledigt 2026-11-13

- Entity `TrialLesson`, Anlegen und Liste, Übernahme in eine Teilnahme (Transaktion, `fromTrial`).

**C6 Einstellungen** – erledigt 2026-11-13 (ohne `HomeSlide`, das ist ein eigener Schritt)

- Entity `Settings` (eine Zeile): Theme, Standarddauer, Schwellwert, Absenderdaten, Unterschrift (Upload, PNG, in der
  Datenbank). `App\Theme\SiteSettings` liest das Theme dann von hier.

## D. Honorarnoten

**D1 Honorarnoten erstellen** – erledigt 2026-11-13 (Nummernvergabe mit Advisory Lock statt Wiederholung, siehe `erklaerungen/13`)

- Entities `Invoice` (Embeddable `Recipient`, Nummer je Jahr mit Unique-Constraint und Wiederholung bei Kollision)
  und `Payment`. Erstellen aus einer Teilnahme mit Snapshot, Unveränderlichkeit über fehlende Setter.

**D2 PDF und Download**

- **Entscheidung vorher:** PDF-Bibliothek, die im PHP-Container läuft (kein zusätzlicher Container). Maßstab ist das
  Muster `HN16_…`. Frage und Antwort im Projektstand („Jetzt“).

  *Optionen (vorgestellt 2026-11-13, Versionen und Lizenzen von Packagist).* Ausgeschlossen: wkhtmltopdf (GitHub-Repo
  seit 2023 archiviert), Gotenberg (zusätzlicher Container), Headless Chrome (zu viel RAM).

  | Bibliothek | Version, Lizenz | Arbeitsweise | Pro | Contra |
  |---|---|---|---|---|
  | `dompdf/dompdf` | 3.1.6, LGPL-2.1 | HTML und CSS aus Twig (mit Übersetzungsschlüsseln) | Layout wie eine Webseite, leicht änderbar | Laut README kein Flexbox und kein Grid, Layout über Tabellen |
  | `mpdf/mpdf` | 8.3.1, GPL-2.0-only | HTML und CSS | Etwas mehr Funktionen für Seiten (Kopf-/Fußzeilen) | Strengere Lizenz (Copyleft), mehr Abhängigkeiten |
  | `tecnickcom/tc-lib-pdf` | 8.84.0, LGPL-3.0+ | PHP-Code mit Koordinaten | Pixelgenau, sehr aktiv (Nachfolger von TCPDF) | Viel mehr Code, Layout in PHP statt Template |
  | `setasign/fpdf` | 1.9.0, MIT | PHP-Code mit Koordinaten | Winzig | Laut FAQ kein UTF-8 (nur windows-1252) |

  Alle brauchen die PHP-Erweiterung GD für die Unterschrift (PNG mit transparentem Hintergrund): eine Zeile im
  `Dockerfile` (`install-php-extensions gd`), das Image wird etwas größer (beim Einbau messen).

  **Empfehlung Claude:** dompdf; vorher RAM-Bedarf und Ergebnis an einem Prototyp messen.
- PDF beim Erstellen erzeugen und in `invoice_pdf` ablegen, Dateiname nach Schema, Download einzeln und als ZIP je
  Kurs mit Hinweis auf fehlende Honorarnoten.

**D3 Zahlungen und Gutschriften** – erledigt 2026-11-13

- Zahlung erfassen (ein Klick für den Rest, Datum änderbar), Status berechnet, Gutschrift mit `credits` und
  `InvoiceVoter::CREDIT`.

**D4 Erinnerung bei überschrittener Frist** – Befehl erledigt 2026-11-13, Cronjob: `deployment.md` S8

- Befehl `app:invoices:remind-overdue` mit `remindedFor`, Tests mit festgelegtem Datum (`ClockInterface`/`MockClock`,
  damit Tests nicht vom heutigen Tag abhängen). Danach Cronjob am Server (nach Rückfrage, `deployment.md` S8).

**D5 Export der Einnahmen** – erledigt 2026-11-13 (mit Sonjas Excel noch zu prüfen)

- CSV je Jahr nach `Payment.paidOn` (Semikolon, Dezimalkomma, UTF-8 mit BOM). Mit Sonjas Excel prüfen.

## E. Dashboard – erledigt 2026-11-13

- Nächste Einheiten (inkl. Schnupperstunden), zuletzt gehaltene Einheit je Schüler:in mit Inhalt, Ausfälle, offene
  Honorarnoten, Einnahmen je Kurs und Jahr.

## F. Vor den echten Daten

- **Backups** der Datenbank: Mac zieht täglich einen verschlüsselten Dump per SSH (launchd, eingeschränkter Schlüssel),
  Rücksicherung einmal testen. Plan dazu als eigener Schritt, Server-Teil nach Rückfrage.
- **Datenschutz-Information** für Schüler:innen und Eltern (Art. 13 DSGVO), Entwurf zum Prüfen.
- **Aufbewahrungsfrist** der Honorarnoten in der BAO nachlesen, Lösch-/Anonymisierungsregel festhalten.
  Nachgelesen 2026-11-13 (RIS, § 132 BAO): Abs. 1 „Bücher und Aufzeichnungen sowie die zu den Büchern und
  Aufzeichnungen gehörigen Belege sind sieben Jahre aufzubewahren“, darüber hinaus so lange, wie sie für anhängige
  Abgabenverfahren von Bedeutung sind; die Frist läuft für Bücher und Aufzeichnungen „vom Schluß des Kalenderjahres“.
  Abs. 2: Aufbewahrung auf Datenträgern zulässig, wenn die Wiedergabe vollständig, geordnet, inhaltsgleich und bis zum
  Ende der Frist jederzeit gewährleistet ist (spricht für Backups der Datenbank samt PDFs, Abschnitt F).
  Die Lösch- und Anonymisierungsregel daraus steht als offener Punkt im Projektstand.

## Meilensteine

| Meilenstein | Nach Schritt | Was Sonja sehen kann |
|---|---|---|
| **Sonja zeigen** | C5 + D1–D3 (Mike lokal mit Fixtures, Sonja in Produktion) | Login, Schüler:innen, Kurs mit Ferien, Kursraster, Schnupperstunde übernehmen, Honorarnote als PDF, Zahlung |
| **Echtbetrieb** | F | Sonja trägt ihre sechs Schüler:innen ein |
| **Komplett** | D4, D5, E, B5 | Erinnerungen, Export, Dashboard, Passkeys |

B5 (Passkeys) kann vor oder nach dem Zeigen kommen; spätestens vor dem Echtbetrieb, wenn Sonja auf Passkeys umsteigen
will.

Kein eigener Demo-Termin (Entscheidung 2026-11-13, Projektstand): Mike sieht sich die Funktionen lokal an
(`docs/rundgang-admin.md`) und zeigt sie Sonja in Produktion.

## Später (außerhalb dieses Plans)

`HomeSlide` mit Bild-Upload, Zugang für Schüler:innen (`ROLE_STUDENT`, ggf. Login für Elternteile), Repertoireliste,
CMS für Texte, FAQ und Unterrichtsregeln von Sonja.
