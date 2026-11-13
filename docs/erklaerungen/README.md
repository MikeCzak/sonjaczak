# Erklärungen zum Setup

Nachschlagewerk zu allem, was in Phase 1 aufgebaut wurde. Jede Datei folgt demselben Aufbau:

1. **Konzepte** – was dahintersteckt und warum es das gibt
2. **Zeile für Zeile** – die konkrete Datei im Repo
3. **Alternativen** – was man stattdessen hätte tun können, und warum wir es nicht getan haben
4. **Sicherheit und Best Practices** – Risiken und wie das Setup damit umgeht

| Datei | Inhalt |
|---|---|
| [01-grundkonzepte.md](01-grundkonzepte.md) | Container, Images, Layer, Registry, Compose, Volumes, Netzwerke; PHP-Ausführungsmodelle; Symfony Flex |
| [02-dockerfile.md](02-dockerfile.md) | `Dockerfile` Zeile für Zeile, Multi-Stage-Builds |
| [03-compose-und-frankenphp.md](03-compose-und-frankenphp.md) | `compose*.yaml`, `Caddyfile`, Entrypoint, PHP-ini-Dateien |
| [04-composer.md](04-composer.md) | `composer.json` Zeile für Zeile, Lock-Dateien, Flex und Recipes |
| [05-ci-cd.md](05-ci-cd.md) | `.github/workflows/ci.yaml` Zeile für Zeile, `deploy/deploy.sh`, Secrets |
| [06-stdin-und-eof.md](06-stdin-und-eof.md) | Warum `docker login --password-stdin` mit Strg+D endet |
| [07-dependabot.md](07-dependabot.md) | Dependabot: alerts, security updates, version updates; `dependabot.yaml` Zeile für Zeile |
| [08-login-und-sessions.md](08-login-und-sessions.md) | Admin-Login: Firewall, Rollen, Passwörter, Sessions in PostgreSQL, Logout, erstes Konto, Passwort vergessen, Benutzerverwaltung und Voter |
| [09-datenbank-schueler.md](09-datenbank-schueler.md) | Schüler:innen und Elternteile: Embeddable, Owning/Inverse Side, Migration, Formulare, Suche |
| [10-datenbank-kurse.md](10-datenbank-kurse.md) | Kurse und Ferien: Enums, Geld in Cent, Cascade/orphanRemoval, OpenHolidays mit Scoped Client und Mock |
| [11-teilnahmen-und-einheiten.md](11-teilnahmen-und-einheiten.md) | Teilnahmen, Einheiten, Schnupperstunden: Unique über zwei Spalten, Zeiten ohne Zeitzone, Selbstreferenz, Einheiten erzeugen, Nachholregel |
| [12-einstellungen.md](12-einstellungen.md) | Einstellungen: eine Zeile per Primärschlüssel, Worker-Modus, Unterschrift als BLOB, IBAN/BIC |
| [13-honorarnoten.md](13-honorarnoten.md) | Honorarnoten, Zahlungen, Gutschriften, Erinnerungen, Einnahmen-Export: unveränderliche Entity, Empfänger als Kopie (verschachteltes Embeddable), Nummer je Jahr mit Advisory Lock |
| [14-dashboard.md](14-dashboard.md) | Dashboard: Fetch Join gegen N+1, Fallstrick gefilterter Fetch Join, Clock in Tests |

Ergänzend: [../begriffe.md](../begriffe.md) (Glossar), [../entwicklung.md](../entwicklung.md) (lokaler Workflow),
[../deployment.md](../deployment.md) (Server-Einrichtung, Rollback).

**Zu den Quellen:** Fakten zu Optionen und Standardwerten sind gegen die verlinkte Primärquelle oder den installierten
Code geprüft (Stand 2026-11-03). Stellen, die Einschätzung oder Begründung sind, sind als solche formuliert. Wo etwas
nicht geprüft wurde, steht „(nicht geprüft)“.

Geprüfte Versionen im Image: FrankenPHP 1.13.4, PHP 8.5.12, Caddy 2.11.7, Basis Debian 13 „trixie“
(`frankenphp version`, `/etc/os-release` im Image `dunglas/frankenphp:1-php8.5`).
