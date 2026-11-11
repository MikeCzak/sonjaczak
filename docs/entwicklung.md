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
kompilieren. Der Befehl läuft **im Container** (dort liegen PHP und der Sass-Compiler); du startest ihn aber vom Mac
aus, im Projektordner, mit `docker compose exec`. Während du am CSS arbeitest, in einem zweiten Terminal
(z. B. zweiter Tab im Terminal der IDE):

```bash
docker compose exec php bin/console sass:build --watch    # kompiliert bei jeder Änderung neu, Strg+C beendet
```

Die SCSS-Dateien bearbeitest du ganz normal in der IDE auf dem Mac; über den Bind-Mount sieht der Container jede
Änderung sofort, `--watch` baut neu, im Browser neu laden.

Einmalig reicht `docker compose exec php bin/console sass:build`. Ohne kompiliertes CSS zeigt Symfony einen
Fehler, dass die Datei fehlt (Status 500). In der CI und beim Bau des Prod-Images passiert das automatisch.

Beim Start des Containers baut der Entrypoint das CSS automatisch (`frankenphp/docker-entrypoint.sh`, nur
außerhalb von `prod`). Nötig ist das, weil das Ergebnis in `var/sass/` liegt und `var/` ein anonymes Volume ist, das
mit dem Container gelöscht wird. Schlägt der Build fehl, startet der Container trotzdem; Seiten liefern dann 500,
bis `sass:build` erfolgreich war (Meldung in `docker compose logs php`).

## Bilder für die Website

Originalfotos sind oft 5000 px breit, mehrere MB groß und enthalten Metadaten (Kamera, Autor, teils GPS-Position).
Das Skript `bin/build-images` erzeugt daraus die Web-Versionen. Es läuft **auf dem Mac**, nicht im Container, weil
nur der Mac die Originale in `claude_references/` hat. Voraussetzung: ImageMagick (`brew install imagemagick`).

```bash
bin/build-images
```

Pro Bild entstehen:

| Datei | Zweck |
|---|---|
| `assets/images/<name>-640.webp`, `-1280.webp`, `-1920.webp` | WebP in drei Breiten, nie breiter als das Original |
| `assets/images/<name>.jpg` | JPEG (1280 px) für Browser ohne WebP |
| `config/packages/images.yaml` | Breiten und Maße aller Bilder (Twig-Global `images`), nicht von Hand bearbeiten |

Alle Dateien werden ohne Metadaten gespeichert (`-strip`), Qualität 78.

**Neues Bild:** eine Zeile `<name>|<Pfad in claude_references>` in der Liste `IMAGES` im Skript ergänzen, Skript
laufen lassen, `assets/images/` und `config/packages/images.yaml` committen. Danach einmal
`docker compose exec php bin/console cache:clear`, damit Symfony die neue Liste kennt.

**Im Template einbinden** mit dem Makro aus `templates/_macros/image.html.twig`:

```twig
{% import '_macros/image.html.twig' as media %}
{{ media.image('hero-sonja2', '', {class: 'sc-hero__image', position: '60% 35%', priority: true}) }}
```

- Zweites Argument: Alternativtext. Leer (`''`) nur bei dekorativen Bildern, deren Inhalt schon im Text steht
  (z. B. Hero mit dem Namen als Überschrift).
- `position` bestimmt beim Zuschneiden den Fixpunkt: `50% 50%` ist die Mitte, `60% 35%` hält die Stelle etwas
  rechts der Mitte im oberen Drittel (das Gesicht) im Bild.
- `priority: true` nur für das erste Bild einer Seite (lädt sofort); alle anderen laden erst, wenn sie in die Nähe
  des sichtbaren Bereichs kommen.
- `sizes` (Standard: volle Breite) sagt dem Browser, wie breit das Bild angezeigt wird, damit er die passende Datei
  wählt, z. B. `'(min-width: 992px) 40vw, 100vw'` für ein Bild neben Text.

**Scroll-Effekte** stehen in `assets/styles/partials/_motion.scss` (Stärke über die Werte in den `@keyframes`).
Zum Prüfen ohne Effekte: in macOS unter Bedienungshilfen → Anzeige → „Bewegung reduzieren“ einschalten.

Originale gehören nicht ins Repo (`claude_references/` ist ignoriert). Bildnachweis im Impressum ergänzen
(`imprint.credits` in `content.de.yaml`).

## Designvorschau (Schritt 3, vorübergehend)

Bis zur Entscheidung zwischen den Designvarianten:

- `https://localhost/_design` – Styleguide: Logo-Kandidaten, Schrift, Farben mit Kontrastwerten, Bedienelemente
- `?design=a`, `?design=b`, `?design=c` an einer beliebigen URL wählt die Variante; sie bleibt für den Browser-Tab
  gespeichert (sessionStorage), unten rechts erscheint ein Umschalter. `?design=off` beendet die Vorschau.
- Funktioniert genauso auf https://sonjaczak.com (für Sonja), die Seite `/_design` ist nicht verlinkt und `noindex`.

## Texte und Fehlerseite

- Seitentexte stehen in `translations/content.de.yaml` (Seite.Abschnitt), Oberflächentexte in `messages.de.yaml`.
  Mehrzeilige Werte (`|-`): Leerzeile = neuer Absatz, in Listen ist jede Zeile ein Punkt. Ein leerer Text (`''`)
  blendet den Abschnitt aus. Entwürfe von Claude sind mit `# Draft` markiert.
- Nach dem Ändern prüfen: `docker compose exec php bin/console debug:translation de --only-missing`.
- Die 404-Seite zeigt Symfony nur ohne Debug-Modus. Lokal ansehen: https://localhost/_error/404

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
