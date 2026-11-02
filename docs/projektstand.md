# Projektstand: sonjaczak.com

Stand: 2026-11-02 (übernommen aus dem Projektstand-Doc auf claude.ai; ab jetzt ist diese Datei maßgeblich)

## Aktueller Stand

Der VPS ist aufgeräumt, aktualisiert und abgesichert. Lokal läuft Symfony 8.1.10 (Skeleton) im Template
dunglas/symfony-docker; Git-Repo ist angelegt (noch ohne GitHub-Remote). Doctrine/PostgreSQL ist noch nicht installiert.

**Projekt-Setup**

- [x] Git-Repo (Branch `main`), `.gitignore` mit `/instructions_draft.md`; `.idea/` und `.DS_Store` über globale Ignore-Datei
- [x] Template dunglas/symfony-docker übernommen (Commit `92d0d49`, ohne dessen `docs/`, `.devcontainer/`, `README.md`)
- [x] Symfony 8.1 per `SYMFONY_VERSION=8.1.*` installiert; `LICENSE` und `AGENTS.md` des Skeletons entfernt,
      `composer.json` auf `sonjaczak/website` / `proprietary` angepasst
- [x] Dockerfile, Compose-Dateien und `frankenphp/` gemeinsam durchgegangen
- [ ] Doctrine (`symfony/orm-pack`) installieren → PostgreSQL-Container kommt per Flex-Rezept; vorher Image-Version
      und RAM-Bedarf prüfen

**Vor dem ersten Deploy prüfen** (aus der Durchsicht des Templates)

- `opcache.memory_consumption = 256` in `frankenphp/conf.d/10-app.ini`: tatsächlichen Verbrauch messen, ggf. senken (850 MiB RAM)
- Mercure und Vulcain sind im `Caddyfile` aktiv, werden aber nicht genutzt: entfernen oder behalten?
- `docker-entrypoint.sh` führt Migrationen bei jedem Containerstart aus: mit dem geplanten Deploy-Schritt „danach Migrationen“ abstimmen
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

1. Projekt-Setup abschließen: Doctrine mit PostgreSQL (siehe oben).
2. GitHub-Repository anlegen; Pipeline zuerst nur mit Tests (PHPUnit, PHPStan).
3. Build-Stage: Produktions-Image bauen, mit Commit-Hash taggen, nach ghcr.io pushen.
4. Auf dem Server einen deploy-Benutzer ohne sudo anlegen; Deploy-Stage per SSH (docker compose pull/up, danach Migrationen, alte Images aufräumen).
5. DNS für sonjaczak.com auf den VPS zeigen lassen; HTTPS über Caddy/FrankenPHP.
6. Planung der App: Anforderungen, Datenmodell (ER-Diagramm), Rollenkonzept.

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
