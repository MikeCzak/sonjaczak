# Verlauf: sonjaczak.com

Was wann gemacht wurde, mit Messwerten, Fehlern und Versionen. Wird nur ergänzt, nicht umgeschrieben.
Bis 2026-11-13 stand dieser Text in `projektstand.md` (dort jetzt nur noch Offenes, Fortschritt und Entscheidungen).

**Markierungen:** `[x]` erledigt. `[→]` war zu dem Zeitpunkt offen; wo der Punkt heute steht, sagt `projektstand.md`
(„Jetzt“ oder „Später zu klären“). Verweise auf `deployment.md` B1–B8, C, D heißen dort seit 2026-11-14 S1–S10
(Tabelle am Anfang von `deployment.md`).

## Phase 1: Setup bis zum ersten Deploy (bis 2026-11-03)

**Phase 1 erreicht (2026-11-03):** Jeder Push auf `main` läuft durch Tests → Build → Deploy und geht automatisch auf
https://sonjaczak.com live (erster Deploy: Commit `9cd534a`, CI-Lauf 41657250962). Symfony 8.1.10 (Skeleton) im
Template dunglas/symfony-docker, Doctrine ORM mit PostgreSQL 18.6. Zertifikat von Let's Encrypt (90 Tage, Caddy
erneuert selbst), HTTP leitet auf HTTPS um (308). Noch keine Startseite (404), keine Entities, keine Migrationen.

RAM auf dem Server nach dem ersten Deploy (Leerlauf): php 159 MiB, database 35 MiB; Server gesamt 462 MiB belegt,
386 MiB verfügbar, 37 MiB Swap genutzt.

**Projekt-Setup**

- [x] Git-Repo (Branch `main`), `.gitignore` mit `/instructions_draft.md`; `.idea/` und `.DS_Store` über globale Ignore-Datei
- [x] Template dunglas/symfony-docker übernommen (Commit `92d0d49`, ohne dessen `docs/`, `.devcontainer/`, `README.md`)
- [x] Symfony 8.1 per `SYMFONY_VERSION=8.1.*` installiert; `LICENSE` und `AGENTS.md` des Skeletons entfernt,
      `composer.json` auf `sonjaczak/website` / `proprietary` angepasst
- [x] Dockerfile, Compose-Dateien und `frankenphp/` gemeinsam durchgegangen
- [x] GitHub-Remote eingerichtet und gepusht
- [x] Doctrine (`symfony/orm-pack`, Flex-Rezept doctrine-bundle 3.0) installiert; auf PostgreSQL 18 angeglichen
      (Image-Tag, `serverVersion` in `compose.yaml` und `.env`, Volume auf `/var/lib/postgresql`, weil PGDATA ab 18
      unter `/var/lib/postgresql/18/docker` liegt). Persistenz über `down`/`up` geprüft.
- RAM lokal im Leerlauf (Dev-Image, Mac): php ~91 MiB, database ~22 MiB; `shared_buffers` = 128MB (Standard,
      wird erst bei Nutzung belegt). Nur Anhaltspunkt, auf dem Server mit Prod-Image neu messen.

**CI** (`.github/workflows/ci.yaml`, Basis: Template-CI)

- [x] PHPUnit 13 über `symfony/test-pack`; erster Test `tests/DatabaseConnectionTest.php` (Kernel bootet, Test-DB erreichbar)
- [x] PHPStan 2 auf Level max mit Symfony-, Doctrine- und PHPUnit-Erweiterung (`phpstan.dist.neon`); Erweiterungen
      manuell eingebunden statt über das Composer-Plugin `phpstan/extension-installer`
- [x] Job „Tests“: Dev-Image bauen (mit GitHub-Actions-Cache), Container starten, HTTP/Mercure prüfen, Test-DB
      anlegen, Migrationen, PHPUnit, Schema-Validierung, PHPStan – alles im Container, also mit PHP 8.5.12 wie lokal
- [x] Job „Lint“ (super-linter) des Templates entfernt: schwer, prüft auch Markdown-Stil und PHP ohne `vendor/`;
      später ggf. gezielt php-cs-fixer und zizmor (Workflow-Sicherheit) ergänzen
- [x] Ersten Lauf auf GitHub geprüft: grün
- [x] Job „Build“ (nur bei Push auf `main`, nach grünen Tests): Prod-Image über Bake aus `compose.yaml` +
      `compose.prod.yaml` bauen, Prod-Stack starten, Smoke-Test (`bin/console about`, DB-Abfrage), dann Push nach
      `ghcr.io/mikeczak/sonjaczak/app-php-prod:<commit-hash>`. Login mit `GITHUB_TOKEN` (`packages: write`).
- `compose.prod.yaml`: `image: ${IMAGES_PREFIX}app-php-prod:${IMAGE_TAG}` – CI und Server nutzen denselben Namen
- Prod-Image lokal getestet (2026-11-03): 301 MB, läuft als www-data, Symfony prod/ohne Debug, OPcache an;
  RAM im Leerlauf php ~107 MiB, database ~27 MiB (Mac, arm64)
- [x] Ersten Build-Lauf auf GitHub geprüft: grün, Image `…/app-php-prod:bc07492…` in ghcr.io (2026-11-03)
- Server-Architektur `x86_64` bestätigt (passt zum amd64-Build)
- Dependabot (`.github/dependabot.yaml`) ist aktiv: wöchentliche PRs für Docker-Images, Composer und Actions
- PHPStan-Analyse von DQL/Entities (`objectManagerLoader`) ergänzen, sobald es Entities gibt
- Startseite (`/`) fehlt noch bewusst: kommt mit dem ersten Twig-Template, wenn Übersetzungsschema und CSS-Framework festgelegt sind

**Vor dem ersten Deploy prüfen** (aus der Durchsicht des Templates)

- `opcache.memory_consumption = 256` in `frankenphp/conf.d/10-app.ini`: tatsächlichen Verbrauch messen, ggf. senken (850 MiB RAM)
- Mercure und Vulcain sind im `Caddyfile` aktiv, werden aber nicht genutzt: entfernen oder behalten?
- [x] Migrationen: führt `deploy.sh` explizit aus. Der Entrypoint sucht in `./migrations`, das Rezept legt sie in
      `src/Migrations` ab → sein automatischer Lauf greift nie; das ist so gewollt (nur eine Stelle führt Migrationen aus)
- [x] `POSTGRES_PASSWORD`, `APP_SECRET`, `CADDY_MERCURE_JWT_SECRET` in `compose.prod.yaml` Pflicht (`${VAR:?…}`)
- [x] PostgreSQL in Prod: `shared_buffers=32MB`, `max_connections=20`, `restart: unless-stopped` (lokal mit Prod-Image
      getestet: database ~18 MiB im Leerlauf)
- [x] Server lädt nur `compose.yaml` + `compose.prod.yaml` (in `deploy.sh` festgelegt) → kein DB-Port veröffentlicht
- [x] Image privat → Personal Access Token (classic) mit nur `read:packages` auf dem Server (`docs/deployment.md`, B4)
- [x] FrankenPHP-Worker: Standard 2 × CPU (Quelle: frankenphp.dev/docs/config) → 2 auf dem Server
- [x] Nach dem ersten Deploy: RAM auf dem Server gemessen (siehe „Aktueller Stand“)
- [→] `opcache.memory_consumption = 256` ggf. senken: tatsächliche OPcache-Belegung messen, sobald die App gewachsen ist
- [→] Backups der Datenbank (DSGVO) einrichten – spätestens bevor echte Daten erfasst werden
- [x] `www.sonjaczak.com` → Weiterleitung auf `sonjaczak.com` (2026-11-12: A-Record `www`, Caddy-Block über
      `CADDY_EXTRA_CONFIG` in `compose.prod.yaml`, 301; Erklärung in `docs/erklaerungen/03-compose-und-frankenphp.md`)
- [x] Erinnerung an ablaufende Tokens: Cronjob am Server mit Mail (`docs/deployment.md` B6), eingetragen 2026-11-12
      (gh CLI läuft 2027-11-02 ab, Server-Token 2027-11-03)
- Mercure und Vulcain sind im `Caddyfile` aktiv, werden aber nicht genutzt: entfernen oder behalten?
- Alte Images in ghcr.io aufräumen (derzeit kostenlos, laut GitHub-Doku „currently free“, Änderung mit 1 Monat Vorlauf)
- CI braucht PHP ≥ 8.5.12 (`composer.json` → `"php": ">=8.5.12"`)

**Server vorbereiten**

- [x] Alte Test-App (smart-ui) entfernt, ungenutzte Images und Volumes gelöscht
- [x] Systemjournal auf 200 MB begrenzt, Docker-Logs auf 3 × 10 MB pro Container
- [x] System aktualisiert (apt full-upgrade) und neu gestartet
- [x] 1 GB Swap eingerichtet und in /etc/fstab eingetragen

**Server absichern**

- [x] Benutzer mike mit sudo-Rechten
- [x] SSH nur mit Key, Root-Login deaktiviert (per `sshd -T` geprüft)
- [x] ufw aktivieren (Regeln für 22, 80/tcp, 443/tcp, 443/udp angelegt)
- [x] Firewall-Richtlinie im IONOS Cloud Panel prüfen (nur 22, 80, 443 offen)
- [x] unattended-upgrades mit automatischem Neustart um 04:00
- [x] Prüfen, ob Docker aus dem offiziellen Docker-Repository stammt (dann manuell aktualisieren)

## Phase 2: Planung, erstes Release, Admin-Bereich (ab 2026-11-03)

Ziel der ersten Phase: eine leere Seite, die bei jedem `git push` automatisch auf sonjaczak.com live geht.
Erst danach werden Features gebaut.

1. Deployment: Repo-Teil fertig (`deploy/deploy.sh`, Job „Deploy“, `compose.prod.yaml`). Server-Einrichtung,
   GitHub-Environment und DNS erledigt (2026-11-03, `docs/deployment.md` B–D; A-Record → `85.215.162.84`, VPS hat
   kein IPv6, daher kein AAAA-Record). Erster Deploy erfolgreich, RAM gemessen. Rollback getestet
   (2026-11-03: Re-run des Deploy-Jobs von `9cd534a` nach Deploy von `86b0932`; nur php neu gestartet, die Datenbank
   lief durch). **Phase 1 abgeschlossen.**
2. **Aktuell:** Planung der App (Phase 2). Erste Fragenrunde in `docs/planung/anforderungen.md` beantwortet
   (2026-11-07): zuerst die öffentlichen Seiten. Inhalte in `docs/planung/inhalte.md` weitgehend beantwortet
   (2026-11-11). Seitenstruktur in `docs/planung/seitenstruktur.md` festgelegt (Routen, Menü, Übersetzungsschema,
   CSS-Framework). Datenmodell (ER-Diagramm) und Rollenkonzept für den Admin-Bereich folgen nach dem ersten Release.
   **Aktuell (2026-11-12):** Datenmodell `docs/planung/datenmodell.md`, zweiter Entwurf: erste Fragenrunde
   eingearbeitet (Schnupperstunde als eigene Entity, Ferien je Kurs, Einheiten werden erzeugt, Kursraster als
   Arbeitsansicht, eine Honorarnote mit Ratenoption, Gutschrift, PDF in der Datenbank). 2026-11-13: zweite Fragenrunde
   eingearbeitet (Schnupperstunde kostenlos und übernehmbar, Ferien-Vorschlag aus der OpenHolidays API, Erinnerungs-Mail
   bei überschrittener Zahlungsfrist per Cronjob). **Freigegeben 2026-11-13.** `docs/planung/rollen.md`, zweiter Entwurf (Rollen, Firewall nur für `/admin`,
   Voter, Passkeys mit Passwort als Rückfall, „Passwort vergessen“ per Mail-Link, Sessions in PostgreSQL mit 8 h
   Inaktivität / 12 h Cookie), **freigegeben 2026-11-13**. Admin-Cookie nur unter `/admin`; Datenschutzerklärung
   wird mit dem Login-Schritt angepasst. Umsetzungsplan: `docs/planung/umsetzung-admin.md`.
   - [x] A1 (2026-11-13): Zeitzone `Europe/Vienna` (Test), `zenstruck/foundry` 2.16.0 (Faker `de_AT`, Factories und
     Stories in `tests/`), `symfony/maker-bundle` 1.68.0, PHPStan mit `objectManagerLoader`. Fixtures-Bundle verworfen
     (Foundry lädt selbst).
   - [x] B1/B2 (2026-11-13): `symfony/security-bundle` 8.1.11, Entity `User` (Tabelle `app_user`, E-Mail klein
     gespeichert), Firewall nur `^/admin`, `form_login` mit CSRF, `login_throttling`, Logout per POST mit CSRF,
     Rollenhierarchie, `app:user:create`, Admin-Layout und Domain `admin`. Sessions in PostgreSQL (`PdoSessionHandler`,
     8 h Inaktivität, Cookie 12 h, nur `/admin`), lokal über Container-Neustart geprüft. Datenschutzerklärung: Absatz
     zum Session-Cookie (§ 165 Abs. 3 TKG 2021, im RIS geprüft; Entwurf). Erklärung: `docs/erklaerungen/08-login-und-sessions.md`.
   - [x] B3 (2026-11-13): „Passwort vergessen“ mit `symfonycasts/reset-password-bundle` 1.26.1 (Link 1 h, einmal
     gültig, immer gleiche Antwort), „Mein Konto“ mit Passwortänderung. Sessions nutzen jetzt Doctrines Verbindung mit
     Advisory Locks (CI-Fehler bei der Schema-Prüfung in Tests behoben, Commit `2de9d4d`).
   - [x] B4 (2026-11-13): Benutzerverwaltung unter `/admin/benutzer` (nur Super-Admin): Konto anlegen mit
     Einladungslink (3 Tage), Rolle ändern, deaktivieren statt löschen (`UserChecker`), erster Voter `UserVoter`
     (eigenes Konto nie änderbar).
   - [→] B5 Passkeys: zurückgestellt bis nach C/D/E (eigener Prüfaufwand am Bundle-Code; Login mit Passwort reicht
     für die Entwicklung). Kommt vor dem Zeigen an Sonja.
   - [x] C1 (2026-11-13): Schüler:innen und Elternteile (`/admin/schueler`, `/admin/elternteile`), Embeddable
     `Address`, Suche, `StudentVoter::DELETE` (seit C5: nicht mit Teilnahmen, damit auch nicht mit Honorarnoten), Factories und Story mit
     erfundenen Daten (Testkonten `mike@`/`sonja@example.com`). Erklärung: `docs/erklaerungen/09-datenbank-schueler.md`.
   - [x] C2 (2026-11-13): Kurse mit Ferien (`/admin/kurse`), Enums `Term`/`BreakSource`, Preis in Cent, Ferien-Vorschlag
     aus der OpenHolidays API beim Anlegen (live geprüft mit Sommersemester 2027), Tests mit aufgezeichneten Antworten.
     `symfony/http-client` jetzt direkt in `composer.json` (war nur indirekt installiert, 8.1.11). PHPStan:
     `allowNullablePropertyForRequiredField` (Begründung in `docs/erklaerungen/10-datenbank-kurse.md`).
   - [x] C3 (2026-11-13): Teilnahmen und Einheiten. Einheiten werden beim Anlegen erzeugt (ohne Ferien, bis
     `plannedLessons`), neue Ferien entfernen geplante Einheiten und füllen am Ende auf, Nachholregel mit Anzeige
     „14 von 15“ und Nachholstunde je abgesagter Einheit, Kurs mit Teilnahmen nicht löschbar. Erklärung:
     `docs/erklaerungen/11-teilnahmen-und-einheiten.md`.
   - [x] C5 (2026-11-13): Schnupperstunden (`/admin/schnupperstunden`), Übernahme in eine Teilnahme in einer
     Transaktion (`fromTrial`). Schüler:innen mit Teilnahmen sind nicht mehr löschbar (vorher Datenbankfehler).
   - [x] C6 (2026-11-13): Einstellungen (`/admin/einstellungen`): Theme der Website (öffentliche Seiten lesen es jetzt
     aus der Datenbank, mit Rückfall auf „classic“, falls die Datenbank nicht erreichbar ist), Standarddauer,
     Schwellwert der Nachholregel, Absenderdaten, Unterschrift als PNG in der Datenbank. BIC nur per Regex geprüft
     (`Assert\Bic` bräuchte `symfony/intl`). Slider-Bilder bleiben vorerst in `config/services.yaml`.
     Nach dem Deploy: Sonja trägt Absenderdaten und Unterschrift ein (vorher keine Honorarnoten möglich).
   - [x] D1 (2026-11-13): Honorarnoten erstellen (`/admin/honorarnoten`, Erstellen auf der Seite einer Teilnahme):
     Empfänger und Betrag als Kopie, keine Setter, Nummer je Ausstellungsjahr in einer Transaktion mit PostgreSQL
     Advisory Lock (Abweichung vom Datenmodell, das eine Wiederholung bei Kollision vorsah). Entity `Payment` ohne
     Oberfläche (folgt in D3). Teilnahmen mit Honorarnote sind nicht löschbar. Erklärung:
     `docs/erklaerungen/13-honorarnoten.md`.
   - [→] D2 PDF: wartet auf die Entscheidung zur PDF-Bibliothek.
   - [x] D3 (2026-11-13): Zahlungen (Formular mit Restbetrag und heute vorbelegt, Datum änderbar, nicht in der Zukunft),
     Status berechnet, Gutschriften mit eigener Nummer (`credits`, `InvoiceVoter::CREDIT`), Rückzahlung als negative
     Zahlung. Honorarnote und Gutschriften als ein Konto (zum Durchsehen).
   - [x] D4 (2026-11-13): Befehl `app:invoices:remind-overdue` (eine Mail an `music@` je neu verpasster Frist,
     `remindedFor`, `--dry-run`), Tests mit `MockClock`; `symfony/clock` direkt in `composer.json`. Lokal über Mailpit
     geprüft.
   - [x] D5 (2026-11-13): Einnahmen je Jahr nach Zahlungsdatum (`/admin/einnahmen`) und als CSV für Excel (Semikolon,
     Dezimalkomma, UTF-8 mit BOM, Schutz vor Formeln). § 19 Abs. 1 EStG im RIS nachgelesen; die Ausnahme für
     regelmäßig wiederkehrende Einnahmen (Z 1) mit der Steuerberatung klären. Mit Sonjas Excel noch prüfen.
   - [x] E (2026-11-13): Dashboard `/admin`: nächste 7 Tage (Einheiten und Schnupperstunden), offene Honorarnoten mit
     Überfälligkeit, Teilnahmen ohne Honorarnote, Einnahmen des Jahres je Kurs, je laufendem Kurs die zuletzt gehaltene
     Einheit mit Inhalt. Erklärung: `docs/erklaerungen/14-dashboard.md`.
   - F (Teil ohne Server): Aufbewahrungsfrist in § 132 BAO nachgelesen (7 Jahre ab Schluss des Kalenderjahres, Details
     in `umsetzung-admin.md` F); die Lösch-/Anonymisierungsregel ist **zurückgestellt** (Mike, 2026-11-13: „für
     später aufheben“).
   - [→] **Von Sonja nachzureichen** (über Mike, 2026-11-13): Absenderdaten für die Honorarnote (Name, Titel, Adresse,
     IBAN, BIC, Bank, Ort, Hinweis zur Umsatzsteuer) und die Unterschrift als PNG. Beides trägt Sonja (oder Mike) in
     Produktion unter `/admin/einstellungen` ein, nicht ins Repo. Ohne sie lassen sich keine Honorarnoten erstellen.
   - **Kein eigener Demo-Termin** (Entscheidung 2026-11-13): Mike sieht sich die Funktionen lokal an
     (`docs/rundgang-admin.md`) und zeigt sie Sonja in Produktion.
   - [→] D2 PDF und C4 Kursraster: Optionen am 2026-11-13 vorgestellt (PDF-Bibliothek; Stimulus, Turbo Frames oder
     Live Components), Entscheidung offen.
   - [→] D4 Cronjob am Server (`deployment.md` B8) – **wartet auf Mikes Freigabe** (Aktion am Server)
   - [→] C4 Kursraster: wartet auf die Entscheidung zu Symfony UX (Stimulus + `fetch`, Turbo Frames oder Live
     Components). Bis dahin werden Einheiten einzeln auf `/admin/einheiten/{id}` markiert.
   - **Zum Durchsehen:** alle offenen Auslegungen gesammelt in `docs/planung/zum-durchsehen.md` (seit 2026-11-14 in `projektstand.md`) (mit Sonja, mit der
     Steuerberatung, technisch).
   - [→] Konten in Produktion anlegen (`deployment.md` B7) – **wartet auf Mikes Freigabe** (Aktion am Server)
   Todos für den Admin-Bereich (Dashboard, Export der Einnahmen, PDF, Art.-13-Information) in Abschnitt 9.
   Ziel: Datenbank und Admin-Bereich fertig, bevor Mike Sonja den Stand zeigt.
3. **Umsetzung erstes Release** (Plan: `docs/planung/seitenstruktur.md`, Abschnitt 10)
   - [x] Schritt 1 (2026-11-11): Twig, Translation (`de`), AssetMapper, Sass-Bundle und Bootstrap 5.3.8 (Sass aus
         `twbs/bootstrap`, JavaScript über die Importmap). Prod-Builder führt `sass:build` vor `asset-map:compile` aus
         und löscht danach `var/dart-sass` und `vendor/twbs` (nur zur Build-Zeit nötig); Prod-Image lokal 305 MB
         (vorher 301 MB). CI-Job „Tests“ baut Sass vor PHPUnit. Nebenbei Symfony 8.1.10 → 8.1.11 (Security-Release,
         `composer audit` meldete 10 Hinweise).
   - [x] Schritt 2 (2026-11-11): Layout (`base.html.twig`, Header, Footer), Hauptmenü mit KnpMenuBundle 3.8.0
         (`App\Menu\MenuBuilder`, eigenes Bootstrap-Template `templates/menu/main.html.twig`), alle Routen aus
         `seitenstruktur.md` als Platzhalterseiten, Startseite mit Hero, Kurzvorstellung (leer, ausgeblendet) und
         drei Angeboten. Übersetzungen in `messages.de.yaml` und `content.de.yaml`; Kontaktdaten als Twig-Globals
         (`music@sonjaczak.com`, YouTube; Telefon vorerst nicht). Tests: jede Seite 200 mit Layout und ohne
         unübersetzte Schlüssel, aktive Menüpunkte, Impressum-Anker, fehlende Übersetzungen
         (`debug:translation --only-missing`). CI: `lint:twig`, `lint:yaml`, HTTPS-Check aktiv, Smoke-Test ruft `/`
         am Prod-Stack ab (lokal nachgestellt: grün; php 114 MiB, database 27 MiB im Leerlauf, Mac).
   - [x] Schritt 3: Design – **entschieden und umgesetzt (2026-11-12):** Variante A als Theme `classic` (Standard),
         B und C bleiben als Themes `modern` und `stage` erhalten (`App\Theme\Theme`, Partials in
         `assets/styles/partials/themes/`). Aktives Theme und Slider-Bilder liefert `App\Theme\SiteSettings`, vorerst
         aus `config/services.yaml`; später aus der Datenbank, eingestellt von Sonja im Admin-Bereich (Dropdown für das
         Theme, auch saisonale Themes; Verwaltung der Slider-Bilder). Vorschau per `?theme=…` nur für die eine Seite,
         ohne sessionStorage und ohne Inline-Script (Umschalter entfernt, Datenschutzerklärung angepasst).
         Logo und Hero-Überschrift in Allura; Great Vibes, Parisienne und Dancing Script entfernt. Logo als SVG mit
         Pfaden (`bin/build-logo`, fontTools und HarfBuzz am Mac, inline mit `currentColor`, 2026-11-12).
         Startseite: Slider mit Bootstrap-Carousel (`assets/slider.js`) über `hero-sonja2` und `hero-sax`, Wechsel alle
         6 s mit Überblendung, Pfeile, Wischen, Tastatur, Pause-Knopf (WCAG 2.2.2), kein Autoplay bei „Bewegung
         reduzieren“; ohne JavaScript nur das erste Bild.
         Bisheriger Stand (2026-11-11), drei Varianten:
         A klassisch-elegant (Cormorant Garamond + Jost), B modern-klar (Montserrat + Inter), C Bühne/dunkel
         (Playfair Display + Inter); Akzentfarbe Petrol in A und B, Gold in C (Rot verworfen: zu nah an Red Mullet);
         Unterricht im selben Stil wie die übrigen Seiten (Flyer-Pink verworfen, 2026-11-11). Logo-Kandidaten:
         Great Vibes, Allura, Parisienne, Dancing Script. Vorschau über `/_design` und `?design=a|b|c` (auch in
         Produktion, nicht verlinkt, `noindex`). Schriften lokal (Fontsource 5.3.0, OFL 1.1, `OFL.txt` je Schrift),
         Hero-Bilder in Web-Größe (`assets/images/`, ohne Metadaten); Startseite derzeit mit `hero-sonja2.jpg`
         (SonjaR_222, s/w), `hero-sonja.jpg` bis zur Entscheidung zum Vergleich.
         Aus Schritt 4 vorgezogen (2026-11-11): `/booking` als Übersicht mit zwei Bild-Sections (`hero-wedding.jpg`,
         `hero-band.jpg`), Unterricht mit Hero (`hero-lessons.jpg`), Variante C auf Home mit `hero-sax.jpg`
         (sonja_sax_profil, Simon Reithofer). Auf schmalen Bildschirmen Abdunklung von unten statt von links. Nach der Entscheidung: Variante festschreiben,
         Umschalter und ungenutzte Schriften entfernen, Logo als SVG mit Pfaden (laut OFL-FAQ 1.1 erlaubt).
         Nebenbei: Prod-Image enthielt kein `/etc/mime.types` → woff2 ohne Content-Type; im Dockerfile ergänzt.
   - [x] Schritt 4 (2026-11-11), Seiten mit Inhalt: Über mich, Booking solo (Anlässe, Leistungen, Region, Ablauf,
         Stornobedingungen), Booking Band (Link zu redmulletmusic.at), Unterricht (Inhalte, Lehrerin, Ort mit
         Maps-Link, Termine, Preise), Kontakt, Impressum (`/kontakt#impressum`), Datenschutzerklärung, 404-Seite.
         Anfrage vorerst per `mailto:` mit Betreff (Formulare in Schritt 6). Fehlende Texte als Entwurf von Claude
         (`# Draft` in `content.de.yaml`). Caddy kürzt IP-Adressen im Zugriffsprotokoll (`ip_mask 24 48`).
         `symfony/asset` nachinstalliert (für `asset()`, Teil der AssetMapper-Installation).
   - [x] Schritt 5 (2026-11-11): `bin/build-images` (ImageMagick auf dem Mac) erzeugt WebP in 640/1280/1920 px und
         ein JPEG (1280 px) als Ersatz, Maße in `config/packages/images.yaml`. Hero und Booking-Sections als
         `<picture>` mit `srcset` statt CSS-Hintergrund (Makro `image()`); Handy lädt 8–20 KB, Desktop 34–69 KB pro
         Bild. Scroll-Effekte nur mit CSS (`_motion.scss`): Hero zoomt beim Hinausscrollen, Booking-Sections mit
         Parallax, Abschnitte blenden ein; aus bei „Bewegung reduzieren“. Effekte im Browser beurteilen (Headless-
         Screenshots zeigen keine Bewegung).
   - [x] Schritt 6: Formulare mit Mailversand – Plan in `docs/planung/formulare.md`, freigegeben (2026-11-11):
         IONOS-SMTP (`smtp.ionos.de`), synchron, ohne Session/Cookies, nichts in der DB. Absender
         `anfrage@sonjaczak.com`, Empfänger `music@sonjaczak.com`, `Reply-To` = Besucher:in.
     - [x] 6a: `symfony/mailer` 8.1.10 (Absender global in `config/packages/mailer.yaml`, Tests immer `null://null`),
           Mailpit v1.33.1 nur lokal (UI auf `127.0.0.1:8025`, ~12 MiB). `MAILER_DSN` Pflicht in `compose.prod.yaml`
           in `/srv/sonjaczak/.env` (`deployment.md` B3). Testversand vom Server angekommen (2026-11-11).
     - [x] 6b (2026-11-11): Kontaktformular auf `/kontakt`. `symfony/form`, `validator`, `security-csrf` (zustandslos,
           Rezept), `rate-limiter` (5/h pro IP, filesystem-Cache). Datenklassen `src/Inquiry/`, Form-Types `src/Form/`
           (gemeinsame Felder in `InquiryType`, Lockfeld `HoneypotType`), Versand in `InquiryProcessor` (Log ohne
           Formulardaten). Partial `_partials/inquiry_form.html.twig` für alle drei Formulare (Sie/Du über `address`).
           Mail nur Text (`templates/email/inquiry/`). Seite setzt weiterhin kein Cookie (Test). Lokal per Mailpit
           geprüft. Datenschutzerklärung dafür vorgezogen (2026-11-12): Abschnitt „Kontaktformular und E-Mail“
           (keine Speicherung, Versand über IONOS, Rate Limiter mit Prüfwert statt IP, max. 1 h).
           Erster Live-Test scheiterte (2026-11-12): PHP im FrankenPHP-Worker fand keine CA-Zertifikate
           (`SSL_CERT_FILE` kommt dort nicht an, das schlanke Prod-Image hat kein OpenSSL-Standardverzeichnis), die
           Konsole schon. Lösung: `openssl.cafile` in `20-app.prod.ini`, CI-Smoke-Test prüft TLS ohne `SSL_CERT_FILE`.
     - [x] 6c (2026-11-12): Booking-Formular auf `/booking/solo` (Anlass als Enum `App\Inquiry\Occasion` mit festen
           Schlüsseln statt der Liste aus `content`, Datum nicht in der Vergangenheit, Ort). Gemeinsamer Ablauf im
           Controller-Trait `InquiryFormTrait`. Datenschutzerklärung um die Booking-Felder ergänzt.
     - [x] 6d/6e (2026-11-12): Unterrichtsformular auf `/unterricht` (Du-Form): Alter, erziehungsberechtigte Person
           (Pflicht unter 18 per `#[Assert\Callback]`, ohne `expression-language`; `assets/inquiry.js` blendet das Feld
           ab 18 aus, ohne JS sichtbar mit Hinweis), gewünschte Uhrzeit (Freitext), Vorerfahrung, Nachricht.
           Datenschutzerklärung nennt alle Formularfelder. `mail_inquiry`-Partial und `mailto:`-Anfragen entfernt.
           **Schritt 6 abgeschlossen.** Zum Durchsehen (Entwurf): Bei Minderjährigen kommt der Vertrag mit den
           Erziehungsberechtigten zustande; ob die Datenschutzerklärung dazu mehr sagen soll, mit der Rechtslage klären.
   - [→] **Vor dem Live-Gang der Inhalte klären** (Impressum/Datenschutz sind Entwürfe, keine Rechtsberatung)
     - [x] Rechtsform (2026-11-12): Einzelunternehmen, Neue Selbständige, kein Gewerbe, keine UID → keine Angaben
       nach § 5 Abs. 1 Z 5–7 ECG; Schlüssel `imprint.profession` entfernt
     - [x] Zweiter schneller Kontaktweg neben der E-Mail (EuGH C-298/07: Kontaktformular genügt, Telefon nicht
       zwingend) → Kontaktformular auf `/kontakt` (Schritt 6, live getestet 2026-11-12)
     - [x] IONOS (2026-11-12): AVV gilt laut ionos.de/terms-gtc/avv (Ziff. 1.1, 12.3) als Teil der AGB, kein eigener
       Abschluss; Rechenzentrum des VPS in Berlin (EU)
     - [x] Postfach `music@` liegt bei IONOS (2026-11-12). Keine Weiterleitung an Gmail (für kostenloses Gmail kein
       Auftragsverarbeitungsvertrag gefunden), Sonja liest es per IMAP in Apple Mail. Datenschutzerklärung nennt nur IONOS.
     - [x] Texte in der Ich-Form statt „Sonja“ (2026-11-12; Meta-Beschreibungen und Rechtstexte bleiben neutral)
     - [x] KI-Bilder gekennzeichnet (2026-11-12): Hinweis „KI-generiert“ in der Ecke (Liste `ai_generated_images` in
       `config/packages/twig.yaml`) und im Bildnachweis; Austausch gegen Fotos, sobald vorhanden
     - [x] Minderjährige (2026-11-12): Altersgrenze 14 (§ 4 Abs. 4 DSG) gilt nur für Einwilligungen, die Formulare
       stützen sich auf Art. 6 Abs. 1 lit. b/f → freiwilliger Hinweis „unter 14 gemeinsam mit einem Elternteil“ beim
       Unterrichtsformular
     - Entwürfe durchsehen: Texte mit `# Draft` in `translations/content.de.yaml`
     - Später (nach dem Release): Regeln für den Unterricht (inhalte.md E42: Absagen, Nachholstunden) und FAQ (E44),
       Texte von Sonja; die Abschnitte bleiben bis dahin ausgeblendet
     - Nach dem Deploy: gekürzte IPs in den Server-Logs prüfen (`docker compose logs php`)
   - [x] **CI startet nicht** (2026-11-09 `startup_failure`): seit 2026-11-11 behoben, CI läuft wieder.

## Erledigte offene Entscheidungen (Stand 2026-11-13)

Aus der früheren Tabelle „Offene Entscheidungen“ im Projektstand:

- **Admin-Bereich** (Login und Rollen, Semester, Schüler:innen, Honorarnoten): geplant in `planung/datenmodell.md`,
  `planung/rollen.md` und `planung/umsetzung-admin.md`, alle freigegeben 2026-11-13.
- **Symfony UX** und **Postfächer**: weiterhin offen, jetzt in `projektstand.md`.

## 2026-11-14

- Doku neu geordnet (Offenes nur in `projektstand.md`, Verlauf hier, Server-Schritte S1–S10).
- Entscheidungen von Mike: dompdf (D2), Stimulus + `fetch` (C4), Cronjob für überfällige Honorarnoten freigegeben ohne
  Prüfung der Zeitzone, Backup-Plan soll Claude schreiben. Super-Admin in Produktion von Mike angelegt, Sonjas Konto
  folgt über die Benutzerverwaltung.
- Absenderdaten von Sonja erhalten. Mike hatte sie in `projektstand.md` eingetragen; vor dem Commit nach
  `claude_references/` (nur lokal) verschoben, weil das Repo öffentlich ist. Geprüft: IBAN und BIC waren in keinem
  Commit.
- Bei der Erklärung der Sperren gefunden: Doppelklick kann zwei Honorarnoten für dieselbe Teilnahme erzeugen
  (`erklaerungen/15-sperren.md`, Abschnitt 3). Behebung wartet auf Freigabe.
- Mike: keine Unterschrift auf der Honorarnote nötig; Doppelklick-Lücke schließen; Backup-Plan schreiben.
- Doppelklick-Lücke geschlossen: `InvoiceIssuer` prüft unter dem Lock in der Datenbank erneut
  (`InvoiceRepository::hasInvoiceFor()`, `creditableCents()`), sonst `InvoiceConflictException` mit Meldung und
  Weiterleitung. Zwei Tests stellen den veralteten Zustand der zweiten Anfrage nach; Gegenprobe ohne die Prüfung:
  beide schlagen fehl. Volle Suite 115 Tests grün, PHPStan ohne Fehler.
- Backup-Plan als Entwurf: `planung/backups.md` (age am Server 1.1.1 aus Ubuntu universe, am Mac 1.3.2 über Homebrew;
  launchd holt verpasste Termine nach dem Aufwachen nach, `man launchd.plist`).
- Mike: alle Empfehlungen des Backup-Plans freigegeben; Upload-Feld der Unterschrift entfernen.
- Unterschrift entfernt: Feld, Route `/admin/einstellungen/unterschrift`, Übersetzungen, Tests; Migration
  `Version20261114022327` löscht `settings.signature`. 114 Tests grün (Upload-Tests ersetzt durch einen Test der
  IBAN-Normalisierung).
- Backups, Repo-Teil: `deploy/backup.sh` (am Server `/usr/local/sbin/sonjaczak-backup`), `deploy/restore-test.sh`,
  `bin/backup-pull`, LaunchAgent-Vorlage `deploy/launchd/`, Anleitungen `deployment.md` S11 und `entwicklung.md`.
  Abweichungen vom Plan: Benutzer `dbbackup` statt `backup` (gibt es in Ubuntu schon, im Image `ubuntu:24.04` geprüft),
  Skripte in `/usr/local/sbin` statt `/srv/sonjaczak` (dort nur root und `deploy`). Lokal gegen den Dev-Stack
  getestet, age aus einem Wegwerf-Container (Alpine, age 1.2.1): Dump 36 KB, Rücksicherung mit gleicher Zeilenzahl je
  Tabelle, Testdatenbank danach gelöscht. Gegenprobe: Scheitert `pg_dump`, endet das Skript mit 1, liefert aber eine
  200-Byte-age-Datei; deshalb prüft `bin/backup-pull` den Exit-Code. Rotation mit erfundenen Dateien geprüft.
- Mike hat die Nachholregel geklärt (Absagen durch Schüler:innen nie nachholen; Mindestzahl je Kurs, Standard aus den Einstellungen). Umbau des Schwellwerts vorgeschlagen, Frage im Projektstand.

## 2026-11-15

- Mike hat Sonjas Konto angelegt (Rolle „Verwaltung“ = `ROLE_ADMIN`), Absenderdaten und Cronjob für überfällige
  Honorarnoten eingetragen.
- Backups eingerichtet (Mac und Server nach `entwicklung.md` und `deployment.md` S11). Stolpersteine:
  - `visudo` öffnet `sonjaczak-backup.tmp`; unter dem Zielnamen gespeichert → „unchanged“, Datei nicht angelegt.
    Hinweis in S11 ergänzt (Name beim Speichern nicht ändern).
  - SSH-Test meldete „PTY allocation request failed“ (`restrict` verbietet ein Terminal), Ergebnis trotzdem richtig.
    `ssh -T` in Testbefehl und `bin/backup-pull` ergänzt.
  - Erster Rücksicherungstest ohne Sicherung gestartet (Schritt 3 noch nicht gemacht): `pg_restore` brach bei leerem
    Datenstrom ab, Testdatenbank wurde wieder gelöscht.
  - `tail` des Logs direkt nach `launchctl kickstart` fand noch nichts, weil der Job im Hintergrund läuft.
- Erste Sicherung 33 729 Byte, LaunchAgent `runs = 1`, `last exit code = 0`. Rücksicherungstest erfolgreich.
- Erklärung `erklaerungen/16-backups.md` geschrieben (Konzepte, Schritte, Alternativen, Praxis in Firmen); dabei
  gegen PostgreSQL-18-Doku und man-Seiten geprüft. Die Doku von `pg_dump` nennt es „except in simple cases“ nicht die
  richtige Wahl für Produktion; unsere Datenbank ist ein solcher einfacher Fall.
- Nachholregel umgebaut: Einstellung „Schwellwert“ ersetzt durch „Einheiten pro Semester“ (Vorschlag für neue Kurse,
  vorher fest 15 in `Course`); `MakeUpRule::due()` ohne Schwellwert. Migration `Version20261115201517`: Diff schlug nur
  eine Umbenennung vor, die den alten Wert 0 übernommen hätte; ergänzt um `UPDATE … = 15`. Hin und zurück lokal
  geprüft. Tests: Fall „mehr gehalten als geplant“ neu, Einstellungstest prüft jetzt die Vorbelegung des
  Kursformulars. 114 Tests grün, PHPStan ohne Fehler, Schema in Sync.

## 2026-11-16

- Sicherung um 12:00 erstmals automatisch über launchd (33 784 Byte).
- C4 geplant, Entscheidungen von Mike: eigene Rasterseite, Stimulus nur im Admin, JavaScript per Headless Chrome
  prüfen, nur Knöpfe statt Tastaturkürzel, `PATCH /admin/einheiten/{id}`, Popup vor dem Löschen von Einheiten durch
  Ferien, Ferien löschen legt die Einheit wieder an, Doppelstunde = zweite Einheit.
- Zweimal beim Einfügen in `umsetzung-admin.md` per Skript den Rest der Datei abgeschnitten (Slicing ohne Rest), beide
  Male aus dem letzten Commit wiederhergestellt, Diff danach nur mit hinzugefügten Zeilen.
- Status der Einheit umgebaut: `status` → `cancelledBy` (nullable), „gehalten“ abgeleitet (`Lesson::state()`,
  Twig-Funktion `lesson_state`). Migration `Version20261116110522` von Hand (Diff hätte die Absagen gelöscht), lokal hin
  und zurück geprüft. `applyBreaks()` löscht vorerst nur zukünftige Einheiten. Ein Test hing am heutigen Datum und
  legt die Zeit jetzt fest. Neuer `LessonTest`. 120 Tests grün, PHPStan ohne Fehler, Schema in Sync.
- C4a: `symfony/stimulus-bundle` 3.7.0 installiert. Das Rezept lud Stimulus in `app.js` (alle Seiten): zurückgesetzt,
  eigener Entrypoint `admin` nur im Admin-Layout, per `curl` geprüft. Beispiel-Controller und CSRF-Controller entfernt
  (Mike), Erklärung `erklaerungen/17-csrf.md`. Ein Test schlug fehl, weil der Test-Cache (ohne Debug) vom Stand vor der
  Installation war; nach `rm -rf var/cache/test` 120 Tests grün.
- C4b: Raster nur lesend unter `/admin/kurse/{id}/raster` (Links von Kursseite, Kursliste, Dashboard). Service
  `App\Course\Grid\CourseGrid` (Wochen × Zeitfenster, Spaltenregel: eigenes Zeitfenster, sonst das am selben Tag
  mit der größten Überschneidung, sonst die eigene Spalte), eine Abfrage mit Fetch Join (`findForGrid()`). Im
  Screenshot (Headless Chrome, Fixtures) waren gehaltene und geplante Einheiten kaum zu unterscheiden → ✓ und fett.
  Lokal fehlte das neue CSS, bis `sass:build` lief. 128 Tests grün, PHPStan ohne Fehler.
- C4c: `PATCH /admin/einheiten/{id}` (Absage, Notiz, „Absage aufheben“ mit 409 und Bestätigung, danach Auffüllen),
  Session-Token je Kurs im Raster. `findOverlapping()` filtert die Kandidaten des Tages in PHP, weil DQL Beginn + Dauer
  nicht addieren kann. Fallstricke: `array_unique()` auf Entities vergleicht Eigenschaften (bei Collections bis zur
  Rekursion) → Schlüssel per `spl_object_id()`; Testdatum 16.10. liegt inzwischen in der Vergangenheit → `mockTime()`.
  PHPStan meldete zeitweise 39 Fehler „Type alias ConfigType contains unknown class“ in `config/reference.php`, auch auf
  dem Stand vor C4c und mit der alten Datei; nacheinander (`--debug`) keine, kurz danach auch parallel keine mehr, ohne
  Änderung. Ursache nicht gefunden (vermutlich parallele Worker), CI-Lauf beobachten. Frage zum Kursnamen nach „Jetzt“.
  133 Tests grün.
- C4d: Stimulus-Controller `grid` (Editor in der Zelle, Absage als Schalter, Notiz, Löschen, Absage aufheben,
  Doppelstunde), Endpunkte `DELETE /admin/einheiten/{id}` und `POST …/doppelstunde`. Beim Planen gefunden: `fillUp()`
  füllt Lücken ab Kursbeginn und hätte eine gelöschte Einheit in dieselbe Woche zurückgelegt → `fillUpAtEnd()`.
  Action-Parameter (`data-grid-…-param`) wandelt Stimulus um („123“ würde eine Zahl), die Notiz steht deshalb in
  `data-note`. Im Browser geprüft: Node 20 hat kein WebSocket, daher ein kleiner DevTools-Client in Python (nur
  Standardbibliothek) im Scratchpad; Absage mit Notiz, Doppelstunde, Absage aufheben mit Popup, nur Notiz ohne
  Neuladen, Löschen mit Auffüllen, keine Fehler in der Konsole. 139 Tests grün, PHPStan ohne Fehler.
- C4-0/C4e: Ferien löschen jetzt alle Einheiten im Zeitraum (vorher nur zukünftige, nicht abgesagte), aufgefüllt
  am Ende; Rückfrage per Stimulus-Controller `break-confirm` mit Liste vom Server, Server löscht nur mit
  `confirmed=1`. Ferien löschen legt die Einheiten wieder an (`restoreBreakDays()`), Wochen-Link im Raster öffnet das
  Ferienformular vorbelegt. Kursname mit abgeleiteten Wochentagen. Fallstricke: `data-action` zu entfernen, um ein
  zweites `submit` durchzulassen, wirkt zu spät (Stimulus sieht Attribute über einen MutationObserver) → Flag;
  Screenshot unterhalb des Bildschirms braucht `captureBeyondViewport`. Im Browser geprüft (Popup abbrechen → nichts
  gespeichert, bestätigen → 5 entfernt, 5 angelegt, zurück im Raster; Ferien löschen → Einheiten wieder da, dabei kam
  auch eine vorher gelöschte zurück, siehe Projektstand). 146 Tests grün, PHPStan ohne Fehler.
- C4f: Erklärung `erklaerungen/18-kursraster.md`. Damit ist C (Kurse, Teilnahmen, Raster) lokal fertig.
- C7 (Anzahl der Einheiten je Teilnahme, Teilnahme beenden): Beratung zu „Einheit ohne Ersatz löschen“ und „Absage vor
  Kursbeginn“ → Option C (Mike). `Enrollment.plannedLessons` als Kopie aus dem Kurs; Doctrines Migration hätte
  `ADD … NOT NULL` ohne Wert vorgeschlagen (scheitert bei bestehenden Zeilen) → erst nullable, aus dem Kurs befüllen,
  dann `NOT NULL`. Formular mit Bestätigungsschritt ohne JavaScript, Untergrenze = begonnene reguläre Einheiten.
  Beim Umsetzen gefunden: Der Entwurf für den Einzelfall („abgesagte Einheit stehen lassen, Anzahl −1“) geht nicht auf,
  weil Planer und Nachholregel verschieden zählen → Korrektur im Plan, Frage nach „Jetzt“. Übersetzungstest fand
  Schlüssel über eine eigene `trans()`-Hilfsmethode nicht (Extractor sieht die Domain nicht) → direkt
  `$this->translator->trans(…, 'admin')`. Im Browser geprüft (Formular, Rückfrage mit Liste, nicht bestätigt).
  154 Tests grün, PHPStan ohne Fehler.

## 2026-11-17

- D2 geplant und freigegeben (Option A: PDF in der Transaktion der Nummernvergabe; Gutschrift-Dateiname mit
  `_Gutschrift`). Geprüft: Rollback in `wrapInTransaction()` schließt den EntityManager, Nummer `MAX + 1` → keine Lücke.
- D2a: `dompdf/dompdf` 3.1.6 installiert (mit `dompdf/php-font-lib` 1.0.2, `dompdf/php-svg-lib` 1.0.2,
  `masterminds/html5` 2.12.0, `sabberworm/php-css-parser` 9.5.0). Entity `InvoicePdf` (eigene Tabelle `invoice_pdf`,
  unidirektional, `invoice_id` unique, `content bytea`), Migration `Version20261117063716`. PHPStan bemängelte
  `is_resource()` als Typprüfung (ein geschlossener Stream ist keine „resource“ mehr) → Prüfung `!is_string()`.
  154 Tests grün, PHPStan ohne Fehler, Schema validiert.
- Erklärungen: `erklaerungen/19-honorarnoten-pdf.md` neu (PHP-Extensions, Migration Zeile für Zeile), `04-composer.md`
  um „Wenn es kein Rezept gibt“ ergänzt. Versuch in der lokalen Datenbank: Identity-Spalte verliert den Wert bei
  Rollback (1, 3 statt 1, 2).
- D2b/D2c: `InvoicePdfRenderer` (Twig `admin/invoice/pdf.html.twig` → dompdf, A4, DejaVu Sans, Remote/PHP/JavaScript
  aus), Aufruf in `InvoiceIssuer::withNextNumber()` in der Transaktion, `InvoicePdfException` → Meldung und zurück
  aufs Formular (`InvoiceController::pdfFailed()`). Fallstrick gefunden: `new Options([...])` ignoriert unbekannte
  Schlüssel stillschweigend (`Options::set()` mit `method_exists`) → Setter. Messung (lokal, Dev mit Xdebug im Modus
  `develop`, zweiter Durchlauf): **122 ms, +11,1 MB Spitze, PDF 24,4 KB** bei `memory_limit` 128M. PDF im Vergleich
  mit dem Muster angesehen, Zeilenabstand angepasst. Test mit fehlschlagendem Renderer: keine Honorarnote, kein PDF,
  Meldung sichtbar. 159 Tests grün, PHPStan ohne Fehler.
- D2d: Download einzeln (`/admin/honorarnoten/{id}/pdf`) und als ZIP je Kurs (`/admin/kurse/{id}/honorarnoten.zip`),
  `Invoice::getFileName()`, Spalte „Honorarnote“ und Hinweis auf fehlende auf der Kursseite. Fehler beim Bauen:
  eigene Methode `file()` im Controller kollidiert mit `AbstractController::file()` (Fatal Error beim Laden der Klasse,
  in PHPUnit nur als langer Xdebug-Stacktrace sichtbar) → `attachment()`.
  Beim Testen gefunden: **`EnrollmentFactory` trug jede Teilnahme doppelt in `course.enrollments` ein**, weil Foundry
  2.16 die inverse Seite offenbar selbst pflegt (Versuch: 2 statt 1; im Foundry-Code nicht nachgesehen); betraf nur Tests und Fixtures in derselben Anfrage, nicht
  Produktion. Behoben mit `contains()`-Prüfung, ebenso in `InvoiceFactory`.
  Eigener Planungsfehler: Ich hatte „Fixtures legen keine Honorarnoten an“ geschrieben (in `src/DataFixtures` gesucht,
  die Fixtures liegen aber in `tests/Story/AppStory.php`). Die zwei Fixture-Honorarnoten hatten kein PDF →
  `InvoiceFactory::withPdf()`, Fixtures neu geladen (2 PDFs, je ~23 KB). Ersetzen des Renderers im Test muss jetzt vor
  dem ersten Factory-Aufruf passieren (Container: „already initialized“). 165 Tests grün, PHPStan ohne Fehler. Nicht im
  Browser angesehen (Login bräuchte den DevTools-Client); die Tests prüfen Links und Hinweis im HTML.
- Push von D2 (5 Commits): CI-Lauf 44043324903 Tests → Build → Deploy grün; Migration `Version20261117063716` in
  Produktion in 57 ms ausgeführt. Erklärung `erklaerungen/20-foundry-stories.md` (Stories) ergänzt.
- F, Datenschutz-Information: Entscheidungen (Seite und PDF, Eltern direkt angesprochen; Speicherdauer Option A, übrige
  Daten bis zum Ende des Folgesemesters, Honorarnoten und Zahlungen 7 Jahre). Konten und Absenderdaten in Produktion
  laut Mike eingetragen. EUR-Lex für den Wortlaut von Art. 13 DSGVO nicht abrufbar (HTTP 202 mit leerem Inhalt, auch
  PDF- und ELI-Adresse).
- F1: Datenschutz-Information für den Unterricht als Seite `/datenschutz/unterricht` (`PageController::privacyLessons()`,
  `templates/page/privacy_lessons.html.twig`, Texte `privacy_lessons.*` als Entwurf), Links von `/datenschutz` und
  `/unterricht`, Test `PublicPagesTest::testPrivacyNoticeForLessons()`. Mikes Antworten: ein Text für alle, Steuerberatung
  als Empfängerin (Name folgt), Mike nicht namentlich; Backups sichert Mike auf Sonjas externer Festplatte, später
  Backup-Server statt Mac. 167 Tests grün, PHPStan ohne Fehler.
- Datenschutzerklärung: Absatz zum Session-Cookie des Verwaltungsbereichs entfernt (Mike: für keine Besucher:in
  relevant, ein Hinweis weniger für Angreifer); § 165 Abs. 3 TKG 2021 dazu im RIS gelesen. Steuerberaterin
  Mag.iur. Ulla Kröll in der Datenschutz-Information Unterricht ergänzt.
- F2: PDF der Datenschutz-Information `/datenschutz/unterricht.pdf` (inline, 28 KB, 2 Seiten), Text gemeinsam mit
  der Seite aus `page/_privacy_lessons_sections.html.twig`. Aufruf und Optionen von dompdf aus `InvoicePdfRenderer`
  nach `App\Pdf\HtmlToPdf` verschoben. Im App-Cache bis zum nächsten Deploy (Abweichung vom Plan, öffentliche
  Adresse). Link oben in „Schüler:innen“ im Admin. 169 Tests grün, PHPStan ohne Fehler. Beim vollen Testlauf mit
  Xdebug (`develop`) hängt Xdebug an jede erwartete 403/404-Logzeile einen langen Stacktrace; das sah zuerst wie ein
  Fehler aus, war aber keiner (mit `XDEBUG_MODE=off` und erneut mit Xdebug: alles grün).
- Push von F (6 Commits, mit den Doku-Commits von D2): CI-Lauf 44069313057 Tests → Build → Deploy grün. Produktion:
  `/datenschutz/unterricht` und `.pdf` liefern 200 ohne Cookie, `/datenschutz` erwähnt den Verwaltungsbereich nicht mehr.
- F3 geplant und freigegeben (automatische Löschung vor dem Echtbetrieb; Reihenfolge danach B5, dann Backups ohne
  Mac). Mikes Einwand: Ein Name ist nicht anonym → bei aufzubewahrender Honorarnote wird die Schüler:in gelöscht,
  die Teilnahme bleibt ohne Person. Dateiname der Honorarnote wird mit dem PDF gespeichert (vorher beim Download aus
  dem aktuellen Nachnamen gebildet).
- F3a: `App\Privacy\RetentionPolicy` (letzte Aktivität = späteste Einheit, Schnupperstunde oder Anlegen; feste
  Semester; Honorarnoten bis 31.12. des 7. Jahres), Anzeige auf der Schülerseite und im Dashboard (30 Tage),
  `StudentRepository::findAllWithActivity()` (zwei Abfragen statt eines Joins über beide Collections). Migration
  `Version20261117142646`: `invoice_pdf.file_name` (bestehende Zeilen per SQL aus Kurs und Nachname befüllt,
  `[[:alnum:]]` erkennt Umlaute bei ctype `en_US.utf8`, lokal geprüft), `student.created_at` (Bestand: Zeitpunkt der
  Migration; neu über `Symfony\Component\Clock\now()`, damit `mockTime()` in Tests greift). Datenschutz-Information:
  Semestergrenzen ergänzt, „bleibt nur der Name“ → „bleibt nur die Honorarnote selbst“. 182 Tests grün, PHPStan ohne
  Fehler, Fixtures neu geladen.
- F3b: Befehl `app:privacy:purge` (`--dry-run`), Logik in `App\Privacy\PersonalDataPurger`, Ergebnis nur als
  Anzahlen (`PurgeResult`). Ein `flush()`, den Doctrine in einer Transaktion ausführt (`UnitOfWork::commit()`);
  Löschreihenfolge sortiert ORM 3.7.6 je Objekt (`computeDeleteExecutionOrder()`), Gutschrift vor Honorarnote klappt
  im Test ohne Zwischen-Flush. Honorarnoten zählen je Teilnahme (mit Gutschriften gemeinsam). Migration
  `Version20261117152927`: `enrollment.student_id` nullable; `Enrollment::detachStudent()` lässt `billToGuardian`
  unverändert. Beim Durchsehen gefunden und behoben: Einnahmen-CSV, Honorarnotenliste und ZIP hätten Zahlungen und
  PDFs ohne Person per Inner Join verschluckt (jetzt Left Join); `StudentRepository::notEnrolledIn()` mit `NOT IN`
  hätte bei einer einzigen NULL-Zeile niemanden mehr geliefert (`IS NOT NULL` ergänzt; Test schlug ohne die Korrektur
  fehl); der Planer hätte beim Ändern der Ferien eines alten Kurses Teilnahmen ohne Person wieder Einheiten angehängt.
  Teilnahme ohne Person: eigene, schreibgeschützte Seite, „Person gelöscht“ in Kurs, Raster, Honorarnote, Dashboard.
  Twig läuft auch in Produktion mit `strict_variables: true` (`config/packages/twig.yaml`), `null.fullName` wäre ein
  Fehler gewesen. 188 Tests grün, PHPStan ohne Fehler, Probelauf auf den Fixtures: nichts zu löschen.
- F3c vorbereitet: `deployment.md` S12 (Cronjob `app:privacy:purge` täglich 03:30, Uhrzeit von Mike freigegeben;
  Reihenfolge Push → Deploy → `--dry-run` → crontab). Server-Tabelle im Projektstand um S8 und S12 ergänzt (S8 fehlte
  dort, obwohl der Cronjob läuft).
- Push von F3 (6 Commits): CI-Lauf 44119503604 Tests → Build → Deploy grün; in Produktion 2 Migrationen
  (`Version20261117142646`, `Version20261117152927`) in 21 ms. `/`, `/datenschutz/unterricht`, `/admin/login` liefern
  200. Cronjob (S12) trägt Mike ein.
- F3c: Cronjob `app:privacy:purge` in der crontab von root eingetragen, `--dry-run` am Server geprüft (Mike). F3 fertig.
- B5 Plan freigegeben (Mike): Fingerabdruck immer, Zufallskennung, Name selbst eingeben, Verbindung nur über
  `userHandle`; Sonja hat ein MacBook Air und ein Android-Handy (Samsung A52s), kein iPhone (`rollen.md` 5 korrigiert).
- B5a: `web-auth/webauthn-symfony-bundle` installiert. Composer nahm **5.3.13** (erschienen 2026-11-17 19:20 UTC,
  wenige Stunden vor dem Einbau) statt der im Plan geprüften 5.3.12; laut Release-Notes ein Sicherheits-Release mit
  zehn Advisories (u. a. GHSA-3chw-82qh-p72w: Registrierungsoptionen für ein fremdes Konto), daher behalten. Das Flex-
  Rezept liegt in recipes-contrib und wurde übersprungen: Bundle in `config/bundles.php` von Hand eingetragen, Mapping
  der Superklasse `Webauthn\CredentialRecord` (XML im Bundle) in `doctrine.yaml` registriert. Im Code gelesen: Das
  Bundle übergibt nach dem Einrichten ein einfaches `CredentialRecord` (nicht unsere Entity), nach dem Login das
  gefundene Objekt mit neuem Zähler → `PasskeyRepository::saveCredentialRecord()` wandelt bzw. setzt „zuletzt
  genutzt“. Ohne `allowed_origins` prüft die Bibliothek Herkunft = RP-ID = Host und verlangt HTTPS
  (`CheckAllowedOrigins`). Migration `Version20261117210541`: Tabelle `passkey`, `app_user.webauthn_handle` (bestehende
  Konten per `gen_random_uuid()` befüllt, lokal geprüft: 64 Hex-Zeichen). Noch keine Routen des Bundles aktiv.
  191 Tests grün, PHPStan ohne Fehler.
- B5b: „Mein Konto“ mit Passkeys (Liste mit Name, angelegt, zuletzt genutzt; Löschen über `<details>` wie sonst; nur
  eigene, fremde → 404). Hinzufügen über die Endpunkte des Bundles (`controllers.creation.account`,
  `/admin/konto/passkeys/optionen` und `/admin/konto/passkeys`, `CurrentUserEntityGuesser`). Der fertige
  Stimulus-Controller des Bundles schickt nur das Ergebnis des Geräts; der Name kommt daher über einen eigenen
  Controller mit `@simplewebauthn/browser` 14.0.0 (2026-09-02) als Zusatzfeld `passkeyName`, das der Denormalizer
  der Bibliothek ignoriert (`PublicKeyCredentialDenormalizer` gelesen), und `PasskeyCreatedHandler` setzt ihn
  (Credential-ID aus dem Request, nur eigener Passkey). Fund: Mit eigenem Profil `default` fehlte der Knoten `rp`, das
  Bundle übergab `null` als Namen (400 beim Optionen-Abruf); `rp: { id: ~ }` behebt es ohne den veralteten
  `rp.name`. Ein ungültiges Ergebnis beantwortet das Bundle mit 400. 197 Tests grün, PHPStan ohne Fehler.
- B5c: Passkey-Login über die Firewall (`webauthn` in der Firewall `admin`, `/admin/login/passkey/optionen` und
  `/admin/login/passkey`, `PUBLIC_ACCESS`), Registrierung über die Firewall bleibt aus (`enabled: false`, geprüft mit
  `debug:config`). Knopf „Mit Passkey anmelden“ über dem Passwortformular (eigener Stimulus-Controller), danach
  Weiterleitung zur zuvor verlangten Seite (`PasskeyLoginSuccessHandler`, JSON). Geprüft im Code: `UserChecker`
  und Login-Throttling greifen auch hier; das Bundle protokolliert in einen `NullLogger`. `autofocus` auf dem
  E-Mail-Feld entfernt (Passkey ist jetzt der erste Weg). Test `PasskeyLoginTest` spielt das Gerät mit einem echten
  P-256-Schlüssel (COSE-Schlüssel per `spomky-labs/cbor-php`, Signatur mit OpenSSL): Login, Weiterleitung, Zähler,
  „zuletzt genutzt“, falscher Schlüssel und deaktiviertes Konto (Gegenprobe: ohne Deaktivierung schlägt der Test
  fehl). Fallstrick im Test: Nach einem Request hängt ein Foundry-Objekt an keinem Entity-Manager mehr, `save()`
  versucht dann ein neues Konto anzulegen. 201 Tests grün, PHPStan ohne Fehler.
- B5d (lokal): Headless-Chrome mit virtuellem Authenticator (DevTools-Protokoll `WebAuthn.addVirtualAuthenticator`,
  eigener Python-Client): **Fehler gefunden** – „Passkey hinzufügen“ scheiterte, Chrome meldete „Failed to read the
  'rp' property … Required member is undefined“. Ursache: Ohne RP-ID (`id: ~`) lässt die Bibliothek `rp` in den
  Optionen ganz weg (`PublicKeyCredentialRpEntityDenormalizer` gibt `null` zurück). Die PHP-Tests prüften `rp` nicht.
  Lösung: RP-ID für Hinzufügen und Login aus `%env(string:key:host:url:DEFAULT_URI)%` (lokal `localhost`, in
  Produktion `sonjaczak.com` über `SERVER_NAME`); ohne `string:` lehnt der Konfigurationsbaum den Wert ab. Tests
  prüfen jetzt `rp` und `rpId`. Danach im Browser: Hinzufügen, zweites Mal am selben Gerät abgelehnt („Auf diesem
  Gerät gibt es schon …“), Abmelden, `/admin/kurse` → Passkey-Login → `/admin/kurse`, „zuletzt genutzt“ gesetzt.
  `restore-test.sh` braucht keine Änderung (Tabellen dynamisch). Erklärung `erklaerungen/21-passkeys.md`, Rundgang
  9.4. 201 Tests grün, PHPStan ohne Fehler.
- 2026-11-18: Mike hat den Passkey lokal mit Touch ID eingerichtet und sich damit angemeldet. B5 gepusht.
- 2026-11-18: Erster CI-Lauf nach dem Push (44268914166) rot: PHPUnit brach mit „Allowed memory size of 134217728 bytes
  exhausted“ ab. Die CI läuft mit Xdebug (Dev-Image), lokal hatte ich mit `XDEBUG_MODE=off` getestet. Lokal wie in
  der CI nachgestellt: Spitze 145 MB, über PHPs Standard 128M. Lösung: `memory_limit` 512M nur für PHPUnit
  (`phpunit.dist.xml`); Produktion unverändert.
- 2026-11-18: CI-Lauf 44269704195 grün (Tests, Build, Deploy), Migration `Version20261117210541` in Produktion in
  50 ms (`passkey`, `webauthn_handle`). Live geprüft: Login-Seite zeigt den Passkey-Knopf, Login-Optionen liefern
  `rpId: sonjaczak.com`, `userVerification: required`, leere `allowCredentials`.
- 2026-11-18: Schüler:innen-Seite zeigt „Nimmt an keinem Kurs teil“ statt einer leeren Liste (Mike). In den Fixtures
  haben nur Nr. 1–6 einen Kurs und Nr. 7–8 eine Schnupperstunde, daher wirkte die Liste kaputt.
- 2026-11-18, C4f: Raster mit einer Zeile je Unterrichtstag statt je Woche (Vorschlag Mike). Anlass: Die Woche
  4.–10.1. war gelb (Weihnachtsferien bis Mi 6.1.), obwohl am Fr 8.1. Unterricht ist. Neu `GridWeek` (KW-Zelle mit
  `rowspan`, „ganze Ferienwoche“ = alle Unterrichtstage frei), `GridRow` je Tag; Nachholstunden an anderen Tagen in
  eigener Zeile, Doppelstunden nur im Zeitfenster des eigenen Wochentags; CSS färbt Tag und Zellen einzeln (wegen
  `rowspan` nicht mehr `> *`). Verschieben auf einen Ferientag bleibt erlaubt, mit gelbem Hinweis. Im Browser mit
  Fixtures geprüft (KW 43 mit Absagen, KW 44 Herbstferien, KW 52/53 gelb, KW 01 weiß). Fallstrick beim Einfügen der
  Methode: Sie landete zwischen dem Docblock von `readJson()` und der Methode, PHPStan bemerkte den fehlenden
  Rückgabetyp. 202 Tests grün wie in der CI (mit Xdebug), PHPStan ohne Fehler.
- 2026-11-18, C4g: Raster als Zeitachse (Vorschlag Mike), Schnupperstunden im Raster, Inhalte des Spaltenkopfs in der
  Box. Neu `GridAxis` (Achse auf volle Stunden), `GridItem` (Einheit, Schnupperstunde, frei; Spur, Überschneidungen,
  Nummer, fällige Nachholstunde), `GridRow` mit `finish()` (sortieren, Spuren, Überschneidungen, Doppelstunden);
  `GridColumn` und `columnFor()` entfallen. Position per CSS-Variablen und `calc()` (keine CSP, geprüft). Editor
  schwebend in der Achse, schließt mit Escape (Stimulus-Tastenfilter `esc`, in `stimulus.index.js` 3.2.2 vorhanden)
  und Klick daneben. Zwei Links je Box über Bootstrap `.stretched-link`. Im Browser mit Fixtures geprüft (1400 und
  600 px, Editor, Escape, Absage-Zeile); dabei gefunden: In der niedrigen Zeile verdrängte die Zählung den Namen, sie
  zeigt jetzt nur Uhrzeit, Name, Art der Absage. PHPStan fand zwei mögliche `null`-Schlüssel. 205 Tests grün wie in
  der CI (mit Xdebug), PHPStan ohne Fehler.
- 2026-11-18: Abzeichen „Nachholstunde fällig“ an allen vier Stellen als Link auf „Nachholstunde eintragen“ (Partial
  `_make_up_due.html.twig`). Im Raster liegt der Link wie der Name über dem Box-Link (`z-index`, im Browser geprüft).
  Ohne offene Absage leitet die Seite mit einem Hinweis zur Teilnahme zurück. Klargestellt: Eine Doppelstunde hebt
  „fällig“ nicht auf, weil am Ende eine Einheit wegfällt. Mein Verdacht, der Terminvorschlag für die Nachholstunde
  nehme die zuletzt angelegte statt der zeitlich letzten Einheit, war falsch: `Enrollment::$lessons` ist per
  `OrderBy(startsAt)` sortiert und auf der Seite frisch geladen. 206 Tests grün wie in der CI, PHPStan ohne Fehler.
- 2026-11-18: C4g und die Abzeichen-Links gepusht (vier Commits bis af5e284), CI 44342529876 mit Tests, Build und
  Deploy grün, live.
- 2026-11-18: Passkeys mit Sonja (B5d) verschoben, bis Mike ihr die Website zeigt; nächster Schritt sind die Backups
  ohne Mikes Mac.
- 2026-11-18: Backups in die Hetzner Storage Box begonnen (Mike), `planung/backups-ohne-mac.md` Umsetzung 1. Neu
  `deploy/backup-push.sh` (Hochladen per SFTP Port 22, Prüfung der age-Kopfzeile und der Größe, Rotation 14 Tage +
  Monatsersten, Mail bei Fehler, Monatsmail mit `status`) und `deployment.md` S13. Hetzner-Doku gelesen (Ports,
  RFC4716 für Port 22, Unterkonten, Fingerabdrücke, Snapshots). Lokal getestet in Ubuntu 24.04 (OpenSSH 9.6p1) gegen
  `atmoz/sftp`, mit Attrappen für `sonjaczak-backup` und `docker`: Hochladen, zweiter Lauf am selben Tag, Rotation
  (gelöscht 15 und 40 Tage alt und 2024-01-01, behalten 1.9., 14 und 3 Tage), Monatsmail normal und veraltet, Dump
  scheitert, falscher Host-Key, Storage Box aus; jedes Mal Mail. Dabei gefunden: Die Größenprüfung las die
  Echo-Zeile `sftp> ls -l …` mit, und die `EXIT`-Falle griff auf eine lokale Variable zu, die nach dem Ende der
  Funktion nicht mehr existiert (`unbound variable`, temporäres Verzeichnis blieb liegen). Beides behoben.
- 2026-11-19: Variante „Mac schickt in die Storage Box“ verworfen (Mike), als A2 im Plan. `deployment.md` S13
  nachgeschärft nach Mikes Fragen: Hauptkonto nur mit Passwort, nie mit dem Server-Key; Platzhalter als
  Shell-Variablen; Erklärung der zwei `ssh-keygen`-Aufrufe und des Fingerabdruck-Vergleichs; Test der Anmeldung mit
  Key. Hetzner-Doku `getting-started/creating-a-storage-box` gelesen: Ein Key beim Anlegen schaltet das Passwort nicht
  ab und lässt sich später nicht in der Console ergänzen.
- 2026-11-19: Storage Box (u724152, von Mike bestellt), AVV, Unterkonten `-sub1` Upload und `-sub2` read-only, beide
  Basisverzeichnis `backups`, External Reachability an; Snapshots eingerichtet. S13 Teil 2 am Server: Skript
  installiert, Key erzeugt. Die Box hat nur einen RSA-Host-Key (`SSH-2.0-mod_sftp`), `ssh-keyscan -t ed25519` lieferte
  eine leere Datei; RSA-Fingerabdruck stimmte mit der Hetzner-Liste. `echo "mkdir .ssh" | sudo sftp …` mit
  Passwortabfrage endete mit „Killed“ (kein OOM im Kernel-Log, Website lief weiter); interaktiv klappte es. Anmeldung
  mit Key über Port 22 (RFC4716) getestet. Gefunden: Gleiches Basisverzeichnis heißt gemeinsame `authorized_keys`;
  das Lese-Unterkonto bekommt deshalb keinen Key, nur ein Passwort.
- 2026-11-19: S13 Teil 3 und 4. Erster Lauf von Hand: `Uploaded 2026-11-19.dump.age (39519 bytes)`, Monatsmail
  angekommen. Lese-Unterkonto `-sub2`: Host-Key RSA wie beim Upload-Konto, `get` klappt, `rm` scheitert mit
  „Permission denied“ (read-only wirkt). Rücksicherung aus der Storage Box am Mac erfolgreich. Stolpersteine: Der
  private age-Schlüssel war im Passwort-Manager zeitweise nicht auffindbar (gefunden); Aufräumzeile lief mit dem
  gescheiterten Test und löschte den Download (Anleitung trennt sie jetzt); der Test wurde einmal in der SSH-Sitzung
  am Server gestartet und scheiterte an `pbpaste` (Anleitung sagt jetzt ausdrücklich „am Mac“). Der abgebrochene
  Test hinterlässt keine Testdatenbank (`trap … EXIT` in `restore-test.sh`).
- 2026-11-19: Cronjobs für S13 eingetragen (Mike), erster automatischer Snapshot angelegt, privater age-Schlüssel
  ausgedruckt. Datenschutz-Information Unterricht: Hetzner Online GmbH als Auftragsverarbeiterin der verschlüsselten
  Sicherungen (Anschrift aus hetzner.com/legal/legal-notice), Aufbewahrung „in einem Rechenzentrum in Deutschland“,
  „bis zu 13 Monate“ statt 12. Seite und PDF lokal geprüft, `PublicPagesTest` grün.
- 2026-11-20: C4h Einheiten im Raster ziehen (Plan und Antworten Mike am selben Tag). PATCH `/admin/einheiten/{id}`
  mit `startsAt`: nur geplante Einheiten, gleicher Tag, volle 5 Minuten, nicht in die Vergangenheit, keine
  Überschneidung mit Einheiten des Kurses oder Schnupperstunden (409); Antwort mit der neu gezeichneten Tageszeile.
  Ziehen mit Pointer Events in `grid_controller.js`. Tests: 209 grün, PHPStan ohne Fehler. Headless Chrome (Fixtures,
  Einheit Fr 20.11. 13:40): über einer Einheit rot, zurückgesprungen; Escape bricht ab; auf freie Zeit gespeichert,
  ohne Neuladen, Fokus auf der Box; Klick auf die neue Zeile öffnet den Editor (Stimulus verbindet die Aktionen).
  Danach Einheit per SQL auf 13:40 zurückgesetzt. Gefunden beim Test: Ein Klick in die Mitte der Box trifft den
  Namenslink (Absicht, wie C4g).
- 2026-11-20: PHPUnit in PhpStorm: Der Klick auf „Run“ lief mit Homebrew-PHP statt im Container. Docker-Compose-
  Interpreter angelegt (Mike); PhpStorm meldete „error parsing compose.yaml“ (`idea.log`: `Unrecognized field "name"`
  in `DockerComposePort`). Port-Namen aus `compose.yaml` entfernt; `docker compose config` für dev und prod vorher und
  nachher verglichen, nur die drei Zeilen fallen weg. Danach `KERNEL_CLASS` fehlte, weil PHPUnit ohne
  `phpunit.dist.xml` startete (kein Bootstrap, kein `.env.test`); mit `/app/phpunit.dist.xml` als Default configuration
  file laufen die Tests in PhpStorm (Mike).
- 2026-11-20: CI 44809022056 rot (Build und Deploy übersprungen): `PasskeyLoginTest::testLoginWithPasskey` mit
  „Invalid length for x coordinate“. Ursache im Test, nicht in `compose.yaml`: `openssl_pkey_get_details()` liefert
  Koordinaten ohne führende Null-Bytes, gemessen 31 von 3000 Schlüsseln mit 31 statt 32 Byte; `Ec2Key` verlangt genau
  32. Test füllt jetzt auf 32 Byte auf; mit einem gezielt erzeugten kurzen Schlüssel geprüft (ohne Auffüllen
  abgelehnt, mit Auffüllen Signatur gültig).  Danach CI 44809710222 grün (Tests, Build, Deploy); `compose.yaml` ohne Port-Namen live: HTTPS 200 über HTTP/2,
  `alt-svc: h3=":443"` (HTTP/3 angeboten), HTTP leitet mit 308 auf HTTPS weiter.
- 2026-11-20: Web Debug Toolbar: `composer require --dev symfony/profiler-pack` (web-profiler-bundle v8.1.11,
  stopwatch). Flex-Rezept legte `config/packages/web_profiler.yaml` und `config/routes/web_profiler.yaml` an, trug das
  Bundle für `dev`/`test` ein und löschte dabei den Kommentar über `WebauthnBundle` in `config/bundles.php`
  (wiederhergestellt). Lokal: `x-debug-token-link` im Header, Leiste im HTML. Tests, PHPStan, YAML-Lint und
  `cache:warmup` grün.  Nach dem Deploy (CI 44812400968 grün) in Produktion geprüft: Startseite 200 ohne `x-debug-token`, `/_profiler/` und
  `/_wdt/…` antworten 404.
- 2026-11-20: Raster zeigt in der Box auch die Endzeit, „15:30-16:20 · 1/15“ (Mike, `GridItem::endTime()`). Zwei Tests
  auf den neuen Text angepasst (`CourseGridTest`, `LessonPatchTest`); 209 Tests und PHPStan grün.
- 2026-11-21: CI 44819502054 (Mikes Commit 9bd9089, Halbgeviertstrich „15:30–16:20“ in der Box) rot, Build und Deploy
  übersprungen: `CourseGridTest` und `LessonPatchTest` erwarteten noch den Bindestrich. Tests und Doku angeglichen,
  jetzt wie die Zeitanzeige beim Ziehen.
- 2026-11-21: CI 44944512370 grün (Tests, Build, Deploy), HTTPS 200.
- 2026-11-21: Backups S13 Teil 5, Test gegen Löschen (Mike): Drei automatische Snapshots in der Console (19., 20., 21.,
  je 0 B). Upload-Unterkonto `u724152-sub1` per SFTP vom Server: `ls /.zfs/snapshot` „not found“, auch nach Einschalten
  von „Display snapshot directory“ (war aus); `/` ist für das Unterkonto der Ordner `backups` (mit `.ssh`).
  `2026-11-20.dump.age` mit dem Upload-Key gelöscht, mit dem Hauptkonto vom Mac aus `/.zfs/snapshot/…/backups/`
  geholt: 39519 Bytes wie vorher, `age -d` ok. Hetzner-Doku (Snapshots) gelesen: Snapshot-Inhalt nur über
  `/.zfs/snapshot`, „Restore“ setzt die ganze Box zurück.
- 2026-11-23: Plan `planung/wartung.md` (W1–W5) und `erklaerungen/22-wartung.md` geschrieben, freigegeben mit allen
  Empfehlungen (Mike). Gemessen: `app.css` 243102 B, mit Brotli 30177 B, gzip 37844 B; Bootstrap-JS 61061 B (Brotli
  16510 B); Mercure-Hub antwortet in Produktion (`/.well-known/mercure?topic=test` 400, `…/subscriptions` 401);
  `composer audit` lokal ohne Befund (Composer 2.11.1); letzter CI-Lauf 45133550109 schon auf "Image: ubuntu-26.04".
- 2026-11-23: W2: `runs-on: ubuntu-26.04` in allen drei Jobs.
- 2026-11-23: W1: Schritt „Audit Composer packages“ (`composer audit --locked --abandoned=report`) im Job „Tests“.
  Lokal geprüft: Kopie von `composer.lock` mit `symfony/http-kernel` v4.4.0 → 3 Advisories, Exit-Code 1; echte
  Lock-Datei → keine, Exit-Code 0. Erklärung `erklaerungen/23-assetmapper.md` (AssetMapper, Alternativen).
- 2026-11-23: W3: Mercure-Hub und Vulcain aus `Caddyfile`, `MERCURE_*` und Flex-Marker aus den Compose-Dateien, CI-Schritt
  „Check Mercure reachability“ und `CADDY_MERCURE_JWT_SECRET` aus der CI entfernt. Danach startete der lokale Container
  nicht: „unable to enable hot reloading: no Mercure hub configured“ (`hot_reload` in `compose.override.yaml`). Entwurf
  per `git stash` beiseite, Variante A2 (Mike): Hub nur lokal über `CADDY_SERVER_EXTRA_DIRECTIVES`. Zweiter Fehler „only
  one unnamed Mercure hub is allowed per configuration“ (Site-Block für `localhost, php:80`), behoben mit `name default`.
  Lokal: Startseite 200, Hub antwortet, nach `touch templates/base.html.twig` kommt das Hot-Reload-Ereignis an;
  `docker compose -f compose.yaml -f compose.prod.yaml config` ohne Mercure-Variable gültig.
- 2026-11-23: W4: Seite „System“ (`/admin/system`, `SystemController`, `App\System\OpcacheStatus`), nur `ROLE_SUPER_ADMIN`
  (`#[IsGranted]` und `access_control`), Eintrag in der Navigation. Beim Ansehen im lokalen FrankenPHP: „Reserviert
  0,0 MB“, „Belegt −198,5 MB“. Ursache: Im Worker (ZTS) meldet `opcache_get_configuration()` die Speichergrößen als 0
  und `used_memory` als minus `free_memory`; `ini_get()` liefert 256. Korrigiert: Größe aus `ini_get()`, Belegt =
  Reserviert − Frei − Verschwendet. Lokal danach 256 MB reserviert, 70,6 MB belegt (13,7 MB Interned Strings), 2.411
  Dateien. 215 Tests, PHPStan ohne Fehler (nach `rm -rf /tmp/phpstan` wegen des neuen Ordners `src/System`).
- 2026-11-23: W5 probeweise umgesetzt und gemessen: Bootstrap-Teilimport (ohne zwölf ungenutzte Komponenten, 40
  ungenutzte Gruppen von Hilfsklassen, responsive Hilfsklassen nur für `flex-direction`) hätte `app.css` von 242.990
  auf 149.551 B verkleinert (gzip 33.824 → 22.884 B); 66 Screenshots und Regelvergleich ohne Unterschied. Mike hat
  verworfen: Ersparnis nicht spürbar, alle Klassen sollen verfügbar bleiben. Bootstrap-JS einzeln hätte 51.303 statt
  61.171 B ergeben, ebenfalls nicht umgesetzt.
- 2026-11-23: Mini-CMS (`planung/cms.md`) nach Mikes Entscheidungen umgesetzt: Tabelle `page_text` (Migration
  `Version20261123202219`, dazu `settings.lesson_price_cents` mit Vorgabe 49500), `PageTextTranslator` als Decorator um
  `translator` (nur Domain `content`, eine Abfrage je Anfrage, `reset()` nach jeder Anfrage; im Worker per `curl`
  geprüft, dass ein geänderter Text sofort und nach dem Löschen wieder der YAML-Text erscheint). Admin „Texte“ mit
  Liste nach Seite, Bearbeiten, Marker und Zurücksetzen. Preis und Einheiten auf `/unterricht` als Platzhalter aus den
  Einstellungen, „Neuer Kurs“ mit dem Preis vorbelegt. `PageTexts` brauchte `#[Autowire(service: 'translator')]`
  (kein Autowiring-Alias für `TranslatorBagInterface`); `AdminRoutesRequireLoginTest` kennt jetzt den Platzhalter
  `{key}` (`x.y`). 223 Tests grün, PHPStan ohne Fehler, prod `cache:warmup` ok. Nicht committet.
- 2026-11-24: Mike wünscht Bearbeiten direkt auf der Seite statt der Liste. Plan `planung/cms.md` K4 mit Fragen 1–4
  (Session auf öffentlichen Seiten, Schalter, Liste behalten, Seitentitel). `PageTextTranslator` nutzt jetzt
  Symfonys Formatter (`translator.formatter`) statt eines eigenen `strtr()`, damit `%count%` und übersetzbare
  Parameter wie bei YAML-Texten funktionieren; Tests grün, per `curl` mit `%price%` geprüft. Erklärung
  `erklaerungen/25-seitentexte-decorator.md`, PDF neu gebaut.
- 2026-11-24: K4 Bearbeiten direkt auf der Seite (Mike: 1 A, 2 Schalter, 3 Liste behalten, 4 nur Seitentexte).
  Firewall ohne `pattern`, `cookie_path: /`; `PageTextEditorExtension` (`page_text_attr()`, `page_text_editor()`), 13
  Templates markiert, Makros mit `attrs`, leere Abschnitte für Admins versteckt im HTML. Leiste mit Schalter
  (`sessionStorage`), Stimulus-Controller `page_editor`, JSON-Endpunkte mit Session-Token `page-text`. Im echten Chrome
  (eigener DevTools-Client) zwei Fehler gefunden: `data-action` wirkt nicht im Feld außerhalb des Controllers
  (Formular wurde normal abgeschickt, nichts gespeichert); Stift im Akkordeon-Knopf und Feld im `<h2>`. Beide behoben,
  danach Speichern, Fehlermeldung, Zurücksetzen und Akkordeon geprüft. Lokal gemessen: PHP nimmt bei zwei Cookies
  gleichen Namens das erste; der Browser schickt das mit `/admin` zuerst → vor dem Push in Produktion abmelden.
  230 Tests grün, PHPStan ohne Fehler, prod `cache:warmup` ok. Nicht committet.
- 2026-11-24: Hinweis im Bearbeitungsfeld je Art des Textes (Liste, Absätze, Zeilen, einzeilig, „leer blendet aus“),
  Art in `PageTexts::kind()`, `PageTextKindTest` vergleicht mit dem gerenderten HTML aller 9 öffentlichen Seiten
  (111 Prüfungen). Im Browser geprüft. 231 Tests grün, PHPStan ohne Fehler. Mike hat sich in Produktion abgemeldet;
  K1–K4 committet und gepusht.
- 2026-11-24: CI-Lauf 45401161376 rot im Schritt `composer audit` (W1 hat zum ersten Mal angeschlagen): neue Meldungen
  zu `twig/twig` (9, meist Sandbox, die wir nicht nutzen; CVE-2026-133771 Autoescaping) und
  `web-auth/webauthn-symfony-bundle` (Benutzerprüfung beim Anlegen eines Passkeys). Kein Deploy, Produktion unverändert.
  Update `twig/twig` 3.30.0 → 3.31.1, `web-auth/webauthn-lib` und `-symfony-bundle` 5.3.13 → 5.3.17 (Composer hebt
  wegen `bump-after-update` auch die Untergrenze in `composer.json`). Danach meldete ein Test „Unknown function
  page_text_editor“: veralteter Test-Cache, nach `rm -rf var/cache/test` zweimal 231 Tests grün.
- 2026-11-24: Mike hat K4 in Produktion getestet, funktioniert. Admin-Bereich oben: Link „Zur Website“ (nur angemeldet,
  wie die ganze Navigation; die Leiste auf der Website hatte schon „Admin-Bereich“). Test
  `testAdminAreaLinksToTheWebsiteOnlyWhenLoggedIn`. Nicht committet.
