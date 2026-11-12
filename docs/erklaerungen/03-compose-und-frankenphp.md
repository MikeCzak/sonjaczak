# 03 – Compose-Dateien und FrankenPHP-Konfiguration

Dateien: `compose.yaml`, `compose.override.yaml`, `compose.prod.yaml`, `frankenphp/Caddyfile`,
`frankenphp/docker-entrypoint.sh`, `frankenphp/conf.d/*.ini`.
Grundlagen zu Compose, Volumes und Netzwerken: [01-grundkonzepte.md](01-grundkonzepte.md).

## Konzepte

### Zwei Arten von Variablen – die häufigste Verwirrung

| | Compose-Interpolation | Container-Umgebung |
|---|---|---|
| Schreibweise | `${VAR}`, `${VAR:-standard}`, `${VAR:?fehler}` in der YAML | Einträge unter `environment:` |
| Wann | **beim Einlesen** der YAML durch `docker compose` | **zur Laufzeit** im Container |
| Woher | Shell-Umgebung, `.env` neben der Compose-Datei bzw. `--env-file` | was unter `environment:` steht |
| Wer sieht es | nur Compose | der Prozess im Container (PHP, Caddy) |

Beispiel aus `compose.prod.yaml`:
```yaml
APP_SECRET: ${APP_SECRET:?APP_SECRET must be set}
```
Links der Name der Variable **im Container**, rechts ein Ausdruck, den **Compose** beim Einlesen auflöst – auf dem
Server aus `/srv/sonjaczak/.env`.

Interpolations-Formen (Compose-Doku *Interpolation*):
- `${VAR}` – Wert oder leer
- `${VAR:-default}` – Wert, oder `default`, wenn nicht gesetzt oder leer
- `${VAR:?meldung}` – Wert, sonst **Abbruch** mit `meldung`. Damit startet Prod nie versehentlich mit Standardpasswort.

### Zusammenführen mehrerer Dateien

Compose führt Dateien der Reihe nach zusammen: einzelne Werte (z. B. `image`) werden überschrieben, Listen wie `ports`
und `volumes` werden ergänzt, `environment`-Einträge pro Schlüssel überschrieben. Prüfen kann man das Ergebnis jederzeit:

```bash
docker compose config                                       # lokal (compose.yaml + override)
docker compose -f compose.yaml -f compose.prod.yaml config  # wie in Prod
```
Sehr nützlich zum Verstehen – und Achtung: zeigt eingesetzte Secrets im Klartext.

---

## `compose.yaml` – die gemeinsame Basis

```yaml
services:
  php:
    restart: unless-stopped
```
Startet den Container nach einem Absturz oder einem Server-Neustart (unattended-upgrades startet um 04:00 neu)
automatisch, außer man hat ihn manuell gestoppt. Alternativen: `no`, `always`, `on-failure`.

```yaml
    environment:
      SERVER_NAME: ${SERVER_NAME:-localhost}, php:80
```
Für welche Adressen Caddy zuständig ist (wird im Caddyfile als Site-Adresse eingesetzt). Auf dem Server:
`sonjaczak.com, php:80`. Caddy holt für `sonjaczak.com` ein Zertifikat; `php:80` (Hostname im Docker-Netzwerk, nur
HTTP) erlaubt internen Aufrufen von anderen Containern, Caddy ohne TLS zu erreichen. **Die Domain in `SERVER_NAME` ist
entscheidend** – deshalb funktioniert lokal `https://localhost`, aber nicht `https://127.0.0.1` (siehe
[../entwicklung.md](../entwicklung.md)).

```yaml
      DEFAULT_URI: ${DEFAULT_URI:-https://${SERVER_NAME:-localhost}:${HTTPS_PORT:-443}}
```
Basis-URL, mit der Symfony absolute Links erzeugt, wenn es keine Anfrage gibt (z. B. Links in E-Mails aus einem
Konsolenbefehl).

```yaml
      MERCURE_TRUSTED_ISSUERS: ...
      MERCURE_PUBLISHER_JWT_KEY: ${CADDY_MERCURE_JWT_SECRET:-!ChangeThisMercureHubJWTSecretKey!}
      MERCURE_SUBSCRIBER_JWT_KEY: ...
      MERCURE_URL / MERCURE_PUBLIC_URL / MERCURE_JWT_SECRET / MERCURE_JWT_ISSUER
```
Konfiguration für **Mercure** – ein im Caddy eingebauter Dienst für Echtzeit-Updates an den Browser (Server-Sent
Events). Die Schlüssel signieren JWTs, mit denen Symfony Nachrichten veröffentlichen darf. Wir nutzen Mercure derzeit
nicht (offene Entscheidung im Projektstand). Die `!ChangeThis…`-Standardwerte sind öffentlich bekannt – in Prod
erzwingt `compose.prod.yaml` eigene Werte.

```yaml
      DATABASE_URL: postgresql://${POSTGRES_USER:-app}:${POSTGRES_PASSWORD:-!ChangeMe!}@database:5432/${POSTGRES_DB:-app}?serverVersion=${POSTGRES_VERSION:-18}&charset=${POSTGRES_CHARSET:-utf8}
```
Verbindungsdaten für Doctrine als URL: Benutzer, Passwort, **Host `database`** (Service-Name im Docker-Netzwerk),
Port, Datenbankname. `serverVersion=18` sagt Doctrine, welchen SQL-Dialekt es erzeugen soll, ohne vorher die
Datenbank fragen zu müssen. Weil `POSTGRES_PASSWORD` hier **und** beim `database`-Service aus derselben Variable kommt,
passen beide automatisch zusammen.

```yaml
    volumes:
      - caddy_data:/data
      - caddy_config:/config
```
Caddys Zertifikate und Konfiguration bleiben über Container-Neustarts erhalten. Ohne `caddy_data` würde bei jedem
Deploy ein neues Zertifikat angefordert – Let's Encrypt begrenzt das.

```yaml
    ports:
      - { name: http,  target: 80,  published: ${HTTP_PORT:-80},   protocol: tcp }
      - { name: https, target: 443, published: ${HTTPS_PORT:-443}, protocol: tcp }
      - { name: http3, target: 443, published: ${HTTP3_PORT:-443}, protocol: udp }
```
Veröffentlichte Ports: `target` = Port im Container, `published` = Port auf dem Host. HTTP/3 läuft über **UDP**
(Protokoll QUIC) – deshalb die ufw-Regel für 443/udp.

```yaml
###> symfony/mercure-bundle ###
###< symfony/mercure-bundle ###
```
Leere Flex-Marker: verhindern, dass das Mercure-Recipe bei Installation einen **zusätzlichen** Mercure-Container
einträgt (Mercure ist ja schon in Caddy eingebaut).

```yaml
  database:
    image: postgres:${POSTGRES_VERSION:-18}-alpine
```
Offizielles PostgreSQL-Image, Alpine-Variante (kleiner). Major-Version 18 fest, Patch-Versionen kommen automatisch.
**Major-Upgrades** (18 → 19) gehen **nicht** durch einfaches Ändern der Zahl: Das Datenformat ändert sich, man braucht
Dump/Restore oder `pg_upgrade`.

```yaml
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-app}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-!ChangeMe!}
      POSTGRES_USER: ${POSTGRES_USER:-app}
```
Werden vom Image **nur beim ersten Start mit leerem Datenverzeichnis** ausgewertet (Datenbank und Benutzer anlegen).
Später ändern = keine Wirkung (Fallstrick in [../deployment.md](../deployment.md)).

```yaml
    healthcheck:
      test: ["CMD", "pg_isready", "-d", "${POSTGRES_DB:-app}", "-U", "${POSTGRES_USER:-app}"]
      timeout: 5s
      retries: 5
      start_period: 60s
```
`pg_isready` fragt, ob PostgreSQL Verbindungen annimmt. Gleiche Idee wie der Healthcheck im Dockerfile; `up --wait`
wartet darauf.

```yaml
    volumes:
      - database_data:/var/lib/postgresql:rw
```
**Die Daten.** Ab PostgreSQL 18 liegt das Datenverzeichnis unter `/var/lib/postgresql/18/docker`, deshalb das Volume
eine Ebene höher (Kommentar in der Datei, Quelle: Docker-Hub-Seite des Images). Bei der falschen Stelle würden die
Daten in der Container-Schicht landen und beim nächsten Deploy verschwinden – so etwas fällt erst auf, wenn es zu spät
ist. Deshalb haben wir Persistenz über `down`/`up` getestet.

```yaml
volumes:
  caddy_data:
  caddy_config:
  database_data:
```
Deklaration der benannten Volumes. Auf dem Server heißen sie mit Projekt-Präfix `sonjaczak_database_data` usw.

---

## `compose.override.yaml` – nur lokal

Compose lädt diese Datei automatisch, wenn kein `-f` angegeben ist. Server und CI-Build geben `-f` an → sie wird dort
nie geladen.

```yaml
  php:
    image: ${IMAGES_PREFIX:-}app-php-dev
    build:
      context: .
      target: frankenphp_dev
```
Lokal das **Dev-Image** bauen (Stage `frankenphp_dev`), Kontext = Projektordner.

```yaml
    volumes:
      - ./:/app
      - ./frankenphp/Caddyfile:/etc/frankenphp/Caddyfile:ro
      - ./frankenphp/conf.d/20-app.dev.ini:/usr/local/etc/php/app.conf.d/20-app.dev.ini:ro
      - /app/var
```
- `./:/app` – **Bind-Mount** des Projekts: Code-Änderungen sofort im Container, ohne Neubau.
- Caddyfile und Dev-ini ebenfalls eingeblendet, `:ro` = nur lesbar für den Container.
- `/app/var` – anonymes Volume „überdeckt“ `var/` im Bind-Mount: Cache und Logs bleiben im Container (schneller auf
  dem Mac, weil Dateizugriffe über die VM-Grenze langsam sind).

```yaml
    environment:
      FRANKENPHP_WORKER_CONFIG: watch
      FRANKENPHP_SITE_CONFIG: hot_reload
      MERCURE_EXTRA_DIRECTIVES: playground
      XDEBUG_MODE: "${XDEBUG_MODE:-develop}"
      APP_ENV: "${APP_ENV:-dev}"
```
- `watch` – Worker starten bei Änderungen an PHP/YAML/Twig/.env neu (FrankenPHP-Doku).
- `hot_reload` – Browser lädt bei Änderungen neu (FrankenPHP-Doku *Hot reloading*, nicht im Detail geprüft).
- `playground` – Mercure-Testoberfläche; laut Mercure-Doku **nur für Dev** (vergibt einen Token mit Vollzugriff).
- `XDEBUG_MODE`, `APP_ENV` – überschreibbar über die Shell, z. B. `XDEBUG_MODE=debug docker compose up --wait`.

```yaml
    extra_hosts:
      - host.docker.internal:host-gateway
    tty: true
```
- `host.docker.internal` – Hostname, unter dem der Container den Host (deinen Mac/PhpStorm) erreicht; Xdebug verbindet
  sich dorthin. Auf Docker Desktop gibt es den Namen ohnehin, die Zeile sorgt dafür, dass es auch unter Linux klappt.
- `tty: true` – Terminal zuweisen, damit Logs farbig erscheinen.

```yaml
  database:
    ports:
      - "5432"
```
DB-Port **lokal** veröffentlicht – ohne Host-Port-Angabe nimmt Docker einen zufälligen freien Port
(`docker compose port database 5432` zeigt ihn). So kannst du mit einem DB-Client vom Mac aus zugreifen.
In Prod verboten (harte Regel, siehe [01](01-grundkonzepte.md#6-netzwerke-und-ports)).

---

## `compose.prod.yaml` – CI-Build und Server

```yaml
  php:
    image: ${IMAGES_PREFIX:-}app-php-prod:${IMAGE_TAG:-latest}
    build:
      context: .
      target: frankenphp_prod
```
Name des Prod-Images, z. B. `ghcr.io/mikeczak/sonjaczak/app-php-prod:9cd534a…`. **Dieselbe Zeile** dient zwei Zwecken:
In der CI liest Bake daraus, wie das gebaute Image heißen soll; auf dem Server sagt sie Compose, welches Image es
starten soll. So können CI und Server nicht auseinanderlaufen. `build:` wird auf dem Server ignoriert, weil
`deploy.sh` `--no-build` angibt (harte Regel: nie auf dem Server bauen).

```yaml
    environment:
      APP_SECRET: ${APP_SECRET:?APP_SECRET must be set}
      MERCURE_PUBLISHER_JWT_KEY: ${CADDY_MERCURE_JWT_SECRET:?...}
      MERCURE_SUBSCRIBER_JWT_KEY: ${CADDY_MERCURE_JWT_SECRET:?...}
```
Pflicht-Secrets. `APP_SECRET` nutzt Symfony u. a. für CSRF-Tokens, signierte URLs und „Remember me“-Cookies – wer ihn
kennt, kann solche Tokens fälschen. Darum zufällig, lang, geheim und pro Umgebung verschieden.

```yaml
  database:
    restart: unless-stopped
    environment:
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:?POSTGRES_PASSWORD must be set}
    command: ["postgres", "-c", "shared_buffers=32MB", "-c", "max_connections=20"]
```
- Neustart nach Absturz/Reboot wie bei php.
- Passwort Pflicht, kein Rückfall auf `!ChangeMe!`.
- `command` überschreibt den Startbefehl: `shared_buffers` (PostgreSQLs eigener Seiten-Cache; Standard 128MB) auf
  32MB und `max_connections` (Standard 100, jede Verbindung kostet RAM) auf 20. Bei 2 FrankenPHP-Workern mit je einer
  Verbindung plus Konsolenbefehlen reicht das reichlich. Gemessen: ~35 MiB auf dem Server.

---

## `frankenphp/Caddyfile`

Das Caddyfile ist Caddys Konfigurationsformat. `{$VAR}` sind Umgebungsvariablen, die Caddy beim Start einsetzt;
`{$VAR:standard}` mit Standardwert; `{env.VAR}` wird erst bei Benutzung ausgewertet.

```caddy
{
	skip_install_trust
	{$CADDY_GLOBAL_OPTIONS}
	frankenphp {
		{$FRANKENPHP_CONFIG}
	}
}
```
**Globaler Block** (Optionen für den ganzen Server):
- `skip_install_trust` – Caddy versucht sonst, seine lokale Zertifizierungsstelle (für `localhost`) in den
  Vertrauensspeicher des Systems einzutragen; im Container sinnlos. Deshalb musst du die Caddy-CA auf dem Mac selbst
  vertrauen (siehe [../entwicklung.md](../entwicklung.md)).
- `frankenphp { … }` – globale FrankenPHP-Optionen (Threads, Worker, `max_requests` …); bei uns leer.
- Die `{$…}`-Platzhalter erlauben Anpassungen über Umgebungsvariablen, ohne die Datei zu ändern.

```caddy
{$CADDY_EXTRA_CONFIG}

{$SERVER_NAME:localhost} {
```
**Site-Block**: gilt für die Adressen in `SERVER_NAME`. Für jeden Domainnamen darin aktiviert Caddy automatisches HTTPS.

```caddy
	log {
		{$CADDY_SERVER_LOG_OPTIONS}
		format filter {
			request>uri query {
				replace authorization REDACTED
			}
		}
	}
```
Zugriffslog, aber der Query-Parameter `authorization` (kann einen Mercure-Token enthalten) wird durch `REDACTED`
ersetzt. **Prinzip, das wir später brauchen:** Geheimnisse und personenbezogene Daten gehören nicht in Logs (DSGVO).

```caddy
	root /app/public
	encode zstd br gzip
```
- `root` – Dokumentenverzeichnis; nur `public/` ist von außen erreichbar. Code, `.env`, `vendor/` liegen außerhalb –
  das ist eine wichtige Schutzschicht.
- `encode` – Antworten komprimieren, in dieser Vorzugsreihenfolge (je nachdem, was der Browser kann).

```caddy
	mercure { … anonymous … subscriptions … }
	vulcain
```
- Mercure-Hub unter `/.well-known/mercure` (siehe oben). `anonymous` erlaubt Abonnenten ohne Token, nur **öffentliche**
  Updates zu empfangen; `subscriptions` aktiviert die Abo-API (beides laut Mercure-Doku standardmäßig aus – der Kommentar
  in der Datei sagt zu Recht „double-check that it's what you want“).
- `vulcain` – Protokoll-Erweiterung für APIs (Preload/Push verknüpfter Ressourcen). Nutzen wir nicht.
- → Beides ist offene Entscheidung: Nicht genutzte Funktionen sind unnötige Angriffsfläche. Entfernen ist einfach.

```caddy
	@phpRoute {
		not path /.well-known/mercure*
		not file {path}
	}
	rewrite @phpRoute index.php
```
**Front-Controller-Muster:** `@phpRoute` ist ein „Matcher“ (benannte Bedingung): alle Anfragen, die **nicht** Mercure
sind und für die **keine Datei** in `public/` existiert. Diese werden intern auf `index.php` umgeschrieben. So landet
`/lessons` bei Symfony, `/favicon.ico` wird direkt als Datei ausgeliefert.

```caddy
	@frontController path index.php
	php @frontController {
		{$FRANKENPHP_SITE_CONFIG}
		worker {
			file ./public/index.php
			{$FRANKENPHP_WORKER_CONFIG}
		}
	}
```
Nur `index.php` wird von PHP ausgeführt – **im Worker-Modus**: `public/index.php` bootet Symfony einmal und bearbeitet
dann viele Anfragen (siehe [01](01-grundkonzepte.md#7-wie-php-ausgeführt-wird-drei-modelle)). Anzahl Worker: Standard
2 × CPU. Die FrankenPHP-Doku nennt `php_server` als üblichen Weg; das Template nutzt die niedrigere Stufe `php` mit
eigenem Matcher, um genau zu steuern, was PHP erreicht.

```caddy
	file_server {
		hide *.php
	}
```
Liefert statische Dateien aus `public/` aus. `hide *.php` – PHP-Dateien werden **nie** als Text ausgeliefert (sonst
könnte man Quellcode lesen, falls eine PHP-Datei nicht über den PHP-Handler läuft).

### Weiterleitung `www` → ohne `www` (seit 2026-11-12)

Ein Caddyfile besteht aus **Site-Blöcken**: Vor der geschweiften Klammer steht die Adresse, für die der Block gilt.
Der Haupt-Block heißt `{$SERVER_NAME:localhost}`, gilt also nur für `sonjaczak.com`. Eine Anfrage an
`www.sonjaczak.com` passt auf keinen Block, deshalb hätte Caddy dafür weder ein Zertifikat noch eine Antwort.

Der Block für `www` steht nicht im Caddyfile selbst, sondern in der Variable `CADDY_EXTRA_CONFIG` in
`compose.prod.yaml`. Das Caddyfile fügt sie in Zeile 11 ein (`{$CADDY_EXTRA_CONFIG}`), außerhalb des Haupt-Blocks.
Compose setzt dabei `SERVER_NAME` aus `/srv/sonjaczak/.env` ein. Am Server ergibt das:

```caddy
www.sonjaczak.com {
	redir https://sonjaczak.com{uri} permanent
}
```

- **Eigener Site-Block → eigenes Zertifikat:** Caddy holt für jede Adresse eines Site-Blocks automatisch ein
  Zertifikat (Caddy-Doku *Automatic HTTPS*). Voraussetzung ist der DNS-Eintrag für `www`. Ohne Zertifikat zeigt der
  Browser bei `https://www…` eine Warnung, bevor die Weiterleitung überhaupt greift.
- **`redir … permanent`:** antwortet mit Status 301 und `Location: https://sonjaczak.com/<pfad>?<query>` (`{uri}` ist
  Pfad plus Query). Geprüft mit `frankenphp adapt`, das das Caddyfile in Caddys JSON übersetzt: `static_response`
  mit `status_code: 301`. Browser und Suchmaschinen merken sich 301 als dauerhaft. Suchmaschinen führen beide
  Adressen dann als eine, statt doppelte Inhalte zu sehen.
- **Warum nicht im Caddyfile?** Lokal und im CI-Smoke-Test heißt der Server `localhost`. Im Caddyfile fest
  eingetragen, würde Caddy auch dort ein Zertifikat für `www.sonjaczak.com` anfordern, was ohne passenden DNS-Eintrag
  fehlschlägt und die Logs füllt. Über `compose.prod.yaml` gilt der Block nur in Produktion. Im CI-Smoke-Test entsteht
  `www.localhost`, für das Caddy wie für `localhost` seine eigene lokale Zertifizierungsstelle nutzt.
- **Alternative:** `SERVER_NAME=sonjaczak.com, www.sonjaczak.com` würde beide Adressen mit derselben Seite bedienen,
  ohne Weiterleitung. Dann gäbe es jede Seite unter zwei Adressen (schlecht für Suchmaschinen), und absolute Links,
  die Symfony aus der aufgerufenen Adresse erzeugt, hätten mal `www`, mal nicht.
- **Alternative:** Weiterleitung im IONOS-Kundenkonto. Dann zeigt `www` auf einen IONOS-Server statt auf unseren,
  und ob dort HTTPS für `www` funktioniert, hängt vom IONOS-Produkt ab. Bei Caddy liegt alles an einer Stelle und ist
  im Repo versioniert.

---

## `frankenphp/docker-entrypoint.sh`

Läuft bei jedem Containerstart vor dem eigentlichen Befehl.

```sh
#!/bin/sh
set -e
if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
```
Nur ausführen, wenn der Container FrankenPHP, PHP oder die Konsole startet – nicht z. B. bei `sh` zum Debuggen.

```sh
	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi
```
Lokal: Ist `vendor/` leer (frisch geklont), Pakete installieren. In Prod ist `vendor/` im Image, der Zweig greift nie
(dort gibt es auch kein Composer).

```sh
	php bin/console -V
```
Gibt Symfony-Version aus – und scheitert sichtbar, falls die App gar nicht bootet.

```sh
	if grep -q ^DATABASE_URL= .env; then
		… bis zu 60 Versuche: php bin/console dbal:run-sql -q "SELECT 1" …
		Exit-Code 255 → unbehebbarer Fehler, sofort abbrechen
	fi
```
Wartet, bis die Datenbank antwortet. Nötig, weil beide Container gleichzeitig starten und PostgreSQL ein paar Sekunden
braucht. (Alternative wäre `depends_on: condition: service_healthy` in Compose.)

```sh
		if find ./migrations -iname '*.php' -print -quit | grep --quiet .; then
			php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
		fi
```
Würde Migrationen beim Start ausführen – **greift bei uns bewusst nie**, weil unser Doctrine-Recipe die Migrationen in
`src/Migrations` ablegt, nicht `./migrations`. Migrationen führt nur `deploy.sh` aus. Warum nur eine Stelle?
Bei automatischer Ausführung im Entrypoint liefe die Migration bei **jedem** Neustart des Containers mit (auch nachts
nach dem Reboot), und bei mehreren Containern parallel. Explizit im Deploy-Schritt ist nachvollziehbarer.

```sh
exec docker-php-entrypoint "$@"
```
`exec` **ersetzt** den Shell-Prozess durch den eigentlichen Befehl (CMD, hier `frankenphp run …`). Dadurch ist
FrankenPHP PID 1 und erhält Stop-Signale direkt – sauberes Herunterfahren statt hartem Kill nach 10 s.
`docker-php-entrypoint` kommt aus dem offiziellen PHP-Image.

---

## `frankenphp/conf.d/*.ini` – PHP-Einstellungen

Reihenfolge: `php.ini` → `conf.d/*.ini` (Extensions) → `app.conf.d/*.ini` alphabetisch (`10-…` vor `20-…`).

### `10-app.ini` (alle Umgebungen)

| Einstellung | Bedeutung |
|---|---|
| `expose_php = 0` | Kein `X-Powered-By: PHP/8.5.12`-Header. Verrät Angreifern nicht die Version (kleine Maßnahme, ersetzt keine Updates) |
| `date.timezone = UTC` | Server rechnet in UTC. **Best Practice:** in UTC speichern, für die Anzeige nach Europe/Vienna umrechnen – vermeidet Sommerzeit-Fehler bei Unterrichtsterminen |
| `apc.enable_cli = 1` | APCu auch in Konsolenbefehlen aktiv (sonst verhalten sich Cache-Pools dort anders) |
| `session.use_strict_mode = 1` | PHP akzeptiert keine vom Client erfundenen Session-IDs → Schutz vor **Session Fixation** (Angreifer schiebt dem Opfer eine bekannte Session-ID unter) |
| `zend.detect_unicode = 0` | Keine Prüfung auf Unicode-BOM am Dateianfang; Performance (nicht im Detail geprüft) |
| `realpath_cache_size = 4096K`, `realpath_cache_ttl = 600` | PHP merkt sich aufgelöste Dateipfade länger. Werte laut Symfony-Doku *Performance* |
| `opcache.interned_strings_buffer = 32` | Speicher für gleiche Strings (Klassennamen). Symfony-Doku: Standard 8 MB ist zu wenig |
| `opcache.max_accelerated_files = 32531` | Max. Anzahl gecachter Dateien (Wert aus der Symfony-Doku) |
| `opcache.memory_consumption = 256` | Max. RAM für OPcache in MB. **Obergrenze, nicht Verbrauch** – offener Punkt: bei wachsender App messen, ggf. senken |
| `opcache.enable_file_override = 1` | `file_exists()` u. Ä. nutzen den OPcache (nicht in der Symfony-Doku; nicht im Detail geprüft) |

### `20-app.prod.ini` (nur Prod)

| Einstellung | Bedeutung |
|---|---|
| `opcache.preload = /app/config/preload.php` | **Preloading:** Beim Start lädt PHP die meistgenutzten Klassen einmal in den Speicher (Liste erzeugt Symfony beim Cache-Aufbau). Schnellere Anfragen |
| `opcache.preload_user = www-data` | Pflicht für Preloading laut Symfony-Doku: unter welchem Benutzer vorgeladen wird |
| `opcache.validate_timestamps = 0` | PHP prüft nie, ob sich Dateien geändert haben. Laut Symfony-Doku muss man nach jedem Deploy den OPcache leeren – bei uns automatisch erfüllt, weil jeder Deploy einen **neuen Container** startet |

### `20-app.dev.ini` (nur Dev)

`xdebug.client_host = host.docker.internal` – Xdebug verbindet sich zu PhpStorm auf dem Mac.

---

## Alternativen

- **Eine Compose-Datei mit Profilen** (`profiles:`) statt Override-Dateien – möglich, aber Dev/Prod-Unterschiede sind
  hier zahlreich; das Override-Muster ist Template- und Docker-Standard.
- **Secrets über Docker Secrets** (`secrets:` in Compose, als Datei im Container unter `/run/secrets/`) statt
  Umgebungsvariablen: Umgebungsvariablen sind über `docker inspect` lesbar und können in Fehlermeldungen/Dumps landen.
  Symfony kann Secrets aus Dateien lesen (`%env(file:…)%`). Für uns: Wer `docker inspect` darf, ist ohnehin root –
  Umgebungsvariablen sind hier vertretbar, Docker Secrets wären die sauberere Stufe.
- **Symfony Secrets Vault** (`bin/console secrets:set`, verschlüsselt im Repo, nur der Entschlüsselungsschlüssel auf
  dem Server) – elegante Alternative für App-Secrets wie SMTP-Passwörter; prüfen, wenn Mail-Versand kommt.
- **Nginx statt Caddy** – siehe [01](01-grundkonzepte.md#7-wie-php-ausgeführt-wird-drei-modelle).
- **Datenbank als verwalteter Dienst** (z. B. von IONOS) – Backups und Updates inklusive, aber Kosten und
  Netzwerklatenz; für unsere Größe nicht nötig.

## Sicherheit und Best Practices – Checkliste

| Praxis | Status |
|---|---|
| DB-Port in Prod nicht veröffentlicht | ✅ nur in `compose.override.yaml` |
| Keine Standardpasswörter in Prod | ✅ `${VAR:?}` erzwingt Werte |
| Nur `public/` vom Webserver erreichbar | ✅ `root /app/public`, `hide *.php` |
| Secrets/Tokens nicht in Logs | ✅ für Mercure-Token; später für eigene Logs beachten (DSGVO) |
| Ungenutzte Dienste abschalten | ⚠️ Mercure (mit `anonymous`, `subscriptions`) und Vulcain aktiv, aber ungenutzt → entscheiden |
| Automatischer Neustart | ✅ `restart: unless-stopped` |
| Persistente Daten im Volume | ✅ getestet; ❌ Backups fehlen noch |
| Worker-Modus: kein Zustand zwischen Anfragen | Beim Schreiben eigener Services beachten |
