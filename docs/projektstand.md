# Projektstand: sonjaczak.com

Stand: 2026-11-02 (übernommen aus dem Projektstand-Doc auf claude.ai; ab jetzt ist diese Datei maßgeblich)

## Aktueller Stand

Der VPS ist aufgeräumt, aktualisiert und abgesichert. Lokal laufen Symfony 8.1.10 (Skeleton) im Template
dunglas/symfony-docker und Doctrine ORM mit PostgreSQL 18.6; das Repo liegt auf GitHub. Noch keine Entities,
keine Migrationen, keine CI.

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
- `docker-entrypoint.sh` führt Migrationen bei jedem Containerstart aus: mit dem geplanten Deploy-Schritt „danach Migrationen“ abstimmen.
  Achtung: Der Entrypoint sucht in `./migrations`, das Migrations-Rezept legt sie aber in `src/Migrations` ab →
  der automatische Lauf greift derzeit nie.
- `POSTGRES_PASSWORD` hat in `compose.yaml` den Standard `!ChangeMe!`: in `compose.prod.yaml` ohne Standardwert
  erzwingen (wie `APP_SECRET`), Wert aus der Umgebungsdatei auf dem Server
- `shared_buffers` (128MB Standard) für den Server in `compose.prod.yaml` begrenzen
- Auf dem Server nur `-f compose.yaml -f compose.prod.yaml` verwenden, damit `compose.override.yaml`
  (veröffentlicht Port 5432) nicht geladen wird
- Backups der Datenbank (DSGVO) einplanen
- Repo ist privat → Image in ghcr.io ist privat. Server braucht zum Pullen einen Personal Access Token (classic) mit
  nur `read:packages` (Quelle: GitHub-Doku „Working with the Container registry“)
- Alte Images in ghcr.io aufräumen (derzeit kostenlos, laut GitHub-Doku „currently free“, Änderung mit 1 Monat Vorlauf)
- Anzahl der FrankenPHP-Worker und deren RAM-Bedarf in der FrankenPHP-Doku prüfen
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

## Nächste Schritte

Ziel der ersten Phase: eine leere Seite, die bei jedem `git push` automatisch auf sonjaczak.com live geht.
Erst danach werden Features gebaut.

1. Auf dem Server einen deploy-Benutzer ohne sudo anlegen; Deploy-Stage per SSH (docker compose pull/up, danach Migrationen, alte Images aufräumen).
2. DNS für sonjaczak.com auf den VPS zeigen lassen; HTTPS über Caddy/FrankenPHP.
3. Planung der App: Anforderungen, Datenmodell (ER-Diagramm), Rollenkonzept.

**Termin:** Upgrade auf Symfony 8.2 vor Ende Januar 2027 (dann endet der Support für 8.1). Danach 8.3 (Mai 2027)
und 8.4 LTS (laut Release-Regeln voraussichtlich November 2027).

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

## Offene Entscheidungen

| Thema | Optionen | Wann |
|---|---|---|
| CSS-Framework | Bootstrap (viel Erfahrung) oder Tailwind | Vor dem ersten Template |
| Symfony UX | Turbo, Live Components oder Kombination für die dynamischen Formulare | Bei Booking- und Lessons-Formular |
| Mail-Versand | Externer SMTP-Dienst für Formular-Mails | Vor dem ersten Formular |
| Postfächer | Mail-Hosting für Sonjas Adressen unter sonjaczak.com | Unabhängig, sobald Domain-Setup steht |
| Admin-Bereich | Login und Rollen: entschieden, ja. Sonja legt Unterrichtssemester und deren Termine an, verwaltet Schüler:innen mit persönlichen und Rechnungsdaten und erstellt daraus Honorarnoten. Details offen; personenbezogene Daten erfordern DSGVO-Maßnahmen (Zugriffsschutz, Backups) | Bei der App-Planung |

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
| Benutzer | mike (sudo); später deploy (ohne sudo) für GitHub Actions | `id mike` |
