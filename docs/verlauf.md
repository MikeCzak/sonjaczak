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
