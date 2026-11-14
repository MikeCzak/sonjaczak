# Projektstand: sonjaczak.com

Stand: 2026-11-14. **Die eine Datei für alles Offene.** Reihenfolge: was jetzt auf Mike wartet, was später zu klären
ist, Termine, Fortschritt, getroffene Entscheidungen, Server. Was wann im Detail passiert ist: `verlauf.md`.

**Verweise:** Schritte des Umsetzungsplans heißen „`umsetzung-admin.md` C4“ (`docs/planung/umsetzung-admin.md`),
Schritte der Server-Einrichtung „`deployment.md` S8“. Ist ein Punkt geklärt, verschwindet er hier und landet unter
„Getroffene Entscheidungen“ (oder im Plan, falls er nur einen Schritt betrifft).

## Jetzt (wartet auf Mike)

**So beantworten:** direkt hier in die Zeile „Antwort“ schreiben (oder im Chat). Claude überträgt die Antwort danach
in Plan bzw. Entscheidungen und entfernt den Punkt. Die Optionen mit Pro und Contra stehen im verlinkten Abschnitt.

1. **Welche PDF-Bibliothek für die Honorarnoten?** Optionen mit Version, Lizenz, Pro und Contra:
   `umsetzung-admin.md` D2. Empfehlung: dompdf. Alle Optionen brauchen die PHP-Erweiterung GD im Image.
   - Antwort:
2. **Womit wird das Kursraster gebaut: Stimulus + `fetch`, Turbo Frames oder Live Components?** Optionen:
   `umsetzung-admin.md` C4. Empfehlung: Stimulus + `fetch`. Bis dahin werden Einheiten einzeln auf
   `/admin/einheiten/{id}` markiert.
   - Antwort:
3. **Sollen die Admin-Konten jetzt am Server angelegt werden, und mit welcher E-Mail-Adresse für dich?** Befehle:
   `deployment.md` S7. Vorschlag: nur dein Konto per Befehl, Sonjas Konto danach über die Benutzerverwaltung mit
   Einladungslink (sie setzt ihr Passwort selbst).
   - Antwort:
4. **Darf der Cronjob für überfällige Honorarnoten eingetragen werden (täglich 07:30, Mail an `music@`)?** Eintrag:
   `deployment.md` S8. Vorher prüfen wir die Zeitzone des Servers (`timedatectl`). Sinnvoll erst, wenn es echte
   Honorarnoten gibt.
   - Antwort:
5. **Backup-Plan:** Noch keine Frage an dich. Claude schreibt zuerst den Plan (`umsetzung-admin.md` F) mit den
   Entscheidungen Verschlüsselung (`age` oder GPG), Ablage und Aufbewahrung am Mac, ob der Mac zur Sicherungszeit
   läuft, eingeschränkter SSH-Zugang. Dann gibst du frei, danach folgen die Schritte am Server und ein Test der
   Rücksicherung. **Echte Daten erst danach.**
   - Antwort (soll Claude den Plan jetzt schreiben?):

**Ausständig von Sonja** (über Mike, 2026-11-13): Absenderdaten für die Honorarnote (Name, Titel, Adresse, IBAN, BIC,
Bank, Ort, Hinweis zur Umsatzsteuer) und die Unterschrift als PNG. Werden in Produktion unter `/admin/einstellungen`
eingetragen, nicht ins Repo. Ohne sie lassen sich keine Honorarnoten erstellen.

## Später zu klären

Jeder Punkt mit dem Tag, an dem er entstand, und dem Schritt, aus dem er stammt. Die Begründung steht in der
verlinkten Erklärung.

### Mit Sonja

- [ ] **Schwellwert der Nachholregel** (2026-11-13, `umsetzung-admin.md` C3, `erklaerungen/11`): Sonja beschreibt in
  `planung/anforderungen.md` (Frage 6) eine **Gesamtzahl** („sofern die Gesamtanzahl der Stunden einen bestimmten
  Schwellwert unterschreiten würde“, z. B. 13), das Datenmodell einen **Abstand** (`plannedLessons − Schwellwert`,
  z. B. 2). Umgesetzt ist der Abstand, der Wert ist seit C6 eine Einstellung (Standard 0). Welche Variante, welcher
  Wert?
- [ ] **Auffüllen nach neuen Ferien** (2026-11-13, C3): Für entfallene Einheiten kommen am Ende neue dazu (bis zum
  Kursende).
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

### Technisch (Mike)

- [ ] **Passkeys** (`umsetzung-admin.md` B5, zurückgestellt 2026-11-13; Login mit Passwort reicht für die Entwicklung). **Zu
  klären:** Bisher stand im Projektstand „kommt vor dem Zeigen an Sonja“, in `umsetzung-admin.md` (Meilensteine) „spätestens
  vor dem Echtbetrieb“.
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
- [ ] **Postfächer:** Mail-Hosting für Sonjas Adressen unter sonjaczak.com (`music@` liegt bereits bei IONOS; prüfen, ob
  noch etwas offen ist).
- [ ] Kandidaten aus der Durchsicht des Templates (2026-11-03):
  - Symfony Language Server aus dem Dev-Image entfernen (lädt ungepinnt die neueste Version, wird nicht genutzt)
  - `composer audit` als CI-Schritt
  - GitHub Actions auf Commit-SHA statt Tag pinnen; zizmor für Workflow-Sicherheit
  - Image-Scan auf bekannte Schwachstellen (`docker scout` oder Trivy) in der CI
  - Ungenutzten `allow-plugins`-Eintrag `php-http/discovery` entfernen

## Termine

- **Upgrade auf Symfony 8.2 vor Ende Jänner 2027** (dann endet der Support für 8.1). Danach 8.3 (Mai 2027) und
  8.4 LTS (laut Release-Regeln voraussichtlich November 2027).
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
- [ ] C4 Kursraster (wartet auf Entscheidung, siehe „Jetzt“)
- [x] C5 Schnupperstunden (2026-11-13, `erklaerungen/11`)
- [x] C6 Einstellungen (2026-11-13, `erklaerungen/12`)
- [x] D1 Honorarnoten erstellen (2026-11-13, `erklaerungen/13`)
- [ ] D2 PDF (wartet auf Entscheidung, siehe „Jetzt“)
- [x] D3 Zahlungen und Gutschriften (2026-11-13, `erklaerungen/13`)
- [x] D4 Erinnerung bei überschrittener Frist: Befehl fertig, Cronjob siehe „Jetzt“ (2026-11-13, `erklaerungen/13`)
- [x] D5 Einnahmen und CSV-Export (2026-11-13, `erklaerungen/13`)
- [x] E Dashboard (2026-11-13, `erklaerungen/14`)
- [ ] F Vor den echten Daten: Backups (siehe „Jetzt“), Datenschutz-Information und Löschregel (siehe „Später“);
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
| Benutzer | mike (sudo); deploy (ohne sudo, Gruppe docker) für GitHub Actions | `id mike`, `deployment.md` S1 |
| Cronjobs (root) | Token-Erinnerungen (`deployment.md` S6) | `sudo crontab -l` |
