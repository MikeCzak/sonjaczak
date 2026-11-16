# Projektstand: sonjaczak.com

Stand: 2026-11-16. **Die eine Datei für alles Offene.** Reihenfolge: was jetzt auf Mike wartet, was später zu klären
ist, Termine, Fortschritt, getroffene Entscheidungen, Server. Was wann im Detail passiert ist: `verlauf.md`.

**Verweise:** Schritte des Umsetzungsplans heißen „`umsetzung-admin.md` C4“ (`docs/planung/umsetzung-admin.md`),
Schritte der Server-Einrichtung „`deployment.md` S8“. Ist ein Punkt geklärt, verschwindet er hier und landet unter
„Getroffene Entscheidungen“ (oder im Plan, falls er nur einen Schritt betrifft).

## Jetzt (wartet auf Mike)

**So beantworten:** direkt hier in die Zeile „Antwort“ schreiben (oder im Chat). Claude überträgt die Antwort danach
in Plan bzw. Entscheidungen und entfernt den Punkt. Die Optionen mit Pro und Contra stehen im verlinkten Abschnitt.

Keine Frage offen.

**Todos für Mike (keine Frage offen):**

- [x] Sonjas Konto über die Benutzerverwaltung anlegen (`/admin/benutzer`, Einladungslink).
- [x] Absenderdaten in Produktion unter `/admin/einstellungen` eintragen. Die Daten liegen lokal in
  `claude_references/absenderdaten-sonja.md` (nicht im Repo, das Repo ist öffentlich).
- [x] Cronjob für überfällige Honorarnoten eintragen (`deployment.md` S8, freigegeben 2026-11-14).
- [x] **Backups einrichten** (Repo-Teil fertig und lokal getestet 2026-11-14; eingerichtet und Rücksicherung
  erfolgreich getestet 2026-11-15, Erklärung `erklaerungen/16-backups.md`), in dieser Reihenfolge:
  1. Mac: age und Schlüssel, SSH-Key (`entwicklung.md`, „Backups“, Schritte 1–2)
  2. Server: `deployment.md` S11
  3. Mac: Skript und LaunchAgent (`entwicklung.md`, „Backups“, Schritt 4)
  4. Test der Rücksicherung (`deployment.md` S11). **Danach dürfen echte Daten eingetragen werden.**

## Später zu klären

Jeder Punkt mit dem Tag, an dem er entstand, und dem Schritt, aus dem er stammt. Die Begründung steht in der
verlinkten Erklärung.

### Mit Sonja

- [ ] **Teilnahme beenden** (2026-11-16, aus `umsetzung-admin.md` C4, Frage 10): Hört eine Schülerin mitten im Semester
  auf, braucht es einen eigenen Ablauf, nicht das Löschen einzelner Einheiten (das füllt am Kursende wieder auf). Zu
  klären, bevor geplant wird: Was passiert mit den zukünftigen Einheiten (löschen, als abgesagt stehen lassen)? Mit der
  Honorarnote (bleibt sie voll, Gutschrift für nicht gehaltene Einheiten, Rückzahlung)? Mit fälligen Nachholstunden?
  Bleibt die Teilnahme sichtbar (z. B. „beendet am …“) und zählt sie im Dashboard und Export mit?
- [ ] **Auffüllen nach neuen Ferien** (2026-11-13, C3; seit 2026-11-16 werden alle Einheiten im Zeitraum gelöscht, nach
  Rückfrage): Für entfallene Einheiten kommen am Ende neue dazu (bis zum Kursende).
- [ ] **Ferien löschen gibt auch gelöschte Einheiten zurück** (2026-11-16, `umsetzung-admin.md` C4-0): Wird eine Ferienwoche
  gelöscht, bekommt jede Teilnahme an den frei gewordenen Tagen ihre Einheit wieder, wenn sie in der Woche keine hat.
  Hatte Sonja dort vorher eine Einheit absichtlich gelöscht, kommt auch diese zurück (der Grund wird nicht gespeichert).
  Im Test: Petras gelöschte Einheit kam mit. Selten; sonst im Raster wieder löschen.
- [ ] **Wochentermin fest** (2026-11-13, C3): Nach dem Anlegen lassen sich Wochentag und Uhrzeit einer Teilnahme nicht
  mehr ändern, nur einzelne Einheiten verschieben. Ein Umzug aller geplanten Einheiten wäre machbar.
- [ ] **Fälligkeit ohne Vorschlag** (2026-11-13, D1, `erklaerungen/13`): „Zahlbar bis“ muss eingetragen werden. Eine
  feste Frist als Vorschlag wäre eine Einstellung.
- [ ] **Betrag beim Erstellen nicht änderbar** (2026-11-13, D1): kommt aus der Teilnahme (dort für Ermäßigungen
  änderbar).
- [ ] **Leistungstext** „Gesangsunterricht im Wintersemester 2026/27“ vorbelegt, änderbar (2026-11-13, D1).
- [ ] **Zahlungen ohne Rückfrage löschbar** (2026-11-13, D3).
- [ ] **Empfänger der Erinnerung** `music@sonjaczak.com` (2026-11-13, D4); eine eigene Einstellung wäre leicht ergänzt.
- [ ] **Dashboard** (2026-11-13, E, `erklaerungen/14`): Zeitraum „nächste 7 Tage“, Gestaltung beim Zeigen nachschärfen.
- [ ] **CSV mit Sonjas Excel prüfen** (2026-11-13, D5): Umlaute, Beträge als Zahl, Datum.
- [ ] **Ansprache „du“** im Admin-Bereich (2026-11-13); bei Konten für Schüler:innen später ggf. anpassen.
- [ ] **Entwürfe der Seitentexte** durchsehen (seit 2026-11-11, Release-Schritt 4): Texte mit `# Draft` in
  `translations/content.de.yaml`, auch Impressum und Datenschutzerklärung (Entwürfe, keine Rechtsberatung).
- [ ] **Regeln für den Unterricht und FAQ** (2026-11-11, `planung/inhalte.md` E42, E44): Texte von Sonja; die
  Abschnitte bleiben bis dahin ausgeblendet.
- [ ] **KI-Bilder durch Fotos ersetzen** (2026-11-12): `hero-wedding.jpg`, `hero-lessons.jpg`, sobald Fotos da sind.

### Mit der Steuerberatung

- [ ] **Gutschrift und Zahlung als ein Konto** (2026-11-13, D3, `erklaerungen/13`): zu zahlen = Betrag minus
  Gutschriften, Rückzahlung als negative Zahlung an der Gutschrift. Passt das so (Antwort 8 im Datenmodell)?
- [ ] **§ 19 Abs. 1 Z 1 EStG** (2026-11-13, D5): Ausnahme für regelmäßig wiederkehrende Einnahmen um den Jahreswechsel;
  der Export ordnet streng nach Zahlungsdatum zu.
- [ ] **Lösch- und Anonymisierungsregel** (2026-11-13, `umsetzung-admin.md` F; **zurückgestellt**, Mike: „für später
  aufheben“). Grundlage: § 132 BAO, 7 Jahre ab Schluss des Kalenderjahres (Zitat in `umsetzung-admin.md` F). Beispiel:
  Honorarnoten ab dem 8. Jahr anonymisieren, Schüler:innen ohne Teilnahmen in Frist löschen. Hängt damit zusammen:
  Schüler:innen mit Teilnahmen sind nicht löschbar (C5).

### Rechtliches

- [ ] **Datenschutz-Information für Schüler:innen und Eltern** (Art. 13 DSGVO, `umsetzung-admin.md` F): Entwurf zum Prüfen,
  **vor den echten Daten**.
- [ ] **Minderjährige im Unterricht** (2026-11-12, Release-Schritt 6): Der Vertrag kommt mit den Erziehungsberechtigten
  zustande; ob die Datenschutzerklärung dazu mehr sagen soll, mit der Rechtslage klären.

- [ ] **Darstellung des Rasters** (2026-11-16, `umsetzung-admin.md` C4b) beim Zeigen mit Sonja prüfen: Gehaltene Einheiten
  grau mit ✓, geplante fett; Ferienwochen gelb, abgesagte Einheiten in einer roten Zeile darunter, aktuelle Woche mit
  blauem Strich. Im Spaltenkopf stehen Zeitfenster, Name und Zählung („14 von 15“). Schnupperstunden im Kurszeitraum
  stehen als Liste über dem Raster (sie gehören zu keinem Kurs). Einheiten, die zu keinem Zeitfenster passen
  (Nachholstunde an einem anderen Tag), stehen in der Spalte ihrer Teilnahme mit Wochentag und Uhrzeit.

- [ ] **Bedienung des Rasters** (2026-11-16, `umsetzung-admin.md` C4d) mit Sonja prüfen: Der Editor macht die Spalte beim
  Öffnen breiter (die Tabelle ordnet sich neu); nach Absage, Doppelstunde oder Löschen lädt die Seite neu. Doppelstunden
  werden in jeder leeren Zelle angeboten, deren Nachbar am selben Tag eine Einheit hat, auch in Wochen ohne Absage
  (z. B. bevor eine Teilnahme beginnt); ob sie passt, prüft der Server.

### Technisch (Mike)

- [ ] **Passkeys** (`umsetzung-admin.md` B5). Zeitpunkt entschieden 2026-11-14: darf auch erst im Echtbetrieb kommen
  (ein gutes Passwort ist nicht per se unsicher), aber **nicht vergessen**: Für Sonja ist der Fingerabdruck viel
  einfacher als z. B. ein Einmalcode (OTP).
- [ ] **Advisory Lock statt Wiederholung** bei der Nummernvergabe (2026-11-13, D1, `erklaerungen/13`): Abweichung vom
  Datenmodell.
- [ ] **BIC nur per Regex** (2026-11-13, C6, `erklaerungen/12`): `Assert\Bic` bräuchte `symfony/intl`.
- [ ] **Mercure und Vulcain** sind im `Caddyfile` aktiv, werden aber nicht genutzt: entfernen oder behalten?
  (2026-11-03, `erklaerungen/03`)
- [ ] **OPcache** (`opcache.memory_consumption = 256`): tatsächliche Belegung messen, sobald die App gewachsen ist,
  ggf. senken (2026-11-03).
- [ ] **Gekürzte IPs in den Server-Logs prüfen** (`docker compose logs php`, 2026-11-11, Release-Schritt 4).
- [ ] **Bootstrap-CSS verkleinern:** nur benutzte Teile importieren (derzeit 237 KB vor gzip, 2026-11-11).
- [ ] **Alte Images in ghcr.io aufräumen** (derzeit kostenlos, laut GitHub-Doku „currently free“, Änderung mit
  1 Monat Vorlauf).
- [ ] **Backups im Ernstfall:** genaue Befehle für das Einspielen auf einem neuen Server ausarbeiten (2026-11-14,
  `planung/backups.md`, „Rücksicherung“). Der Test in die temporäre Datenbank deckt das Einspielen ab, nicht den
  ganzen Ablauf.
- [ ] **Zweite Kopie der Backups außer Haus** (2026-11-15, `erklaerungen/16`, Abschnitt 5): Heute liegen die Sicherungen
  nur am Mac (und in Time Machine, falls eingerichtet). Möglich: Cloud-Speicher mit Push vom Server oder eine externe
  Platte an einem anderen Ort. Auch prüfen: Läuft Time Machine, und ist der private age-Schlüssel ausgedruckt?
- [ ] **Postfächer:** Mail-Hosting für Sonjas Adressen unter sonjaczak.com (`music@` liegt bereits bei IONOS; prüfen, ob
  noch etwas offen ist).
- [ ] **Runner-Label `ubuntu-latest`** (2026-11-15): wechselt laut GitHub-Hinweis im CI-Lauf ab 2026-10-19 schrittweise
  auf Ubuntu 26 (github.com/actions/runner-images/issues/14748). Stört derzeit nicht; ggf. auf `ubuntu-24.04` pinnen,
  damit der Wechsel bewusst passiert.
- [ ] Kandidaten aus der Durchsicht des Templates (2026-11-03):
  - Symfony Language Server aus dem Dev-Image entfernen (lädt ungepinnt die neueste Version, wird nicht genutzt)
  - `composer audit` als CI-Schritt
  - GitHub Actions auf Commit-SHA statt Tag pinnen; zizmor für Workflow-Sicherheit
  - Image-Scan auf bekannte Schwachstellen (`docker scout` oder Trivy) in der CI
  - Ungenutzten `allow-plugins`-Eintrag `php-http/discovery` entfernen

## Termine

- **Upgrade auf Symfony 8.2 vor Ende Jänner 2027** (dann endet der Support für 8.1). Danach 8.3 (Mai 2027) und
  8.4 LTS (laut Release-Regeln voraussichtlich November 2027).
- **Rücksicherung der Backups erneut testen** etwa jährlich (nächstes Mal ca. November 2027) und nach jedem
  PostgreSQL-Upgrade (`deployment.md` S11, „Test der Rücksicherung“).
- **Tokens erneuern:** gh CLI läuft 2027-11-02 ab, Server-Token 2027-11-03 (Erinnerung per Cronjob, `deployment.md` S6).

## Fortschritt

Details zu jedem Schritt: `verlauf.md`. Technische Erklärungen: `erklaerungen/`.

**Phase 1** (2026-11-03): Jeder Push auf `main` läuft durch Tests → Build → Deploy und geht auf https://sonjaczak.com
live. Server eingerichtet und abgesichert, Rollback getestet.

**Erstes Release** (Plan: `planung/seitenstruktur.md`, Abschnitt 10)

- [x] 1. Twig, Übersetzungen, AssetMapper, Bootstrap (2026-11-11)
- [x] 2. Layout, Menü, alle Routen (2026-11-11)
- [x] 3. Design: Themes `classic` (Standard), `modern`, `stage`; Logo als SVG; Slider (2026-11-12)
- [x] 4. Seiten mit Inhalt, Impressum, Datenschutzerklärung (2026-11-11)
- [x] 5. Bilder in mehreren Größen, Scroll-Effekte (2026-11-11)
- [x] 6. Formulare mit Mailversand: Kontakt, Booking, Unterricht (2026-11-12, Plan `planung/formulare.md`)

**Admin-Bereich** (Plan: `planung/umsetzung-admin.md`, freigegeben 2026-11-13)

- [x] A1 Zeitzone, Foundry, Maker, PHPStan mit Entities (2026-11-13)
- [x] B1/B2 Login, Rollen, Sessions in PostgreSQL (2026-11-13, `erklaerungen/08`)
- [x] B3 Passwort vergessen, Mein Konto (2026-11-13, `erklaerungen/08`)
- [x] B4 Benutzerverwaltung mit Einladungslink (2026-11-13, `erklaerungen/08`)
- [ ] B5 Passkeys (zurückgestellt, siehe „Später zu klären“)
- [x] C1 Schüler:innen und Elternteile (2026-11-13, `erklaerungen/09`)
- [x] C2 Kurse und Ferien mit OpenHolidays (2026-11-13, `erklaerungen/10`)
- [x] C3 Teilnahmen, Einheiten, Nachholregel (2026-11-13, `erklaerungen/11`)
- [ ] C4 Kursraster (entschieden 2026-11-14: Stimulus + `fetch`; C4-0 Status umgebaut, C4a Stimulus installiert, C4b Raster nur lesend, C4c Endpunkt, C4d Bedienung, C4-0/C4e Ferien mit Bestätigung und per Woche 2026-11-16; Erklärung C4f folgt)
- [x] C5 Schnupperstunden (2026-11-13, `erklaerungen/11`)
- [x] C6 Einstellungen (2026-11-13, `erklaerungen/12`; Schwellwert durch „Einheiten pro Semester“ ersetzt 2026-11-15)
- [x] D1 Honorarnoten erstellen (2026-11-13, `erklaerungen/13`; Doppelklick-Lücke geschlossen 2026-11-14)
- [ ] D2 PDF (entschieden 2026-11-14: dompdf; noch nicht gebaut)
- [x] D3 Zahlungen und Gutschriften (2026-11-13, `erklaerungen/13`)
- [x] D4 Erinnerung bei überschrittener Frist: Befehl fertig, Cronjob freigegeben, Eintrag siehe „Jetzt“ (2026-11-13, `erklaerungen/13`)
- [x] D5 Einnahmen und CSV-Export (2026-11-13, `erklaerungen/13`)
- [x] E Dashboard (2026-11-13, `erklaerungen/14`)
- [ ] F Vor den echten Daten: Backups **fertig** (eingerichtet, Rücksicherung getestet 2026-11-15, `erklaerungen/16`), Datenschutz-Information und Löschregel (siehe „Später“);
  Aufbewahrungsfrist nachgelesen (2026-11-13)

**Zum Ausprobieren:** `rundgang-admin.md` (lokal mit Fixtures, inklusive was vor dem Zeigen in Produktion passieren
muss).

## Getroffene Entscheidungen

| Bereich | Entscheidung | Begründung |
|---|---|---|
| Hosting | Eigener IONOS-VPS statt Webhosting | VPS ist bereits bezahlt; Webhosting mit SSH wäre erst ab ca. 10 €/Monat verfügbar; volle Kontrolle; Wartung übernimmt Mike |
| Container | Docker Compose lokal und in Produktion | Gleiche Umgebung überall; Workflow bereits bekannt |
| PHP-Server | FrankenPHP über Template dunglas/symfony-docker | Ein Container statt Nginx + PHP-FPM; automatisches HTTPS über Caddy |
| Symfony-Version | 8.1, danach Minor-Upgrades alle 6 Monate bis 8.4 LTS | Neues Projekt ohne Altcode; bis 8.4 LTS nur kleine Minor-Upgrades statt späterem Major-Sprung von 7.4; aktuelle Doku passt. Quelle: symfony.com/releases (8.1: Support bis Jan. 2027) |
| Datenbank | PostgreSQL (Standard des Templates) in eigenem Container, ohne veröffentlichten Port | Kein Umbau des Templates nötig; Docker umgeht ufw für veröffentlichte Ports |
| PostgreSQL-Version | 18 (`postgres:18-alpine`) | Längster Support (bis Nov. 2030, Quelle: postgresql.org/support/versioning); Major-Upgrades erfordern Datenmigration, daher spät wie möglich |
| CI/CD | GitHub Actions, Images in ghcr.io, Deploy per SSH | Kein eigener Runner nötig; Rollback über vorheriges Image-Tag |
| Builds | Images nur in der CI bauen, nie auf dem Server | Server hat ~850 MiB RAM |
| Test-App | smart-ui.mikeczak.com offline genommen | Spart RAM; FrankenPHP kann 80/443 direkt belegen, kein Reverse Proxy nötig |
| Arbeitsumgebung | Ab Repo-Anlage mit Claude Code in der IDE; CLAUDE.md und dieses Dokument liegen im Repo | Direkter Zugriff auf die Codebase; Projektstand wird mit dem Code versioniert |
| Referenzmaterial | `claude_references/` nur lokal, in `.gitignore` (2026-11-11) | Enthält eine echte Honorarnote (DSGVO) und große Originalbilder; ins Repo kommen nur Web-Versionen |
| CSS-Framework | Bootstrap 5.3 mit `symfonycasts/sass-bundle`, Assets über AssetMapper (2026-11-11) | Fertige Komponenten (Navbar, Formulare, Accordion), Erfahrung vorhanden; kein Node nötig. Details: `docs/planung/seitenstruktur.md` |
| Übersetzungen | YAML, Domains `messages` (Zweck-Schlüssel), `content` (Seite.Abschnitt), `validators` (2026-11-11), `admin` (2026-11-13) | Lange Texte lesbarer als XLIFF; `content` wandert später teilweise ins CMS |
| Admin-Oberfläche | Eigene Controller, Forms und Voter, kein EasyAdmin (2026-11-12) | Volle Kontrolle; Mike kennt Symfony-Forms und Voter |
| Daten für Entwicklung | Erfundene Daten aus Fixtures (Zenstruck Foundry, nur `require-dev`); echte Daten nur über die Admin-Masken in Produktion (2026-11-12) | Keine echten Personendaten lokal oder im Repo; Factories auch für Tests |
| Seitenstruktur | Deutsche URLs ohne Sprachpräfix; Impressum als Abschnitt auf `/kontakt`; Ansprache „Sie“, nur Unterricht „Du“; Logo „Sonja Czak“; Google Maps nur als Link (2026-11-11) | Siehe `docs/planung/seitenstruktur.md` |
| Mail-Versand | IONOS-SMTP (`smtp.ionos.de:465`), synchron, Absender `anfrage@sonjaczak.com`, Empfänger `music@sonjaczak.com` (2026-11-11) | Postfach ist bereits bei IONOS, kein weiterer Dienst; kein Messenger-Worker nötig (RAM). Details: `docs/planung/formulare.md` |
| Admin-Planung | Datenmodell, Rollen und Umsetzungsplan freigegeben (2026-11-13) | `planung/datenmodell.md`, `planung/rollen.md`, `planung/umsetzung-admin.md` |
| Vorführung | Kein eigener Demo-Termin: Mike sieht sich die Funktionen lokal an (`rundgang-admin.md`) und zeigt sie Sonja in Produktion (2026-11-13) | Spart eine eigene Demo-Umgebung; Honorarnoten in Produktion verbrauchen aber Nummern |
| PDF-Bibliothek | dompdf (2026-11-14) | HTML aus Twig mit Übersetzungsschlüsseln, LGPL-2.1, läuft im PHP-Container; braucht GD. Optionen: `planung/umsetzung-admin.md` D2 |
| Kursraster | Stimulus + `fetch` (2026-11-14) | Tastaturlastig, viele kleine Änderungen; durchschaubar. Live Components ggf. später fürs Booking-Formular. Optionen: `planung/umsetzung-admin.md` C4 |
| Konten in Produktion | Super-Admin (Mike) per Befehl angelegt; Sonjas Konto über die Benutzerverwaltung mit Einladungslink (2026-11-14) | Sonja setzt ihr Passwort selbst |
| Cronjob überfällige Honorarnoten | Freigegeben, täglich 07:30, ohne Prüfung der Zeitzone (2026-11-14) | Es geht um Tage, nicht Stunden; eine Stunde früher oder später ist egal |
| Unterschrift | Keine Unterschrift auf der Honorarnote; Upload-Feld und Spalte entfernt (2026-11-14) | Nicht nötig (Mike); kein Risiko durch eine Unterschrift in PDFs, Datenbank und Backups |
| Doppelklick bei Honorarnoten | Regeln „eine je Teilnahme“ und „Gutschriften bis zum Betrag“ werden unter dem Lock in der Datenbank erneut geprüft (2026-11-14) | Lücke gefunden bei Mikes Frage zu Sperren; `erklaerungen/15-sperren.md` |
| Backups | Plan `planung/backups.md` mit allen Empfehlungen freigegeben (2026-11-14): age, Verschlüsselung am Server, privater Schlüssel im Passwort-Manager, 14 Tage + 12 Monate, täglich 12:00 per launchd, Rücksicherungstest in temporärer Datenbank am Server, Sessions ohne Inhalt | Begründungen im Plan |
| Passkeys (B5) | Dürfen nach dem Start des Echtbetriebs kommen, bleiben aber eingeplant (2026-11-14) | Passwort reicht vorerst; Fingerabdruck ist für Sonja einfacher als ein Einmalcode |
| Absenderdaten | Nur in Produktion unter `/admin/einstellungen`, lokal in `claude_references/` (2026-11-14) | Repo ist öffentlich; IBAN und Unterschrift gehören nicht hinein |
| Status der Einheit | Nur `cancelledBy` gespeichert (nullable: Schüler:in, ich); „geplant“ = Einheit existiert, „gehalten“ aus der Zeit abgeleitet (2026-11-16) | Ableitbare Fakten nicht doppelt speichern; Sonja trägt nur Absagen ein (Mike). `erklaerungen/11`, `datenmodell.md` 3.5 |
| Nachholregel | Mindestzahl = geplante Einheiten des Kurses, kein Schwellwert; Einstellung „Einheiten pro Semester“ belegt neue Kurse vor (2026-11-15) | Absagen durch Schüler:innen werden nie nachgeholt; mehr Einheiten als vereinbart sind möglich, weniger nicht (Mike). `erklaerungen/11` |
| CSRF im Admin | Stimulus-CSRF-Controller aus dem Rezept entfernt; Formulare weiter über Herkunfts-Header geprüft, Raster mit Session-Token (2026-11-16) | Ein Verfahren für ganze Seite und Tests, kein Ausfall bei fehlendem JavaScript. `erklaerungen/17-csrf.md` |
| Kursname | Wochentage aus den Teilnahmen angehängt, „Wintersemester 2026/27 (Fr)“, kein eigenes Feld (2026-11-16) | Nichts doppelt speichern, stimmt immer (Mike). `umsetzung-admin.md` „Kursname“ |
| Ferien nachträglich | Alle Einheiten im Zeitraum löschen nach Popup mit Liste, am Ende auffüllen; Ferien löschen gibt die Einheiten zurück (2026-11-16) | Nachträgliche Ferien heißen: nichts fand statt (Mike). `umsetzung-admin.md` C4 Fragen 6, 7 |
| Dokumentation | Offenes nur in `projektstand.md`, Geschichte in `verlauf.md`, Erklärungen nur zum Verstehen; Server-Schritte S1–S10 (2026-11-14) | Offene Punkte waren über sieben Stellen verstreut, Kürzel doppelt vergeben (B1–B8 in `deployment.md` und in `umsetzung-admin.md`) |

## Server-Konfiguration

IONOS-VPS mit Ubuntu 24.04, 1 vCore, ~850 MiB nutzbarem RAM und 8,7 GB Platte. Ressourcen sind knapp und
bei jeder Entscheidung zu berücksichtigen.

| Bereich | Einstellung | Datei / Befehl |
|---|---|---|
| Swap | 1 GB Swapfile | /swapfile, Eintrag in /etc/fstab |
| Journal | max. 200 MB | /etc/systemd/journald.conf.d/size.conf |
| Docker-Logs | json-file, 3 × 10 MB pro Container | /etc/docker/daemon.json |
| SSH | Kein Root-Login, kein Passwort-Login, nur Key | /etc/ssh/sshd_config.d/00-hardening.conf |
| Firewall | ufw: 22, 80/tcp, 443/tcp, 443/udp | `sudo ufw status verbose` |
| Updates | unattended-upgrades, Neustart um 04:00 | /etc/apt/apt.conf.d/52unattended-upgrades-local |
| Benutzer | mike (sudo); deploy (ohne sudo, Gruppe docker) für GitHub Actions; dbbackup (ohne Docker, nur Backup-Skript per sudo) | `id mike`, `deployment.md` S1, S11 |
| Backups | Mac holt täglich 12:00 einen mit age verschlüsselten Dump; Skripte in /usr/local/sbin, sudo-Regeln in /etc/sudoers.d/sonjaczak-backup | `deployment.md` S11 |
| Cronjobs (root) | Token-Erinnerungen (`deployment.md` S6) | `sudo crontab -l` |
