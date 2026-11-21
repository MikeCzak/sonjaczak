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

**C4f Ferienwochen im Raster** – erledigt 2026-11-18 (Vorschlag D). Frage von Mike, 2026-11-18: Eine Zeile ist gelb, sobald Ferien die Woche auch nur
an einem Tag berühren (`CourseGrid::breaksIn`). Fixtures: Weihnachtsferien 24.12.–6.1. (Do–Mi) → drei gelbe Wochen,
in der dritten (4.–10.1.) stehen die Freitagseinheiten vom 8.1., der kein Ferientag ist. Richtig geplant, aber
missverständlich.

Erste Optionen (überholt durch Mikes Vorschlag D): A gelb nur, wenn ein Unterrichtstag betroffen ist; B Abzeichen mit
Zeitraum; C Ferientage je Zelle.

**Vorschlag D (Mike): eine Zeile je Unterrichtstag statt je Woche**

- **Zeilen:** je Woche eine Zeile für jeden Wochentag, an dem der Kurs Zeitfenster hat (heute nur Freitag, also gleich
  viele Zeilen wie bisher). Dazu eine Zeile für jeden anderen Tag, an dem eine Einheit liegt (Nachholstunde an einem
  Mittwoch); das ersetzt die heutige Sonderanzeige „mit Wochentag und Uhrzeit“.
- **Erste Spalte „KW“** mit `rowspan` über die Tage der Woche: ISO-Kalenderwoche und Zeitraum (KW 53, 28.12.–3.1.).
  Zweite Spalte: Datum mit Wochentag.
- **Ferien:** Sind **alle Unterrichtstage der Woche** Ferientage (nicht Mo–So: die Herbstferien 27.–31.10. sind
  Di–Sa), steht der Name in der KW-Zelle und die ganze Woche ist gelb. Sonst steht er beim einzelnen Tag (Feiertag,
  schulautonomer Tag, erster oder letzter Ferientag), nur diese Zeile ist gelb. Die Zeilen bleiben sichtbar, damit die
  Lücke im Datum erkennbar ist.
- **Mehrere Unterrichtstage:** Zellen von Zeitfenstern eines anderen Wochentags bleiben leer und grau (kein Unterricht
  an diesem Tag), Doppelstunden werden nur dort angeboten, wo das Zeitfenster zum Tag passt (wie heute).
- **Abgesagte Einheiten:** niedrige rote Zeile unter dem jeweiligen Tag statt unter der Woche.
- **Aktuelle Woche:** blauer Strich wie bisher an der KW-Zelle; zusätzlich der heutige Tag hervorgehoben.
- **Nicht betroffen:** Planung (eine Einheit je Woche und Teilnahme), Zählung, Editor, Endpunkte. Geändert werden
  `CourseGrid`, `GridRow` (Tag statt Woche), `grid.html.twig`, `_course-grid.scss`, die beiden Raster-Tests.
- **Fallstrick `rowspan`:** Ab der zweiten Zeile einer Woche fehlt die erste Zelle. Geprüft 2026-11-18: Weder
  `grid_controller.js` noch `_course-grid.scss` wählen Zellen nach Position, beides arbeitet mit Klassen und
  `data-`Attributen.

Pro: Ferien sind eindeutig, weil sie einen Tag betreffen oder nicht; Nachholstunden an anderen Tagen bekommen ihre
eigene Zeile. Contra: bei mehreren Unterrichtstagen viele graue Zellen (bei zwei Tagen die Hälfte); etwas mehr Umbau
als A.

Antworten Mike, 2026-11-18: **D umsetzen; 2 gelbe Tageszeilen; 3 so lassen, aber mit Hinweis beim Verschieben auf
einen Ferientag.** Umgesetzt: `GridWeek` (neu), `GridRow` je Tag, Hinweis als gelbe Meldung in
`LessonController::edit()`; Datum in der Zelle entfällt (steht in der Zeile).

**Fragen:**

1. Vorschlag D so umsetzen?
2. Ganze Ferienwoche: alle Tageszeilen gelb anzeigen (Empfehlung, Datum bleibt sichtbar) oder zu einer Zeile
   zusammenfassen?
3. Einheit von Hand auf einen Ferientag verschieben (Formular „Einheit bearbeiten“): so lassen (Empfehlung, mit D
   steht sie sichtbar in einer gelben Zeile; Sonja kann in den Ferien bewusst eine Stunde ausmachen), Hinweis beim
   Speichern oder Sperre?

**C4g Raster als Zeitachse, mit Schnupperstunden** – erledigt 2026-11-18 (Wunsch Mike, Plan 2026-11-18). Ersetzt den ersten
Entwurf (Schnupperstunden als eigene Spalte A oder im Zeitfenster B).

Anlass:

- Schnupperstunden stehen als Liste über dem Raster und werden leicht übersehen. Für Sonja sind sie gleich viel Arbeit
  wie reguläre Einheiten.
- Seit C4f gibt es eine Zeile je Tag. Der Spaltenkopf mit Uhrzeit, Name, Zählung und „Nachholstunde fällig“ passt
  nicht mehr, sobald es mehr als einen Unterrichtstag gibt.
- Do 15:00 und Fr 15:30 stünden in Spalten untereinander, obwohl sie zeitlich versetzt sind.

Mikes Vorgaben:

- Schnupperstunden kommen dorthin, wo sie zeitlich liegen (Option B), Überschneidungen sichtbar.
- Uhrzeit, „die wievielte Einheit“ und „Nachholstunde fällig“ stehen in der Box.
- Der Name verlinkt auf die Seite der Teilnahme; der Rest der Box öffnet den Editor.
- Statt Spalten eine Zeitachse je Tag: Breite = Dauer, Position = Beginn, gestrichelte Stundenlinien dahinter.

**Aufbau**

- **Tabelle bleibt** mit den Spalten KW (`rowspan`) und Tag wie in C4f. Statt der Zeitfenster-Spalten gibt es eine
  dritte Zelle je Zeile: die Zeitachse des Tages. Der Kopf zeigt darüber die vollen Stunden (14:00, 15:00 …).
- **Achse für den ganzen Kurs gleich:** von der frühesten Beginnzeit bis zum spätesten Ende aller Einheiten,
  Schnupperstunden und Zeitfenster im Raster, auf volle Stunden gerundet. So steht jede Uhrzeit in jeder Zeile an
  derselben Stelle.
  - Preis: Ein Ausreißer (Nachholstunde um 19:00) macht alle Zeilen breiter.
- **Maßstab fest** in Pixel pro Minute, Vorschlag 3 px (50 Minuten ≈ 150 px), am Prototyp nachschärfen. Ist das Raster
  breiter als der Bildschirm, wird es waagrecht gescrollt. KW und Tag bleiben dabei links stehen (`position: sticky`).
- **Stundenlinien:** gestrichelt als Hintergrund der Zeitachse (`repeating-linear-gradient`, Abstand 60 Minuten). Die
  Achse beginnt immer zu einer vollen Stunde, also auch die erste Linie.

**Boxen**

- **Inhalt einer Einheit:**
  - Zeile 1: Uhrzeit und Zählung, z. B. „15:00 · 7/15“.
    Seit 2026-11-20 mit Endzeit (Mike): „15:00–15:50 · 7/15“ (`GridItem::endTime()`).
  - Zeile 2: Name als Link auf die Teilnahme (`admin_enrollment_show`).
  - Darunter wie bisher Abzeichen (Nachholstunde für …, aus Schnupperstunde, Nachholstunde fällig), Minuten bei
    abweichender Dauer und die Notiz.
  - Was nicht in die Breite passt, wird abgeschnitten; der volle Text steht im Tooltip.
- **Zählung „7/15“:**
  - 7 = die wievielte zählende Einheit der Teilnahme, nach Beginnzeit (Regel aus `Lesson::counts()`: Absagen durch
    Sonja zählen nicht, Absagen durch die Schüler:in schon).
  - 15 = Anzahl der Teilnahme (C7). Eine nicht zählende Einheit bekommt keine Nummer.
  - Berechnet einmal je Teilnahme in `CourseGrid`, nicht in Twig.
- **Klickflächen:** Die ganze Box ist ein Link auf „Einheit bearbeiten“; mit JavaScript öffnet er den Editor (wie
  heute). Der Name ist ein eigener Link darüber.
  - Zwei verschachtelte `<a>` sind in HTML nicht erlaubt. Darum liegen beide Links nebeneinander: Der Link zur Einheit
    deckt die Box mit einem Pseudo-Element ab (Bootstrap `.stretched-link`, `vendor/twbs/bootstrap/scss/helpers/_stretched-link.scss`).
    Der Namenslink liegt mit `position: relative` und höherem `z-index` darüber.
- **Schnupperstunde:** eigene Farbe, gestrichelter Rahmen, Aufschrift „Schnupperstunde“, Name. Die ganze Box verlinkt
  auf die Seite der Schnupperstunde (`admin_trial_edit`), kein Editor, keine Zählung. An einem Tag ohne Unterricht
  bekommt sie eine eigene Zeile, wie eine Nachholstunde. Die Liste über dem Raster entfällt.
- **Überschneidung:** Liegen zwei stattfindende Termine (Einheiten ohne Absage, Schnupperstunden) am selben Tag
  zeitlich übereinander, kommen sie auf zwei Spuren untereinander und die Zeile wird höher. Beide bekommen einen roten
  Rahmen und den Tooltip „überschneidet sich mit …“.
  - Die Spuren rechnet der Server aus: nach Beginn sortiert, jede Box auf die erste freie Spur. Das ist
    deterministisch und im Unit-Test prüfbar.
  - Ein Nachbar, der genau dann beginnt, wenn der andere endet (15:50/15:50), ist keine Überschneidung.
- **Platzhalter „frei“:** Fällt in ein Zeitfenster einer Teilnahme an einem Unterrichtstag ohne Ferien keine
  stattfindende Einheit, steht dort ein gestrichelter Platzhalter. Das ist der Fall, wenn die Einheit abgesagt,
  gelöscht oder verschoben wurde. Er bietet wie heute die Doppelstunde an, also dem Nachbarn davor „danach“ und dem
  Nachbarn danach „davor“. Ob sie passt, prüft weiter der Server (`LessonController::double()`).
  - Vor dem Beginn und nach dem Ende einer Teilnahme gibt es keinen Platzhalter.
  - Liegt eine Schnupperstunde im freien Fenster, gibt es keinen Platzhalter, also auch kein Doppelstunden-Angebot.
- **Abgesagte Einheiten** bleiben in der niedrigen roten Zeile unter dem Tag, auf derselben Achse an ihrer Zeit.

**Editor**

- Heute wird er in die Zelle eingefügt und macht die Spalte breiter. Auf der Zeitachse würde das die Boxen
  verschieben. Er erscheint deshalb als schwebendes Feld unter der Box (absolut positioniert im Zeitachsen-Container,
  nach rechts begrenzt).
- Schließen mit „Abbrechen“, Escape oder Klick daneben; Fokus zurück auf die Box.
- Inhalt und Endpunkte bleiben gleich.
- Bootstrap-Popover wäre möglich: Der Admin lädt `app` und damit Bootstrap 5.3.8 mit Popper 2.11.8
  (`importmap.php`, `templates/admin/base.html.twig`).
  - Empfehlung trotzdem ein eigenes Element. Das Popover reinigt HTML-Inhalt nach einer Liste erlaubter Elemente.
    Ob Buttons und Textarea darauf stehen, habe ich nicht nachgelesen; sonst müsste man die Liste erweitern.
  - Die Stimulus-Aktionen im geklonten Editor funktionieren heute schon. Es ändert sich nur, wo er eingefügt wird.

**Technik**

- **Position:** Jede Box bekommt `style="--start: 60; --length: 50; --lane: 0"` (Minuten ab Achsenbeginn, Spur).
  Die SCSS rechnet `left`, `width` und `top` mit `calc()` und `--minute-width` aus.
  - Geprüft: Eine Content-Security-Policy, die Inline-Styles verbieten würde, gibt es nicht.
  - Alternative: CSS-Grid mit einer Spalte je Minute (`grid-column: 61 / span 50`). Das Grid könnte die Spuren auch
    selbst setzen (`grid-auto-flow: dense`). Dieses Verhalten müsste ich vor dem Einsatz gegen die Spezifikation
    prüfen, und es wäre schwer zu testen. Darum nur, falls die absolute Positionierung Probleme macht.
  - Flexbox wie in Mikes Skizze: Abstände bräuchten Leer-Elemente, und Überschneidungen gingen nicht.
- **Modell:**
  - `CourseGrid` baut statt Spalten je Zeile eine Liste von `GridItem` mit Art (Einheit, Schnupperstunde, frei),
    Minuten, Spur, Überschneidungen und Nummer. Die Achse kommt als `GridAxis` mit Beginn, Ende und vollen Stunden.
  - `GridColumn`, `columnFor()` und die Zellen je Spalte entfallen. Die Zeitfenster der Teilnahmen bleiben nur noch
    für die Platzhalter.
  - Die Reihenfolge im HTML ist nach Uhrzeit sortiert, damit Tastatur und Screenreader sie in der Zeitfolge
    durchgehen.
- **Unverändert:** Planung, Zählung, Endpunkte und CSRF, Ferien und Zeilen aus C4f, das Laden der Schnupperstunden
  (`TrialLessonRepository::findBetween`).
- **Tests:**
  - `tests/Course/CourseGridTest.php`: Achse, Positionen, Spuren und Überschneidung, Platzhalter, Zählung,
    Schnupperstunde an einem anderen Tag.
  - `tests/Admin/CourseGridTest.php`: Links in der Box, keine Liste mehr oben.
  - Dazu ein Blick im Browser (Headless Chrome) auf Breite, Scrollen und Editor.
- **Doku:** `erklaerungen/18-kursraster.md` (Zeitachse, Spuren, warum absolute Positionierung), `rundgang-admin.md`,
  Hilfetext unter dem Raster.

**Umfang:** größer als C4f. `CourseGrid`, Template, SCSS, Editor-JavaScript und beide Tests werden großteils neu
geschrieben. Ein Schritt, zwei Commits (Modell mit Unit-Tests, dann Darstellung mit Editor).

**Fragen:**

1. Maßstab: fest 3 px pro Minute mit waagrechtem Scrollen (Empfehlung, Boxen bleiben lesbar) oder auf die
   Bildschirmbreite gestaucht (kein Scrollen, auf schmalen Bildschirmen sehr enge Boxen)?
2. Wo steht „Nachholstunde fällig“?
   - A (Empfehlung): in der Box der **nächsten** geplanten Einheit der Teilnahme. Dort sieht Sonja es in der
     aktuellen Woche.
   - B: in jeder Box der Teilnahme.
   - C: in der letzten Box der Teilnahme, wo eine Nachholstunde angehängt würde.
3. Platzhalter „frei“ in Zeitfenstern ohne stattfindende Einheit, mit dem Angebot für die Doppelstunde (Empfehlung: ja)?
4. Abgesagte Einheiten weiter in der roten Zeile unter dem Tag (Empfehlung) oder blass in der Tageszeile? Blass bräuchten
   sie eigene Spuren und machten Zeilen höher.

Antworten Mike, 2026-11-18: **1–4 wie empfohlen, die Zeile für Abgesagte aber mit reduzierter Höhe.** Umgesetzt:
`GridAxis`, `GridItem` (neu), `GridRow` mit Boxen und Spuren, `GridColumn` entfällt; Partials `_grid_track` und
`_grid_item` statt `_grid_lesson`. Die Box in der Absage-Zeile ist eine Textzeile hoch und zeigt nur Uhrzeit, Name und
Art der Absage (mit Zählung passte der Name nicht hinein).
Nachtrag (Mike, 2026-11-18): Jedes Abzeichen „Nachholstunde fällig“ (Raster, Teilnahme, Kursseite, Dashboard) führt
zu „Nachholstunde eintragen“ (`_make_up_due.html.twig`).

**C4h Einheiten im Raster verschieben (Ziehen und Ablegen)** – erledigt 2026-11-20 (Wunsch Mike, Plan 2026-11-20).

Anlass: Eine Einheit verschieben heißt heute Box → Editor → „Beginn, Dauer“ → Einzelseite → Beginn ändern →
speichern; danach landet Sonja auf der Teilnahme, nicht im Raster. Ziehen ist die Abkürzung dafür. Die Doppelstunde
im Platzhalter „frei“ bleibt wie sie ist.

Mikes Vorgaben: Einrasten im 5-Minuten-Raster; Ablegen nur, wenn nichts kollidiert.

**Ablauf**

- Box greifen und ziehen. Erst ab einer Bewegung von 5 px beginnt das Ziehen; darunter bleibt es ein Klick und öffnet
  den Editor wie heute.
- Während des Ziehens steht eine Vorschau an der eingerasteten Stelle mit der neuen Zeit („15:35–16:25“). Kollidiert
  sie, ist sie rot mit „überschneidet sich mit …“.
- Loslassen an freier Stelle: Anfrage an den Server, danach lädt die Seite neu (wie bei allen anderen Änderungen im
  Raster: Spuren, Platzhalter „frei“ und Doppelstunden-Angebote rechnet der Server). Loslassen auf einer Kollision,
  neben der Achse oder Escape: Die Box springt zurück, nichts wird gespeichert.
- **Einrasten:** Der Beginn landet auf vollen 5 Minuten der Uhrzeit. Weil die Achse immer zu einer vollen Stunde
  beginnt (C4g), sind das auch volle 5 Minuten ab Achsenbeginn. Eine Einheit um 13:42 landet auf 13:40 oder 13:45.
  Die Dauer bleibt.
- Die Box bleibt innerhalb der Achse (nicht vor deren Beginn, nicht über deren Ende hinaus).
- Kein „Rückgängig“: Zurückziehen geht, solange der alte Platz frei ist.

**Kollision** (Vorschau im Browser und Prüfung am Server nach derselben Regel; maßgeblich ist der Server)

- Stattfindende Einheiten desselben Kurses am selben Tag (wie `LessonRepository::findOverlapping`, also ohne
  abgesagte) und Schnupperstunden am selben Tag. Für Schnupperstunden braucht es eine neue Abfrage in
  `TrialLessonRepository`; die Doppelstunde prüft sie heute nicht.
- Beginnt die eine, wenn die andere endet (15:50/15:50), ist das keine Kollision (wie in C4g).
- Der Server antwortet bei Kollision mit 409 und einem Text, ohne Nachfrage (anders als „Absage aufheben“: dort wird
  nach Rückfrage gelöscht, hier nie).

**Server**

- Der bestehende PATCH-Endpunkt (`admin_lesson_patch`) bekommt den Schlüssel `startsAt`. Prüfungen: Einheit nicht
  abgesagt und noch nicht gehalten (siehe Frage 2), Minute durch 5 teilbar, gleicher Tag (bei Frage 1 A), keine
  Kollision.
  - Alternative: eigener Endpunkt `POST …/verschieben`. PATCH passt besser, weil sich nur ein Teil der Einheit ändert,
    und Token, JSON-Lesen und Antwort sind schon da.
  - Format der Zeit beim Umsetzen gegen die gespeicherte Zeit (Zeitzone) prüfen.
- Die Einzelseite (`LessonType`) bleibt ohne Kollisionsprüfung: Dort kann Sonja bewusst überschneiden, das Raster
  zeigt es rot.

**Fragen**

1. Wohin darf gezogen werden?
   - **A (Empfehlung): nur innerhalb des Tages, also waagrecht.** Der häufige Fall ist „diese Woche 15 Minuten
     später“. Pro: einfach, kein senkrechtes Scrollen beim Ziehen, Zählung „7/15“ bleibt gleich. Contra: Auf einen
     anderen Tag weiter über die Einzelseite (oder „Nachholstunde eintragen“).
   - B: auch auf jede andere Tageszeile (andere Woche, anderer Unterrichtstag, Ferientag mit Hinweis wie in C4f).
     Pro: alles mit der Maus. Contra: Das Raster ist rund 20 Wochen lang, beim Ziehen muss die Seite selbst scrollen
     (eigener Code); man legt leicht in der falschen Woche ab; Tage ohne eigene Zeile (ein Mittwoch ohne Unterricht)
     gehen trotzdem nicht. Lässt sich später auf A aufsetzen.
2. Welche Boxen lassen sich ziehen?
   - **Empfehlung: nur geplante Einheiten** (noch nicht gehalten, nicht abgesagt), auch Nachholstunden. Nicht:
     gehaltene (Vergangenheit; zur Korrektur weiter über die Einzelseite), abgesagte (die rote Zeile zeigt, wann sie
     gewesen wären), Schnupperstunden (eigene Seite, eigene Entität), Platzhalter „frei“.
   - Alternative: auch gehaltene Einheiten oder Schnupperstunden (Letztere bräuchten einen eigenen Endpunkt).
3. Technik im Browser?
   - **A (Empfehlung): Pointer Events** (`pointerdown`, `pointermove`, `pointerup`) mit eigenem Code, keine
     Bibliothek. Pro: Einrasten, Vorschau und die Grenze Klick/Ziehen ganz in der Hand; wenige Zeilen; Maus, Touchpad
     und Stift mit demselben Code. Contra: alles selbst geschrieben.
   - B: HTML Drag and Drop (`draggable`, `dragstart`, `drop`). Pro: eingebaut. Contra: Der Browser zeigt ein eigenes
     Geisterbild unter dem Mauszeiger statt einer eingerasteten Vorschau; Unterstützung auf Touch-Geräten nicht
     nachgelesen.
   - C: Bibliothek (z. B. interact.js; Version und Lizenz vor dem Einbau prüfen). Pro: Einrasten eingebaut. Contra:
     eine Abhängigkeit mehr für wenig eigenen Code.
4. Auf dem Handy?
   - **Empfehlung: kein Ziehen mit dem Finger**, Antippen öffnet weiter den Editor. Mit dem Finger würde das Ziehen
     einer Box mit dem waagrechten Scrollen des Rasters kollidieren; abhelfen ließe sich nur mit „lange drücken, dann
     ziehen“ (mehr Code, schwer zu entdecken).
   - Alternative: lange drücken (etwa eine halbe Sekunde), dann ziehen.

Antworten Mike, 2026-11-20: **1–4 wie empfohlen.** Dazu (Auslegung): Der neue Beginn darf nicht in der Vergangenheit
liegen, sonst wäre die Einheit nach dem Ablegen „gehalten“.

5. Nach dem Ablegen neu laden oder nur die Zeile ersetzen? (Frage Mike, 2026-11-20)
   - **A (Empfehlung): Der Server schickt die neu gezeichnete Tageszeile mit.** Weil nur innerhalb des Tages gezogen
     wird (Frage 1 A) und die Box in der Achse bleibt, ändert sich nichts außerhalb dieser Zeile: Spuren,
     Überschneidungen, der neue Platzhalter „frei“ mit Doppelstunden-Angeboten liegen alle dort; Achse, Zeile der
     Abgesagten, Zählung und „Nachholstunde fällig“ bleiben gleich. Nötig: Der Endpunkt baut das Raster mit
     `CourseGrid::build()` wie die Seite, nimmt die Zeile des Tages und rendert `_grid_track.html.twig` mit
     `renderView()` in die JSON-Antwort; das JavaScript schließt den Editor, ersetzt das `div.grid-track` und setzt den
     Fokus auf die verschobene Box. Stimulus erkennt `data-action` in neu eingefügtem HTML selbst (beim Umsetzen in der
     Stimulus-Doku nachlesen). Pro: kein Springen, Scrollposition bleibt, eine Darstellung (Twig). Contra: etwas mehr
     Code im Endpunkt; ein Test, der das HTML der Antwort prüft.
   - B: Turbo Streams (`symfony/ux-turbo`, nicht installiert). Contra: neue Abhängigkeit, die ohne Abschalten auch die
     Navigation im ganzen Admin übernimmt (Turbo Drive), für eine Stelle.
   - C: Nur im Browser verschieben (`--start` setzen) und Spuren, Überschneidungen, „frei“ und Doppelstunden in
     JavaScript nachrechnen. Contra: dieselbe Logik wie in `CourseGrid` ein zweites Mal, die beiden laufen
     auseinander.
   - Absage, Löschen und Doppelstunde laden weiter neu: Sie ändern die Zählung über viele Wochen und füllen am Ende auf.

Antwort Mike, 2026-11-20: **5 wie empfohlen.** Umgesetzt: PATCH mit `startsAt` (`LessonController::move()`,
`renderRow()`), `TrialLessonRepository::findOverlapping()` und `findInCourse()`, `TrialLesson::getEndsAt()`,
`Grid::rowOn()`, `LessonDescriber::describeTrials()`; `data-day`, `data-axis` an der Zeile; Ziehen in
`grid_controller.js` (`grab`, `dragMove`, `drop`). Tests in `tests/Admin/LessonPatchTest.php`, Ziehen im Headless
Chrome geprüft.

**Barrierefreiheit:** Ziehen geht nur mit dem Zeiger. Mit der Tastatur bleibt der Weg über Editor und Einzelseite.

**Tests:** Funktionstest für PATCH mit `startsAt` (verschoben; Kollision mit Einheit und mit Schnupperstunde;
Grenze 15:50/15:50; keine 5 Minuten; gehaltene, abgesagte; anderer Tag; ohne Token). Für JavaScript gibt es keine
Tests im Projekt; Prüfung im Browser.

**Umfang:** mittel, zwei Commits (Endpunkt mit Tests, dann Ziehen im Raster). Doku: `erklaerungen/18-kursraster.md`,
`rundgang-admin.md`, Hilfetext unter dem Raster.

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
