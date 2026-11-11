# Lokale Entwicklung

Wie die Entwicklungsumgebung funktioniert und wie der tägliche Ablauf aussieht. Begriffe: [begriffe.md](begriffe.md).

## Das Bild im Kopf

```
Dein Mac                                   Docker (Docker Desktop)
─────────────────────────────              ──────────────────────────────────────────
IDE, Git, Browser                          Container "php" (FrankenPHP = Caddy + PHP 8.5)
                                             /app  ◄── Bind-Mount ── Projektordner
Projektordner sonjaczak/  ─────────────►     /app/var  (anonymes Volume, nur im Container)
                                             Ports 80, 443 → auf dem Mac veröffentlicht
https://localhost  ───────────────────►    
                                           Container "database" (PostgreSQL 18)
                                             Daten im Volume database_data
                                             Port 5432 → zufälliger Port auf dem Mac
```

- **Code bearbeitest du auf dem Mac** in der IDE. Durch den Bind-Mount (`./:/app` in `compose.override.yaml`) sieht
  der Container jede Änderung sofort. Kein Kopieren, kein Neubauen.
- **PHP, Composer und `bin/console` laufen im Container**, nie lokal. Dafür `docker compose exec php …`.
- **Git läuft auf dem Mac.**
- `vendor/` liegt im Bind-Mount, also auch auf deinem Mac – dadurch kennt die IDE alle Klassen für Autovervollständigung.
- `var/` (Cache, Logs) liegt nur im Container. Logs: `docker compose exec php tail -f var/log/dev.log`.

## Täglicher Ablauf

```bash
# 1. Docker Desktop starten (muss laufen)

# 2. Umgebung starten – wartet, bis beide Container "healthy" sind
docker compose up --wait

# 3. Im Browser öffnen
#    https://localhost

# 4. Arbeiten: Code ändern, Seite neu laden – Änderungen wirken sofort
#    (Worker-Modus mit --watch startet PHP bei Dateiänderungen selbst neu)

# 5. Abends stoppen (Daten in den Volumes bleiben erhalten)
docker compose down --remove-orphans
```

Wann **neu bauen** (`docker compose up --build --wait`)? Nur wenn sich `Dockerfile`, `compose*.yaml` oder
`frankenphp/` geändert haben – z. B. nachdem ein Flex-Rezept eine PHP-Extension ins Dockerfile geschrieben hat.
Normale Code-Änderungen brauchen das nie.

## CSS bearbeiten (Sass)

Das CSS entsteht aus `assets/styles/app.scss` (Bootstrap + eigene Styles). Nach einer Änderung muss Sass neu
kompilieren. Während du am CSS arbeitest, in einem zweiten Terminal:

```bash
docker compose exec php bin/console sass:build --watch    # kompiliert bei jeder Änderung neu, Strg+C beendet
```

Einmalig reicht `docker compose exec php bin/console sass:build`. Ohne kompiliertes CSS zeigt Symfony einen
Fehler, dass die Datei fehlt. In der CI und beim Bau des Prod-Images passiert das automatisch.

## Häufige Befehle

```bash
docker compose ps                               # Status der Container
docker compose logs -f php                      # Logs von Caddy/FrankenPHP live (Strg+C beendet nur die Anzeige)
docker compose exec php bin/console …           # Symfony-Konsole, z. B. debug:router
docker compose exec php composer require …      # Paket installieren
docker compose exec php sh                      # Shell im Container
docker compose exec database psql -U app app    # PostgreSQL-Konsole
docker stats --no-stream                        # RAM/CPU der Container
```

**Vorsicht:** `docker compose down -v` löscht zusätzlich die Volumes – lokal also die Datenbank und die
lokale Zertifizierungsstelle von Caddy.

## Warum `https://localhost`, aber nicht `https://127.0.0.1`?

Caddy stellt nur für die Namen in `SERVER_NAME` ein Zertifikat aus; lokal ist das `localhost, php:80`
(`compose.yaml`). Rufst du `https://127.0.0.1` auf, fragt der Browser beim TLS-Handshake nach einem Zertifikat für
`127.0.0.1` – dafür hat Caddy keines und bricht ab (`tlsv1 alert internal error`). Der Container läuft trotzdem.
`http://localhost` leitet automatisch auf `https://localhost` um.

## Zertifikatswarnung im Browser

Caddy erzeugt lokal eine eigene Zertifizierungsstelle („Caddy Local Authority“). Dein Mac vertraut ihr nicht,
daher die Warnung. Entweder einmal im Browser akzeptieren oder dauerhaft vertrauen
(aus der Template-Doku `docs/tls.md`, Commit 92d0d49):

```bash
docker cp $(docker compose ps -q php):/data/caddy/pki/authorities/local/root.crt /tmp/root.crt \
  && sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain /tmp/root.crt
```

Firefox hat einen eigenen Zertifikatsspeicher (dort unter Einstellungen → Zertifikate → Zertifizierungsstellen
importieren). Nach `docker compose down -v` gibt es eine neue Zertifizierungsstelle, dann den Schritt wiederholen.

## Datenbank aus der IDE erreichen

Der Port 5432 des Containers wird auf einen **zufälligen** Port des Macs gelegt (`compose.override.yaml`).
Welcher es gerade ist:

```bash
docker compose port database 5432
```

Zugangsdaten lokal: Host `127.0.0.1`, Datenbank `app`, Benutzer `app`, Passwort `!ChangeMe!` (Standardwerte aus
`compose.yaml`; nur lokal, in Produktion kommt das Passwort aus der Umgebungsdatei auf dem Server).

## Xdebug

Standardmäßig aus (Performance). Einschalten für eine Sitzung:

```bash
XDEBUG_MODE=develop,debug docker compose up --wait
```

PhpStorm: Server `localhost`, Port 443, Path-Mapping Projektordner → `/app` (Template-Doku `docs/xdebug.md`).
