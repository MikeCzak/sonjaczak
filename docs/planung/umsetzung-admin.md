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

**B1 User und Login mit Passwort** (`rollen.md` 1–4) – erledigt 2026-11-13

- `symfony/security-bundle`, Entity `User` (`make:user`), erste Migration.
- Firewall nur für `^/admin`, `access_control`, Rollenhierarchie, `form_login` mit CSRF, `login_throttling`, Logout.
- Admin-Layout (eigenes Basis-Template, Navigation, `noindex`), Startseite `/admin` als Platzhalter, Domain `admin`
  für Übersetzungen.
- Konsolenbefehl `app:user:create` (Passwort verdeckt).
- Datenschutzerklärung: Absatz zum Session-Cookie im Verwaltungsbereich (`rollen.md` 10, Rechtsgrundlage vorher prüfen).
- Tests: öffentliche Seiten weiterhin ohne `Set-Cookie`, Login-Seite ohne Absenden ohne Cookie, Zugriffsmatrix,
  Routen-Test (siehe Spielregeln).
- Konten in Produktion angelegt (Mike per Befehl, Sonja per Einladungslink, 2026-11-14).

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

- Entities `Enrollment` (Enum `Billing`) und `Lesson` (Enum `CancelledBy` seit C4, Selbstreferenz `makeUpFor`).
- `LessonPlanner` erzeugt die Einheiten (bis `plannedLessons`, ohne Ferien).
- Nachholregel (`MakeUpRule`): Mindestzahl = geplante Einheiten, kein Schwellwert (seit 2026-11-15).
- `EnrollmentVoter::DELETE`.

**C4 Kursraster** – erledigt 2026-11-16. Erklärungen mit Alternativen: `erklaerungen/17-csrf.md`,
`erklaerungen/18-kursraster.md`.

Entscheidungen (Mike, 2026-11-14 bis 2026-11-16):

- **Technik:** Stimulus + `fetch` (nicht Turbo Frames, nicht Live Components); eigener Entrypoint `admin`, öffentliche
  Seiten laden kein Stimulus. Controller `grid` und `break-confirm` lazy. JavaScript wird im Browser geprüft, nicht mit
  Panther. CSRF-Controller des Rezepts entfernt; das Raster nutzt ein Session-Token je Kurs (Header `X-CSRF-Token`).
- **Seite:** eigene Seite `/admin/kurse/{id}/raster` (Links von Kursseite, Kursliste, Dashboard). Zeilen =
  Kalenderwochen, Spalten = Zeitfenster (Wochentag + Uhrzeit), Name in der Zelle. Einheit ohne passendes Zeitfenster
  (Nachholstunde an anderem Tag) steht in der Spalte ihrer Teilnahme mit Datum und Uhrzeit. Absagen in einer roten
  Zeile unter der Woche. Schnupperstunden als Liste über der Tabelle.
- **Bedienung:** nur Knöpfe, keine Tastaturkürzel, kein Ziehen. Zelle: „abgesagt (Schüler:in)“ / „abgesagt (ich)“ als
  Schalter, Notiz, Löschen, „Absage aufheben“, Link auf die Einzelseite (Beginn, Dauer). Nach allem außer einer
  Notiz lädt die Seite neu (der Server zählt).
- **Status der Einheit:** nur `cancelledBy` (nullable: `student`, `teacher`) gespeichert; „geplant“ = existiert,
  „gehalten“ aus der Zeit abgeleitet (`datenmodell.md` 3.5).
- **Eine Regel für die Zahl:** Jede Teilnahme hat immer `plannedLessons` reguläre Einheiten (abgesagte zählen mit,
  Nachholstunden nicht). Daraus folgt:
  - Einheit löschen (Irrtum) → am Ende eine dazu (`fillUpAtEnd()`), vorher Popup.
  - Doppelstunde = zweite Einheit derselben Person mit **ihrer** Dauer, direkt vor oder nach ihrer Einheit, nur
    neben Nachbarn am selben Tag; überschneidet sie etwas, wird nichts angelegt. Die letzte zukünftige Einheit fällt weg.
  - Absage aufheben → was sich zeitlich überschneidet, wird nach Popup gelöscht, die betroffene Teilnahme bekommt am
    Ende eine dazu.
  - Ferien nachträglich eintragen → alle Einheiten im Zeitraum gehen (auch gehaltene, abgesagte), am Ende kommen neue
    dazu; vorher Popup mit Liste, Server löscht nur mit `confirmed=1`. Gilt auch für „Ferien vorschlagen lassen“.
  - Ferien löschen → Einheiten an den freien Tagen kommen zurück, am Ende fallen sie weg.
  - Ferien per Woche: Link „Ferien …“ in jeder Wochenzeile öffnet das Ferienformular vorbelegt, danach zurück ins Raster.
- **Kursname:** abgeleitet, Wochentage aus den Teilnahmen angehängt („Wintersemester 2026/27 (Fr)“), kein Feld.

**C5 Schnupperstunden** – erledigt 2026-11-13

- Entity `TrialLesson`, Anlegen und Liste, Übernahme in eine Teilnahme (Transaktion, `fromTrial`).

**C6 Einstellungen** – erledigt 2026-11-13 (ohne `HomeSlide`, das ist ein eigener Schritt)

- Entity `Settings` (eine Zeile): Theme, Standarddauer, Einheiten pro Semester (Vorschlag für neue Kurse),
  Absenderdaten. Keine Unterschrift (entfernt 2026-11-14). `App\Theme\SiteSettings` liest das Theme von hier.

**C7 Anzahl der Einheiten je Teilnahme, Teilnahme beenden** – erledigt 2026-11-16

Entscheidungen (Mike, 2026-11-16): Häufigster Grund für weniger Einheiten ist ein vorzeitiges Ende aus persönlichem
Grund. Daher eine eigene Anzahl je Teilnahme (verworfen: dritte Absage-Art „entfällt ersatzlos“; Löschen mit Abfrage
„am Ende anhängen?“, weil die Nachholregel dann dauerhaft „fällig“ meldet).

- `Enrollment::$plannedLessons` ist eine Kopie aus dem Kurs (wie Preis und Dauer); spätere Änderungen am Kurs wirken
  nicht auf bestehende Teilnahmen. Nachholregel und Planer lesen die Zahl der Teilnahme.
- Ändern auf der Seite der Teilnahme, **Grund ist Pflicht**. **Untergrenze:** reguläre Einheiten, die schon begonnen
  haben. „Teilnahme beenden“ setzt genau diese Zahl (vor Kursbeginn 0).
- Senken löscht die letzten zukünftigen Einheiten (auch abgesagte, nicht Nachholstunden), vorher Liste und
  Bestätigung ohne JavaScript. Erhöhen hängt am Ende an, höchstens bis Kursende.
- **Geld nie automatisch:** Mit Honorarnote folgt ein Hinweis auf die Gutschrift (D3). Absage vor Kursbeginn ohne
  Honorarnote: Teilnahme löschen.
- **Einzelne Absage durch Sonja ohne Nachholen:** Anzahl −1 reicht nicht (der Planer zählt die abgesagte Einheit mit,
  die Nachholregel nicht; Senken löscht dann eine zukünftige). Umweg: die abgesagte Einheit im Raster löschen (am
  Ende kommt eine dazu), dann Anzahl −1. Mike: reicht vorerst.

## D. Honorarnoten

**D1 Honorarnoten erstellen** – erledigt 2026-11-13 (Nummernvergabe mit Advisory Lock statt Wiederholung, siehe `erklaerungen/13`)

- Entities `Invoice` (Embeddable `Recipient`, Nummer je Jahr mit Unique-Constraint, vergeben unter Advisory Lock)
  und `Payment`. Erstellen aus einer Teilnahme mit Snapshot, Unveränderlichkeit über fehlende Setter.

**D2 PDF und Download** – entschieden 2026-11-14: dompdf, **noch nicht gebaut (nächster Schritt)**

- dompdf 3.1.6 (LGPL-2.1, Stand Packagist 2026-11-13): HTML aus Twig mit Übersetzungsschlüsseln, läuft im
  PHP-Container. Verworfen: mPDF (GPL), tc-lib-pdf und FPDF (Layout in PHP-Koordinaten, FPDF ohne UTF-8),
  wkhtmltopdf (archiviert), Gotenberg und Headless Chrome (eigener Container bzw. zu viel RAM).
  Laut README kein Flexbox/Grid, Layout über Tabellen. Version vor dem Einbau erneut prüfen.
- Keine Unterschrift auf der Honorarnote; ob GD trotzdem nötig ist, beim Einbau prüfen. RAM-Bedarf am Prototyp messen.
- Maßstab ist das Muster `HN16_…` (`claude_references/`). PDF beim Erstellen erzeugen und in `invoice_pdf` ablegen,
  Dateiname nach Schema, Download einzeln und als ZIP je Kurs mit Hinweis auf fehlende Honorarnoten.

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

- **Backups** – erledigt 2026-11-15 (Rücksicherung getestet): `backups.md`, `erklaerungen/16-backups.md`.
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
