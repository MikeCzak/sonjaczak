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

### B5 Plan (freigegeben 2026-11-17)

**Geprüft** (2026-11-17): Packagist: `web-auth/webauthn-symfony-bundle` 5.3.12 (2026-11-03) ist aktuell, verlangt PHP
≥ 8.2 und `symfony/security-bundle ^6.4|^7.0|^8.0`. `composer require --dry-run` im Container: 15 Pakete (u. a.
`web-auth/webauthn-lib`, `web-auth/cose-lib`, `spomky-labs/cbor-php`, `symfony/serializer`, `symfony/uid`), kein
weiterer Dienst, `openssl` ist im Image. Code des Bundles 5.3.12 (GitHub-Tarball, nicht installiert) gelesen:

- **Datenmodell:** Das Bundle bringt `Webauthn\CredentialRecord` als Mapped Superclass (Doctrine-XML im Bundle):
  Credential-ID, öffentlicher Schlüssel, `userHandle` (Text), Zähler, AAGUID, Transports u. a. Wir leiten eine eigene
  Entity davon ab und ergänzen Spalten. Gespeichert wird über `DoctrineCredentialSourceRepository`
  (`persist` + `flush`), gesucht über `userHandle` bzw. Credential-ID.
- **Benutzer:** Das Bundle fragt ein eigenes Repository nach `findOneByUsername()` und `findOneByUserHandle()` und
  bekommt ein `PublicKeyCredentialUserEntity` (Name, Anzeigename, Handle). Ohne eigene Klasse nimmt es einen
  „Dummy“, der nichts findet.
- **Firewall:** eigener Schlüssel `webauthn` in der Firewall (`WebauthnFactory`), mit eigenen Routen für Optionen und
  Ergebnis (Standard `/login/options`, `/login`, also bei uns unter `/admin` umzustellen). Hinzufügen eines Passkeys
  für angemeldete Konten über `webauthn.controllers.creation`.
- **Zwischenspeicher der Challenge:** Standard `SessionStorage`, d. h. die Login-Seite startet beim Klick eine Session
  (Cookie nur unter `/admin`, wie beim Passwort-Login nach dem Absenden).
- **JavaScript:** npm-Paket `@web-auth/webauthn-stimulus` 5.3.12 (Stimulus-Controller, braucht
  `@simplewebauthn/browser ^13.2`), über AssetMapper eingebunden wie Bootstrap, kein Node.
- **CSRF:** Die Antworten des Browsers enthalten Herkunft und Challenge und sind vom Gerät unterschrieben; eine fremde
  Seite kann sie nicht erzeugen. Unsere Formulare (Löschen) bleiben wie gehabt (`erklaerungen/17`).

**Optionen für die Bibliothek** (entschieden in `rollen.md` 5, hier nur zur Erinnerung): A Bundle (empfohlen, bringt
Authenticator für die Firewall und Endpunkte); B nur `webauthn-lib` mit eigenen Controllern und eigenem Authenticator
(mehr Code, alles sichtbar, mehr Fehlerquellen bei Kryptografie-Details); C anderes Paket (z. B. `lbuchs/webauthn`,
ohne Symfony-Integration). Ich bleibe bei A.

**Schritte:**

- **B5a Grundlage** (ohne Oberfläche): Paket, `config/packages/webauthn.yaml` (Name „Sonja Czak“, erlaubte Herkunft
  aus `DEFAULT_URI`, also lokal `localhost`, in Produktion `sonjaczak.com`), Entity `Passkey` (abgeleitet von
  `CredentialRecord`) mit `name`, `createdAt`, `lastUsedAt`, Migration; `user.webauthn_handle` (Frage 3);
  `PasskeyUserRepository` für das Bundle. Tests: Repository findet Konto per Handle, deaktiviertes Konto nicht.
  **Erledigt 2026-11-17** (Bundle 5.3.13 statt 5.3.12: Sicherheits-Release vom selben Tag; RP-ID = Host der Anfrage,
  ohne `allowed_origins`).
- **B5b Mein Konto:** Abschnitt „Passkeys“: Liste (Name, angelegt, zuletzt genutzt), „Passkey hinzufügen“ (Name
  eingeben, dann Dialog des Geräts), löschen mit Rückfrage. Tests für Liste und Löschen (nur eigene Passkeys).
  **Erledigt 2026-11-17.** Abweichung: statt `@web-auth/webauthn-stimulus` ein eigener kleiner Stimulus-Controller
  (`assets/controllers/passkey_register_controller.js`) mit `@simplewebauthn/browser` 14.0.0, weil der fertige
  Controller keinen Namen mitschicken kann; den Namen setzt `App\Security\PasskeyCreatedHandler` nach dem Speichern.
- **B5c Login:** Knopf „Mit Passkey anmelden“ über dem Passwortformular; kein E-Mail-Feld nötig (Passkey ist auf dem
  Gerät gespeichert, „discoverable“). Nach Erfolg `lastUsedAt` setzen. Deaktivierte Konten scheitern wie beim
  Passwort (`UserChecker`). Passwort bleibt als Rückweg (`rollen.md` 5).
  **Erledigt 2026-11-17.** Nach dem Login geht es zur zuvor verlangten Admin-Seite, sonst zur Übersicht
  (`PasskeyLoginSuccessHandler`). Der Test spielt das Gerät mit einem echten P-256-Schlüssel und läuft durch die
  ganze Prüfung der Bibliothek.
- **B5d Ausprobieren:** lokal mit Touch ID im Browser (localhost gilt als sichere Herkunft) und mit dem virtuellen
  Authenticator von Chrome (DevTools); die eigentliche Unterschrift testen die automatischen Tests nicht (`rollen.md`
  5). Danach Push, Sonja richtet in Produktion ihre Passkeys ein (mit Mike).
  **Lokal erledigt 2026-11-17** mit dem virtuellen Authenticator (Headless-Chrome): Hinzufügen, zweites Mal am selben
  Gerät abgelehnt, Login mit Weiterleitung, „zuletzt genutzt“. Dabei gefunden: RP-ID muss gesetzt sein, sonst fehlt
  `rp` in den Optionen (jetzt aus `DEFAULT_URI`). Touch ID von Mike lokal geprüft und gepusht 2026-11-18. Offen: Einrichten mit Sonja.
- `deploy/restore-test.sh`: keine Änderung nötig, es liest die Tabellen aus der Datenbank (`passkey` ist dabei).
  Erklärung: `erklaerungen/21-passkeys.md`.

**Fragen:**

Antworten Mike, 2026-11-17: **1 MacBook Air und Android (Samsung A52s), kein iPhone; 2–5 wie empfohlen (A).**

1. **Geräte:** `rollen.md` 5 spricht von Mac und iPhone (iCloud-Schlüsselbund), dieser Plan von einem Samsung-Handy.
   Mit Android liegt der Passkey im Google-Passwortmanager, nicht in iCloud: Sonja richtet dann **je Gerät** einen
   Passkey ein (Mac und Handy), oder meldet sich am Mac per QR-Code mit dem Handy an. Am Code ändert das nichts. Was
   hat Sonja?
2. **Fingerabdruck immer verlangen?**
   - **A (empfohlen): ja** (`user_verification: required`). Ohne Fingerabdruck oder Geräte-PIN kein Login; der
     Passkey ersetzt so Passwort und zweiten Faktor.
   - B: „preferred“ (Standard des Bundles). Ein Sicherheitsschlüssel ohne Fingerabdruck reicht dann schon zum Login.
3. **Kennung des Kontos für das Gerät** (`userHandle`, wird auf dem Gerät gespeichert):
   - **A (empfohlen): Zufallswert** in neuer Spalte `user.webauthn_handle` (32 Zufallsbytes, beim ersten Passkey
     erzeugt). Die WebAuthn-Spezifikation verlangt, dass er keine persönlichen Daten enthält.
   - B: die Datenbank-ID des Kontos. Pro: keine Spalte. Contra: verrät die Reihenfolge der Konten, hängt an der ID.
4. **Name des Passkeys in der Liste:**
   - **A (empfohlen): Sonja tippt ihn beim Hinzufügen** („MacBook“, „Handy“), vorbelegt mit „Passkey vom
     17.11.2026“.
   - B: aus der AAGUID automatisch („iCloud-Schlüsselbund“, „Google-Passwortmanager“), braucht eine Liste der
     Hersteller-IDs (Metadaten von außen). Contra: zwei Macs heißen gleich.
5. **Verbindung Passkey → Konto:**
   - **A (empfohlen): nur über `userHandle`** wie im Bundle vorgesehen. Nichts doppelt gespeichert. Konten werden
     nicht gelöscht, nur deaktiviert.
   - B: zusätzlich Fremdschlüssel `user_id` (Löschen kaskadiert, Abfragen per Join). Contra: dieselbe Information
     zweimal.

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

**C4f Ferienwochen im Raster** (Frage von Mike, 2026-11-18): Eine Zeile ist gelb, sobald Ferien die Woche auch nur
an einem Tag berühren (`CourseGrid::breaksIn`). Fixtures: Weihnachtsferien 24.12.–6.1. (Do–Mi) → drei gelbe Wochen,
in der dritten (4.–10.1.) stehen die Freitagseinheiten vom 8.1., der kein Ferientag ist. Richtig geplant, aber
missverständlich. Optionen:

- **A (Empfehlung):** Gelb nur, wenn ein Ferientag auf einen Wochentag fällt, an dem der Kurs Teilnahmen hat (hier
  Freitag); sonst bleibt nur das Abzeichen, mit Zeitraum („Weihnachtsferien bis 6.1.“). Pro: zeigt, was für den
  Unterricht zählt. Contra: Bei Kursen an mehreren Tagen ist eine Woche gelb, obwohl ein Tag Unterricht hat.
- **B:** Gelb wie bisher, Abzeichen mit Zeitraum. Pro: kleinste Änderung. Contra: gelbe Zeile mit Einheiten bleibt.
- **C:** Ferientage je Zelle färben statt je Zeile. Pro: genau. Contra: größerer Umbau des Rasters.

Dazu (Frage, keine Option): Eine einzelne Einheit lässt sich im Formular „Einheit bearbeiten“ ohne Hinweis auf einen
Ferientag verschieben; die Planung selbst legt nie Einheiten in Ferien. Hinweis beim Speichern, Sperre oder so lassen?

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

**D2 PDF und Download** – entschieden 2026-11-14: dompdf, erledigt 2026-11-17 (`erklaerungen/19`)

- dompdf 3.1.6 (LGPL-2.1, Stand Packagist 2026-11-13): HTML aus Twig mit Übersetzungsschlüsseln, läuft im
  PHP-Container. Verworfen: mPDF (GPL), tc-lib-pdf und FPDF (Layout in PHP-Koordinaten, FPDF ohne UTF-8),
  wkhtmltopdf (archiviert), Gotenberg und Headless Chrome (eigener Container bzw. zu viel RAM).
  Laut README kein Flexbox/Grid, Layout über Tabellen. Version vor dem Einbau erneut prüfen.
- Keine Unterschrift auf der Honorarnote; ob GD trotzdem nötig ist, beim Einbau prüfen. RAM-Bedarf am Prototyp messen.
- Maßstab ist das Muster `HN16_…` (`claude_references/`). PDF beim Erstellen erzeugen und in `invoice_pdf` ablegen,
  Dateiname nach Schema, Download einzeln und als ZIP je Kurs mit Hinweis auf fehlende Honorarnoten.

Plan (2026-11-17, freigegeben 2026-11-17). Geprüft: dompdf 3.1.6 ist weiterhin die neueste Version (Packagist,
veröffentlicht 2026-07-20), braucht laut `composer.json` `ext-dom` und `ext-mbstring`, beide im Image vorhanden.
`ext-zip` für das ZIP ist ebenfalls vorhanden. GD wird nur für Bilder gebraucht; die Honorarnote hat keine.

- **D2a Ablage:** Entity `InvoicePdf` (Tabelle `invoice_pdf`, 1:1 zu `Invoice`, Fremdschlüssel unique, Spalte
  `content` vom Typ `blob` = `bytea`). Eine Migration. Fallstrick: Doctrine liefert `blob` als PHP-Stream, nicht als
  String (`vendor/doctrine/dbal/src/Types/BlobType.php`), beim Lesen also `stream_get_contents()`.
- **D2b Erzeugen:** Dienst `InvoicePdfRenderer` (Twig `admin/invoice/pdf.html.twig` → dompdf, Tabellenlayout, Schrift
  DejaVu Sans aus dompdf für €, ß, Umlaute; Remote-Zugriff aus). Aufgerufen in `InvoiceIssuer` **innerhalb der
  Transaktion**, nachdem die Nummer vergeben ist.
  - Option A (empfohlen): in der Transaktion. Honorarnote und PDF entstehen gemeinsam oder gar nicht. Contra: Das
    Lock hält so lange, wie das Rendern dauert (geschätzt unter 1 s, wird gemessen); bei einer Benutzerin egal.
  - Option B: nach dem Commit. Lock kürzer, aber scheitert das Rendern, gibt es eine Honorarnote ohne PDF.
  - **Entschieden 2026-11-17: Option A.** Scheitert das Rendern, rollt `wrapInTransaction()` alles zurück
    (`vendor/doctrine/orm/src/EntityManager.php`, ORM 3.7.6): keine Honorarnote, kein PDF, keine verbrauchte Nummer
    (die Nummer ist `MAX + 1`, keine Sequenz, also keine Lücke). Der `EntityManager` ist danach geschlossen. Der
    Controller fängt eine eigene `InvoicePdfException`, schreibt den Fehler ins Log (ohne Personendaten), zeigt eine
    Meldung und leitet zurück aufs Formular. Neu auslösen = Formular erneut absenden. Liegt der Fehler im Code,
    hilft das erst nach einem Fix; verloren geht nichts. Gleiches gilt für Gutschriften.
- **D2c Inhalt wie im Muster:** Absender (Name, Titel, Adresse), Empfänger, „Ort, Datum“, „Honorarnote 16/2026“,
  Leistungstext, Betrag („€ 495,-“ bei ganzen Euro, sonst „€ 247,50“), Zahlbar gesamt bzw. mit zwei Raten,
  Überweisungsbitte, Name, Bank, IBAN, BIC, Hinweis zur Umsatzsteuer. Keine Unterschrift. Gutschrift: Titel
  „Gutschrift 17/2026“, Bezug „zu Honorarnote 16/2026“, Betrag, kein Zahlungsteil. Alle Texte als Schlüssel in `admin`.
- **D2d Download:** `/admin/honorarnoten/{id}/pdf` als Anhang (Datei wird gespeichert, Name nach Schema)
  und `/admin/kurse/{id}/honorarnoten.zip` mit allen Honorarnoten und Gutschriften des Kurses. Knöpfe in Detail und
  Liste der Honorarnoten und im Kurs; beim ZIP der Hinweis „3 Teilnahmen haben noch keine Honorarnote“.
  Dateiname `HN16_GU_WiSe_2026_Springer.pdf` (Nachname der Schüler:in, auch wenn die Honorarnote an den Elternteil
  geht; `anforderungen.md`). Gutschrift: `HN17_GU_WiSe_2026_Springer_Gutschrift.pdf` (Mike, 2026-11-17). Umlaute bleiben,
  dazu ein ASCII-Ersatzname für alte Programme (`HeaderUtils::makeDisposition`).
- **D2e Tests und Messung:** Erstellen legt ein PDF an (beginnt mit `%PDF`), Text enthält Nummer und Betrag, Zugriff
  (anonym, falsche Rolle, Admin) für beide Downloads, ZIP-Inhalt und Dateinamen. RAM-Spitze und Dauer beim Rendern
  messen und in `verlauf.md` festhalten.

Bestehende Honorarnoten ohne PDF: In Produktion gibt es keine, Fixtures legen keine an. Daher kein Nachholbefehl;
der Download antwortet ohne PDF mit 404.

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
- **Datenschutz-Information** für Schüler:innen und Eltern (Art. 13 DSGVO), Entwurf zum Prüfen. Entschieden
  2026-11-17 (Mike): eigene Seite **und** PDF, die Eltern werden direkt angesprochen.
- **Speicherdauer in der Datenschutz-Information** (Art. 13 Abs. 2 lit. a DSGVO: Dauer „oder, falls dies nicht möglich
  ist, die Kriterien für die Festlegung dieser Dauer“; Wortlaut bisher nur über dsgvo-gesetz.de gelesen, EUR-Lex
  blockiert automatische Abrufe). Die 7 Jahre der BAO gelten nur für Honorarnoten, Zahlungen und die Zuordnung, nicht
  für Geburtsdatum, Kontaktdaten, Notizen, Unterrichtsinhalte und Schnupperstunden.
  - **A: konkrete Frist** – Pro: klar für Eltern, fester Maßstab für die spätere Löschregel. Contra: bindet Sonja,
    eine verpasste Frist ist ein Verstoß.
  - **B: nur ein Kriterium** („solange für den Unterricht erforderlich“) – Pro: zulässig, legt sich nicht fest.
    Contra: vage, kein Anhaltspunkt für die Löschregel.
  - **Entschieden 2026-11-17 (Mike): A, bis zum Ende des Folgesemesters** nach dem letzten Kurs (bzw. der letzten
    Schnupperstunde). Honorarnoten und Zahlungen 7 Jahre ab Schluss des Kalenderjahres.
  - **F1 Seite** `/datenschutz/unterricht` (freigegeben 2026-11-17): Texte in `content.de.yaml` unter
    `privacy_lessons.*`, Rechte aus `privacy.rights.body` übernommen; verlinkt von `/datenschutz` und `/unterricht`.
    Ein Text für Eltern und erwachsene Schüler:innen; Mike nicht namentlich, sondern als IT-Dienstleister;
    Steuerberaterin Mag.iur. Ulla Kröll als Empfängerin. Erledigt 2026-11-17.
  - **F2 PDF** `/datenschutz/unterricht.pdf` aus demselben Template mit dompdf, bei jedem Aufruf neu erzeugt (nicht
    gespeichert); HTML → PDF in einen gemeinsamen Dienst mit `InvoicePdfRenderer`; Link im Admin bei den
    Schüler:innen. Alternative: fertiges PDF im Repo (Text doppelt, läuft auseinander). Erledigt 2026-11-17; abweichend
    vom Plan einmal erzeugt und im App-Cache gehalten bis zum nächsten Deploy (öffentliche Adresse, dompdf ~0,1 s und
    11 MB je Aufruf), im Debug-Modus jedes Mal neu. Text in `page/_privacy_lessons_sections.html.twig` für Seite und PDF.
- **Aufbewahrungsfrist** der Honorarnoten in der BAO nachlesen, Lösch-/Anonymisierungsregel festhalten.
  Nachgelesen 2026-11-13 (RIS, § 132 BAO): Abs. 1 „Bücher und Aufzeichnungen sowie die zu den Büchern und
  Aufzeichnungen gehörigen Belege sind sieben Jahre aufzubewahren“, darüber hinaus so lange, wie sie für anhängige
  Abgabenverfahren von Bedeutung sind; die Frist läuft für Bücher und Aufzeichnungen „vom Schluß des Kalenderjahres“.
  Abs. 2: Aufbewahrung auf Datenträgern zulässig, wenn die Wiedergabe vollständig, geordnet, inhaltsgleich und bis zum
  Ende der Frist jederzeit gewährleistet ist (spricht für Backups der Datenbank samt PDFs, Abschnitt F).
  Die Lösch- und Anonymisierungsregel daraus steht als offener Punkt im Projektstand.

### F3 Automatische Löschung (Plan freigegeben 2026-11-17)

Mike, 2026-11-17: Die App soll Sonja Arbeit abnehmen, daher wird schon **vor dem Echtbetrieb** automatisch gelöscht,
nicht von Hand. Danach B5 (Passkeys), dann Backups ohne Mikes Mac. Grundlage sind die Fristen aus F (Datenschutz-
Information, `/datenschutz/unterricht`).

**Was gelöscht wird** (je Schüler:in):

| Wann | Was passiert |
|---|---|
| Frist der übrigen Daten abgelaufen, **keine** Honorarnote | Schüler:in mit Teilnahmen, Einheiten, Schnupperstunden löschen |
| Frist abgelaufen, Honorarnote noch aufzubewahren | **Personendaten löschen** (nicht „anonymisieren“: Mike, 2026-11-17, ein Name ist nicht anonym). Es bleibt nur, was die Honorarnote selbst ist (Empfänger:in und Betrag als Kopie in `invoice`, PDF, Zahlungen) und die Teilnahme als Bindeglied ohne Person. Einheiten und Schnupperstunden löschen. Umsetzung siehe Frage 3 |
| Alle Honorarnoten älter als 7 Jahre ab Schluss des Ausstellungsjahres | Zahlungen, PDFs, Gutschriften, Honorarnoten, Teilnahmen und die Schüler:in löschen |
| Elternteil ohne Schüler:in (danach) | Elternteil löschen |

Kurse und Ferien bleiben (keine Personendaten). Benutzerkonten, Sessions und Passwort-Links sind nicht betroffen.
Was die Website-Formulare per Mail schicken, liegt in Sonjas Postfach, nicht in der App (bleibt Handarbeit).

**Letzte Aktivität** einer Schüler:in: die späteste Einheit (auch geplante) oder Schnupperstunde. Ohne beides zählt das
Anlegedatum; dafür braucht `student` eine neue Spalte `created_at` (Migration; in Produktion gibt es noch keine
Schüler:innen).

**Schritte:**

- **F3a Regel und Anzeige:** Dienst `RetentionPolicy` berechnet je Schüler:in „Daten bis“ und „Honorarnoten bis“.
  Die Schülerseite zeigt beides, das Dashboard nennt, wer in den nächsten 30 Tagen gelöscht wird. Migration
  `student.created_at` und `invoice_pdf.file_name`. Tests für die Fristen. **Erledigt 2026-11-17.**
- **F3b Befehl** `app:privacy:purge` (`--dry-run` zählt nur), alles in einer Transaktion, Ausgabe nur Anzahlen, keine
  Namen. Migration: `enrollment.student_id` nullable; Templates zeigen „Person gelöscht“. Tests für Löschen,
  Personendaten löschen bei aufzubewahrender Honorarnote, 7 Jahre, Elternteile. **Erledigt 2026-11-17.** Dazu: Die
  7 Jahre zählen je Teilnahme (Honorarnote und Gutschriften gemeinsam, bis zum Ende des Jahres der letzten); eine
  Teilnahme ohne Person hat eine schreibgeschützte Seite und bekommt keine Einheiten mehr.
- **F3c Server:** Eintrag in der crontab von root wie S8, täglich um 03:30 (Mike, 2026-11-17), `deployment.md` S12.
  **Erledigt 2026-11-17** (Mike, `--dry-run` am Server geprüft).

**Fragen:**

Antworten Mike, 2026-11-17: **1 feste Grenzen (A), 2 so lassen, 3 Teilnahme ohne Person (A), 4 keine Mail (A), 5 passt.**
Dazu: Der Dateiname der Honorarnote wird beim Erstellen mit dem PDF gespeichert (`invoice_pdf.file_name`), statt beim
Download aus dem aktuellen Nachnamen gebildet zu werden; so bleibt er nach dem Löschen der Personendaten gleich.

1. **Wann endet ein Semester?** Der Kurs kennt Beginn und Ende, das Folgesemester gibt es zum Löschzeitpunkt aber oft
   noch nicht in der App.
   - **A (empfohlen): feste Grenzen.** Wintersemester = September bis Februar, Sommersemester = März bis August.
     Letzte Aktivität im Wintersemester → gelöscht nach dem 31. August, im Sommersemester → nach dem 28./29. Februar.
     Pro: vorhersehbar, steht so in der Information. Contra: zwischen 6 und 12 Monaten Abstand.
   - B: letzte Aktivität + 6 Monate. Pro: einfach. Contra: passt nicht genau zu „Ende des Folgesemesters“.
2. **Pause von einem Semester:** Wer im Wintersemester war, im Sommer pausiert und im Herbst wiederkommt, ist dann
   schon gelöscht (31. August), Sonja müsste die Person neu eintragen. Soll die Frist ein Semester länger sein („Ende
   des übernächsten Semesters“, Text der Information ändern) oder so bleiben?
3. **Wie bleibt die Verbindung zur Honorarnote ohne Personendaten?** Die Honorarnote hängt an der Teilnahme, die
   Teilnahme an der Schüler:in (`enrollment.student_id`, NOT NULL). Was auf der Honorarnote steht, muss 7 Jahre
   bleiben (BAO), das ist erlaubt; alles andere zur Person kann weg.
   - **A (empfohlen): Teilnahme ohne Person.** `enrollment.student_id` wird nullable (Migration), die Schüler:in wird
     gelöscht, die Teilnahme behält Kurs, Preis und Honorarnoten. Pro: keine leere „Geisterzeile“, wirklich nichts mehr
     zur Person außer dem Beleg. Contra: Templates müssen „Person gelöscht“ zeigen (der PHP-Code behandelt `null`
     schon, PHPStan erzwingt es); das Formular verlangt weiter eine Person (`Assert\NotNull`).
   - B: Schüler:in-Zeile bleibt, alle Felder geleert und als gelöscht markiert. Pro: kein Schemaumbau der Beziehung.
     Contra: leere Zeilen, die Liste, Suche und Auswahl ausblenden müssen; leicht vergessen.
   - Folge für beide: Der Dateiname der Honorarnote enthält den Nachnamen der Schüler:in (D2d). Danach heißt sie
     `HN17_GU_WiSe_2026.pdf` (ohne Namen). Die Datenschutz-Information sagt derzeit „bleibt nur der Name als
     Zuordnung“; wird angepasst auf „bleibt nur die Honorarnote mit den Angaben darauf“.
4. **Benachrichtigung:**
   - **A (empfohlen):** keine Mail; das Datum steht auf der Schülerseite, das Dashboard nennt, wer in den nächsten
     30 Tagen gelöscht wird (Zeit, eine Teilnahme anzulegen, falls jemand wiederkommt).
   - B: zusätzlich eine Mail nach jedem Lauf mit Anzahlen. Contra: Mail ohne Handlungsbedarf.
5. **„Laufendes Verfahren beim Finanzamt“** (BAO, darüber hinaus aufbewahren): selten; vorerst nicht abgebildet.
   Falls nötig, schaltet Mike den Cronjob ab. Passt das?

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
