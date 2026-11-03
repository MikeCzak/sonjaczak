# 02 – Das Dockerfile

Datei: [`/Dockerfile`](../../Dockerfile) (aus dem Template dunglas/symfony-docker, eine Zeile von Flex ergänzt).
Referenz: Docker-Doku *Dockerfile reference* (docs.docker.com/reference/dockerfile/).

## Konzepte

### Was ein Dockerfile ist

Ein Dockerfile ist ein **Bauplan** für ein Image: eine Liste von Anweisungen, die nacheinander ausgeführt werden.
Jede Anweisung, die Dateien ändert, erzeugt einen Layer (siehe [01-grundkonzepte.md](01-grundkonzepte.md#2-image-und-layer)).
Gebaut wird von **BuildKit**, der Build-Engine von Docker. Beim Bauen bekommt BuildKit den **Build-Kontext** – den
Projektordner, abzüglich allem, was in `.dockerignore` steht. Nur Dateien im Kontext kann `COPY` ins Image holen.

### Multi-Stage-Builds

Unser Dockerfile enthält **mehrere `FROM`-Anweisungen**. Jede beginnt eine neue **Stage** (Bauabschnitt) mit einem
Namen (`AS …`). Eine Stage kann auf einer anderen aufbauen (`FROM frankenphp_base AS frankenphp_dev`) oder Dateien
aus ihr kopieren (`COPY --from=…`). Gebaut wird nur, was die gewählte **Ziel-Stage** (`target` in Compose) braucht.

```
dunglas/frankenphp:1-php8.5   (frankenphp_upstream – das fertige Image vom Docker Hub)
        │
        ▼
frankenphp_base               gemeinsame Basis: Extensions, Composer, Config, Entrypoint
   │                │
   ▼                ▼
frankenphp_dev    frankenphp_prod_builder    baut die App: composer install, Cache, Autoloader
(lokal, Xdebug)         │
                        │ COPY --from: nur die fertigen Dateien
                        ▼
debian:13-slim ──►  frankenphp_prod          schlankes Laufzeit-Image, läuft als www-data
```

**Warum so?** Zum Bauen braucht man Werkzeuge (Composer, git, apt-Paketlisten, Compiler-Abhängigkeiten), zum Laufen
nicht. Das Prod-Image startet deshalb von einem **leeren** Debian und bekommt nur die fertigen Ergebnisse. Vorteile:
kleiner (unser Prod-Image: 301 MB), weniger Angriffsfläche (kein Composer, kein git, kein apt-Index), und Dev und Prod
teilen sich trotzdem dieselbe Basis-Konfiguration.

### Reihenfolge und Cache

BuildKit nimmt einen Layer aus dem Cache, solange die Anweisung und **alle Eingaben** (bei `COPY`: die Dateien) gleich
sind. Sobald ein Layer neu gebaut werden muss, werden **alle folgenden** ebenfalls neu gebaut. Darum gilt:
Selten Geändertes nach oben, häufig Geändertes nach unten. Das Dockerfile installiert deshalb zuerst Systempakete, dann
PHP-Pakete (nur abhängig von `composer.json`/`.lock`) und kopiert erst ganz am Ende den Quellcode.

---

## Zeile für Zeile

### Kopf

```dockerfile
#syntax=docker/dockerfile:1
```
**Parser-Direktive**: legt fest, welche Version der Dockerfile-Syntax BuildKit verwenden soll. `1` heißt „neueste
Version 1.x“ – BuildKit lädt dafür das Image `docker/dockerfile:1` als „Frontend“. Ohne die Zeile nimmt BuildKit die
eingebaute Version, die je nach Docker-Version älter sein kann. Wir brauchen eine aktuelle Version, z. B. für
`COPY --exclude` (laut Doku ab Syntax 1.19).
Muss ganz oben stehen, sonst ist es ein normaler Kommentar.

```dockerfile
FROM dunglas/frankenphp:1-php8.5 AS frankenphp_upstream
```
Basis-Image vom Docker Hub. Tag `1-php8.5` = FrankenPHP-Major-Version 1, PHP 8.5. Geprüft: darin stecken
FrankenPHP 1.13.4, PHP 8.5.12, Caddy 2.11.7 auf Debian 13. Das Tag ist **verschiebbar** – jeder Build mit `--pull`
holt die aktuellste 1.x/8.5.x-Version, also automatisch Sicherheitsupdates, aber auch unangekündigte Minor-Änderungen.
Die eigene Stage `frankenphp_upstream` ist nur ein Alias, damit die Version an einer Stelle steht.

### Stage `frankenphp_base`

```dockerfile
FROM frankenphp_upstream AS frankenphp_base
```
Neue Stage auf Basis des Upstream-Images.

```dockerfile
SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]
```
Standard-Shell für `RUN` ist `/bin/sh -c`. Hier wird Bash mit strengen Optionen eingestellt:
- `-e` – bei einem fehlgeschlagenen Befehl sofort abbrechen (sonst würde der Build trotz Fehler weiterlaufen)
- `-u` – Zugriff auf nicht gesetzte Variable ist ein Fehler (fängt Tippfehler in Variablennamen)
- `-x` – jeden Befehl vor der Ausführung ausgeben (Build-Log wird nachvollziehbar)
- `-o pipefail` – in `a | b` zählt auch ein Fehler von `a` (sonst nur der Exit-Code von `b`)

Gute Praxis für jedes Shell-Skript, nicht nur in Dockerfiles. Unser `deploy.sh` nutzt `set -eu` aus demselben Grund.

```dockerfile
WORKDIR /app
```
Arbeitsverzeichnis für alle folgenden Anweisungen und für den Container zur Laufzeit; wird angelegt, falls es fehlt.

```dockerfile
# persistent deps
# hadolint ignore=DL3008
RUN <<-EOF
	apt-get update
	apt-get install -y --no-install-recommends \
		file \
		git
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip
	rm -rf /var/lib/apt/lists/*
EOF
```
- `# hadolint ignore=DL3008` – Anweisung an **hadolint** (Linter für Dockerfiles), die Regel DL3008 hier zu
  ignorieren. DL3008 verlangt feste Versionen bei `apt-get install`; das Template verzichtet bewusst darauf
  (Versionen von Debian-Paketen innerhalb einer Debian-Version ändern sich nur durch Sicherheitsupdates).
- `RUN <<-EOF … EOF` – **Heredoc**: mehrere Zeilen als ein Skript, ohne `&& \` am Zeilenende. Ein einziges `RUN` =
  ein Layer. `<<-` (mit Bindestrich) entfernt führende **Tabs** – deshalb ist der Block mit Tabs eingerückt. Die Doku
  verweist dafür auf die POSIX-Shell-Regeln.
- `apt-get update` – Paketlisten von Debian laden (nötig vor jedem `install` in einem frischen Image).
- `apt-get install -y --no-install-recommends file git` – `-y` beantwortet Rückfragen mit Ja (kein Terminal im Build);
  `--no-install-recommends` installiert nur Pflicht-Abhängigkeiten, keine „empfohlenen“ Extras → kleineres Image.
  `file` erkennt Dateitypen (Symfony Mime nutzt es für Uploads), `git` braucht Composer für manche Pakete.
- `install-php-extensions` – Hilfsskript aus dem Projekt *mlocati/docker-php-extension-installer* (geprüft im Image).
  Es installiert PHP-Extensions samt Systembibliotheken und räumt Build-Werkzeuge wieder ab.
  - `@composer` – installiert Composer selbst
  - `apcu` – Cache im gemeinsamen Speicher (Symfony nutzt ihn z. B. für Cache-Pools)
  - `intl` – Internationalisierung (Datum, Zahlen, Übersetzungen, Sortierung nach Sprache) – wichtig für uns
  - `opcache` – speichert kompilierten PHP-Bytecode, damit Dateien nicht bei jeder Anfrage neu geparst werden
  - `zip` – Composer entpackt Pakete damit
- `rm -rf /var/lib/apt/lists/*` – Paketlisten wieder löschen. Sie sind nur zum Installieren nötig und würden das Image
  vergrößern. Muss **im selben `RUN`** passieren: Ein Löschen in einem späteren Layer macht das Image nicht kleiner,
  weil der frühere Layer die Dateien weiterhin enthält.

```dockerfile
ENV COMPOSER_ALLOW_SUPERUSER=1
```
Composer warnt, wenn es als root läuft (Plugins und Skripte aus Paketen hätten dann root-Rechte). Im Build-Container
ist root unvermeidbar und harmlos; die Variable unterdrückt die Warnung. Composer-Doku: „only set this if you always
run Composer as a superuser, such as in Docker containers“.

```dockerfile
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"
```
PHP liest neben `php.ini` alle `*.ini` eines „Scan-Verzeichnisses“. Diese Variable setzt eine Liste solcher
Verzeichnisse. Der **führende Doppelpunkt** ist ein leerer Eintrag – laut PHP-Doku heißt das: zusätzlich das
Standard-Verzeichnis (`conf.d`, in dem die Extensions eingetragen sind) einlesen, und danach `app.conf.d` mit unseren
Einstellungen. So überschreibt unsere Konfiguration die Standardwerte, ohne Dateien des Basis-Images anzufassen.
`$PHP_INI_DIR` ist im Basis-Image `/usr/local/etc/php` (geprüft).

```dockerfile
###> recipes ###
###> doctrine/doctrine-bundle ###
RUN install-php-extensions pdo_pgsql
###< doctrine/doctrine-bundle ###
###< recipes ###
```
Die `###>`-Markierungen gehören **Flex**: Das Doctrine-Recipe hat diesen Block eingefügt und kann ihn bei
`composer remove` wieder entfernen. `pdo_pgsql` ist der PostgreSQL-Treiber für PDO, den Doctrine DBAL nutzt.
Nicht von Hand zwischen die Marker schreiben – Flex könnte es überschreiben.

```dockerfile
COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile
```
Kopiert unsere Konfiguration ins Image (Inhalt siehe [03-compose-und-frankenphp.md](03-compose-und-frankenphp.md)).
- `--link` – die Dateien landen in einem **unabhängigen Layer** (Doku: „your files remain independent on their own
  layer“). Vorteil: Ändert sich ein Layer darunter, muss dieser `COPY`-Layer nicht neu erstellt werden.
- `--chmod=755` – Rechte direkt beim Kopieren setzen (ausführbar), statt in einem zusätzlichen `RUN chmod`-Layer.

```dockerfile
ENTRYPOINT ["docker-entrypoint"]
```
Programm, das beim Containerstart **immer** ausgeführt wird. `CMD` (s. u.) wird ihm als Argumente übergeben. Unser
Entrypoint wartet auf die Datenbank und startet dann mit `exec "$@"` den eigentlichen Befehl
(Details in [03](03-compose-und-frankenphp.md)).
Die **JSON-Schreibweise** (`["…"]`, „exec form“) startet das Programm direkt, ohne Shell dazwischen. Dadurch ist es
PID 1 und bekommt Signale wie `SIGTERM` von `docker stop` – nötig für sauberes Herunterfahren.

```dockerfile
HEALTHCHECK --start-period=60s CMD ["php", "-r", "exit(false === @file_get_contents('http://localhost:2019/metrics', context: stream_context_create(['http' => ['timeout' => 5]])) ? 1 : 0);"]
```
Docker führt diesen Befehl regelmäßig im Container aus. Exit-Code 0 = `healthy`, 1 = `unhealthy` (Doku).
- Geprüft wird `http://localhost:2019/metrics` – das ist die **Admin-API von Caddy**, die standardmäßig auf
  `localhost:2019` lauscht und `/metrics` bereitstellt (Caddy-Doku *Metrics*). Antwortet sie, läuft der Server.
- `--start-period=60s` – in den ersten 60 s zählen Fehlschläge nicht (der Entrypoint wartet evtl. auf die Datenbank).
- Weitere Standardwerte laut Doku: alle 30 s, Timeout 30 s, nach 3 Fehlschlägen `unhealthy`.
- Wozu: `docker compose up --wait` (CI und `deploy.sh`) wartet, bis alle Container `healthy` sind. Ohne Healthcheck
  wüsste das Deployment nicht, ob die App wirklich läuft.
- Einschränkung: Der Check prüft Caddy, **nicht** ob Symfony fehlerfrei antwortet. Ein Fehler in einer Route fällt
  nicht auf. Ein Smoke-Test gegen eine echte Seite wäre aussagekräftiger – Kandidat für später, wenn es eine
  Startseite gibt.

```dockerfile
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]
```
Standardbefehl: FrankenPHP mit unserem Caddyfile starten. Lässt sich beim Start überschreiben
(`docker compose run php bin/console …` ersetzt CMD).

### Stage `frankenphp_dev` (nur lokal)

```dockerfile
FROM frankenphp_base AS frankenphp_dev
SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]
```
Baut auf der Basis auf. `SHELL` wird laut Kommentar nur für hadolint wiederholt (der Linter erkennt die Vererbung
noch nicht); für Docker wäre es unnötig.

```dockerfile
ENV APP_ENV=dev
ENV XDEBUG_MODE=off
ENV FRANKENPHP_WORKER_CONFIG=watch
```
- `APP_ENV=dev` – Symfony-Umgebung (siehe [01](01-grundkonzepte.md#8-symfony-umgebungen-flex-recipes)).
- `XDEBUG_MODE=off` – Xdebug ist installiert, aber aus; `compose.override.yaml` setzt es auf `develop` (bessere
  Fehlermeldungen). Für Debugging mit Breakpoints: `XDEBUG_MODE=debug docker compose up --wait`.
- `FRANKENPHP_WORKER_CONFIG=watch` – wird ins Caddyfile eingesetzt; Worker starten bei Dateiänderungen neu.

```dockerfile
RUN <<-EOF
	mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
	install-php-extensions xdebug
	useradd -m -s /bin/bash nonroot
	git config --system --add safe.directory /app
EOF
```
- PHP liefert zwei Vorlagen mit; die Dev-Variante zeigt Fehler an, die Prod-Variante versteckt sie.
- `xdebug` – Debugger (Breakpoints in PhpStorm), nur in Dev. Kostet Performance, deshalb nie in Prod.
- `useradd … nonroot` – ein normaler Benutzer, gedacht für Devcontainer-Setups; wir nutzen ihn nicht (der Dev-Container
  läuft als root, damit Dateien im Bind-Mount ohne Rechteprobleme geschrieben werden können).
- `safe.directory` – Git verweigert seit einer Sicherheitslücke von 2022 die Arbeit in Repos, die einem anderen
  Benutzer gehören (Schutz vor manipulierten `.git/config`-Dateien in fremden Ordnern). Im Bind-Mount gehören die
  Dateien deinem Mac-Benutzer, im Container läuft root → ohne diese Zeile würden Composer-Befehle mit git scheitern.

```dockerfile
ARG TARGETARCH
RUN <<-EOF
	case "$TARGETARCH" in … esac
	lsp_version=$(basename "$(curl -fsSLI -o /dev/null -w '%{url_effective}' https://github.com/symfony/language-tools/releases/latest)")
	curl -fsSL "https://github.com/…/symfony-lsp-$lsp_version-linux-$lsp_arch.tar.gz" | tar -xz -C /usr/local/bin --strip-components=1 --wildcards '*/symfony-lsp'
EOF
```
Installiert den **Symfony Language Server** (für Editoren/KI-Agenten, die Symfony-Autovervollständigung über das
Language Server Protocol beziehen).
- `ARG TARGETARCH` – BuildKit setzt automatisch die Zielarchitektur (`amd64`, `arm64`). Laut Doku muss die Variable
  **in der Stage neu deklariert** werden, sonst ist sie leer. Wird hier auf die Dateinamen-Konvention des Projekts
  (`x64`, `arm64`) abgebildet.
- Die erste `curl`-Zeile ermittelt die neueste Version über die Weiterleitung von `/releases/latest`.
- **Kritisch betrachtet:** Hier wird bei jedem Build ungeprüft die *neueste* Version aus dem Internet geladen – ohne
  feste Version und ohne Prüfsumme. Das ist ein (kleines) Lieferketten-Risiko und macht Builds nicht reproduzierbar.
  Es betrifft nur das Dev-Image, und wir nutzen den Language Server nicht (PhpStorm hat eigene Symfony-Unterstützung).
  → Kandidat zum Entfernen (spart auch Build-Zeit in der CI).

```dockerfile
COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]
```
Dev-spezifische PHP-Einstellungen (Xdebug-Host) und Start mit `--watch` (Caddy lädt die Konfiguration bei Änderung neu).

### Stage `frankenphp_prod_builder` (baut die App für Produktion)

```dockerfile
FROM frankenphp_base AS frankenphp_prod_builder
SHELL [...]
ENV APP_ENV=prod
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/
```
Wie bei Dev, aber Produktionswerte: Fehler nicht anzeigen, OPcache-Preloading, keine Zeitstempelprüfung
(Inhalt der ini in [03](03-compose-und-frankenphp.md)).

```dockerfile
# prevent the reinstallation of vendors at every changes in the source code
COPY --link composer.* symfony.* ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress
```
Das **Cache-Muster** von oben: Erst nur `composer.json`, `composer.lock`, `symfony.lock` kopieren, dann installieren.
Solange sich diese Dateien nicht ändern, kommt der Layer aus dem Cache – auch wenn sich der Code ändert.
- `--no-cache` – Composers Download-Cache nicht benutzen (würde nur das Image vergrößern)
- `--prefer-dist` – fertige Archive statt git-Klone laden (schneller, kleiner; Composer-Standard für stabile Versionen)
- `--no-dev` – Pakete aus `require-dev` (PHPUnit, PHPStan) **nicht** installieren → kleiner, weniger Angriffsfläche
- `--no-autoloader` – Autoloader noch nicht erzeugen; geht erst, wenn der Code da ist (s. u.)
- `--no-scripts` – Composer-Skripte (`cache:clear` …) noch nicht ausführen; der Code fehlt ja noch
- `--no-progress` – keine Fortschrittsbalken im Log

Installiert wird exakt, was in `composer.lock` steht – deshalb ist die Lock-Datei committet
(siehe [04-composer.md](04-composer.md)).

```dockerfile
COPY --link --exclude=frankenphp/ . ./
```
Jetzt den ganzen Build-Kontext kopieren, ohne `frankenphp/` (gehört nicht in `/app`). Was `.dockerignore` ausschließt
(z. B. `.git/`, `tests/`, `vendor/`, `var/`, `.env.local`, `docs/`), ist gar nicht im Kontext.
Unsere Ergänzung in `.dockerignore`: `!compose.yaml` und `!compose.prod.yaml` – sie **sollen** ins Image, damit
`deploy.sh` sie herausholen kann (siehe [05-ci-cd.md](05-ci-cd.md)).

```dockerfile
RUN <<-EOF
	mkdir -p var/cache var/log var/share
	composer dump-autoload --classmap-authoritative --no-dev
	composer dump-env prod
	composer run-script --no-dev post-install-cmd
	if [ -f importmap.php ]; then
		php bin/console asset-map:compile
	fi
	chmod +x bin/console
	chmod -R g=u var
	sync
EOF
```
- `mkdir -p var/…` – Ordner für Cache, Logs und geteilte Daten anlegen.
- `dump-autoload --classmap-authoritative` – Composer erzeugt eine vollständige Tabelle „Klasse → Datei“. Doku:
  „Autoload classes from the classmap only“. Schneller (kein Suchen im Dateisystem), aber: Klassen, die zur Laufzeit
  neu entstehen, werden nicht gefunden – in Prod gibt es die nicht.
- `dump-env prod` – liest **alle** `.env`-Dateien und schreibt das Ergebnis nach `.env.local.php` (Symfony-Doku
  *Configuration*). Symfony muss dann zur Laufzeit keine `.env`-Dateien mehr parsen.
  **Sicherheitsrelevant:** Alles, was in den committeten `.env`-Dateien steht, landet so im Image. Deshalb dürfen dort
  nur Standardwerte stehen. Unsere echten Secrets kommen zur Laufzeit als Umgebungsvariablen (die laut Symfony-Doku
  immer Vorrang vor `.env`-Werten haben).
- `run-script post-install-cmd` – führt jetzt die übersprungenen Skripte aus: über Flex `cache:clear` (baut den
  Prod-Cache **ins Image**, damit der erste Request schnell ist) und `assets:install`.
- `asset-map:compile` – nur, falls AssetMapper genutzt wird (gibt es bei uns noch nicht); erzeugt versionierte
  CSS/JS-Dateien.
- `chmod -R g=u var` – Gruppe bekommt dieselben Rechte wie der Besitzer; zusammen mit der Gruppe `0` (s. u.) für
  Plattformen, die Container mit zufälliger Benutzer-ID starten (OpenShift). Für uns nicht nötig, schadet nicht.
- `sync` – schreibt Dateisystem-Puffer auf die Platte. Im Template ohne Begründung (nicht geprüft, wogegen es schützt).

```dockerfile
# Collect shared libraries needed by FrankenPHP and PHP extensions
RUN <<-'EOF'
	apt-get update
	apt-get install -y --no-install-recommends libtree
	mkdir -p /tmp/libs
	BINARIES=(frankenphp php file)
	for target in $(…which…) $(find "<extension_dir>" -maxdepth 2 -name "*.so"); do
		libtree -pv "$target" 2>/dev/null | grep -oP '(?:── )\K/\S+(?= \[)' | while IFS= read -r lib; do
			[ -f "$lib" ] && cp -n "$lib" /tmp/libs/
		done
	done
	rm -rf /var/lib/apt/lists/*
EOF
```
Vorbereitung für das schlanke Prod-Image. Programme unter Linux hängen von **Shared Libraries** ab (`.so`-Dateien,
vergleichbar mit DLLs): z. B. braucht `intl` die ICU-Bibliothek, `pdo_pgsql` die libpq.
- `libtree` zeigt für ein Programm alle benötigten Bibliotheken als Baum.
- Für `frankenphp`, `php`, `file` und **jede PHP-Extension** (`*.so` im Extension-Verzeichnis) werden alle
  Bibliotheken gesucht und nach `/tmp/libs` kopiert.
- `grep -oP '(?:── )\K/\S+(?= \[)'` – Perl-Regex, schneidet aus jeder Baumzeile nur den Dateipfad heraus.
- `<<-'EOF'` mit Anführungszeichen: Docker setzt im Heredoc **keine** Variablen ein, die Shell macht das selbst.

### Stage `frankenphp_prod` (das Image, das auf dem Server läuft)

```dockerfile
FROM debian:13-slim AS frankenphp_prod
SHELL [...]
ENV APP_ENV=prod
ENV PHP_INI_SCAN_DIR=":/usr/local/etc/php/app.conf.d"
```
**Neustart von einem minimalen Debian** – nichts aus den vorherigen Stages ist automatisch dabei. Gleiche
Debian-Version wie das FrankenPHP-Image (13), damit die kopierten Bibliotheken passen.

```dockerfile
COPY --from=frankenphp_prod_builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp
COPY --from=frankenphp_prod_builder /usr/local/bin/php /usr/local/bin/php
COPY --from=frankenphp_prod_builder /usr/local/bin/docker-php-entrypoint /usr/local/bin/docker-php-entrypoint
COPY --from=frankenphp_prod_builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=frankenphp_prod_builder /tmp/libs /usr/lib
COPY --from=frankenphp_prod_builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d
COPY --from=frankenphp_prod_builder /usr/local/etc/php/php.ini /usr/local/etc/php/php.ini
COPY --from=frankenphp_prod_builder /usr/local/etc/php/app.conf.d /usr/local/etc/php/app.conf.d
COPY --from=frankenphp_prod_builder /etc/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
```
Nur das Nötigste aus dem Builder übernehmen: die Programme, die Extensions, die gesammelten Bibliotheken, die
PHP-Konfiguration und das Caddyfile. **Composer ist nicht dabei** – im Prod-Image kann man keine Pakete nachinstallieren.

```dockerfile
# CA certificates for TLS, file/libmagic for Symfony MIME type detection
COPY --from=frankenphp_prod_builder /etc/ssl/certs/ca-certificates.crt /etc/ssl/certs/ca-certificates.crt
COPY --from=frankenphp_prod_builder /etc/ssl/openssl.cnf /etc/ssl/openssl.cnf
COPY --from=frankenphp_prod_builder /usr/bin/file /usr/bin/file
COPY --from=frankenphp_prod_builder /usr/lib/file/magic.mgc /usr/lib/file/magic.mgc
```
- **CA-Zertifikate**: Liste der vertrauenswürdigen Zertifizierungsstellen. Ohne sie könnte der Container keine
  HTTPS-Verbindungen nach außen prüfen – z. B. Caddy zu Let's Encrypt oder später Symfony Mailer zum SMTP-Server.
- `file` + `magic.mgc` – Dateityp-Erkennung anhand des Inhalts (Datenbank „magischer Bytes“), für Uploads wichtig:
  Man darf dem Dateinamen oder dem Browser-Typ eines Uploads nie trauen.

```dockerfile
ENV  OPENSSL_CONF=/etc/ssl/openssl.cnf XDG_CONFIG_HOME=/config XDG_DATA_HOME=/data SSL_CERT_FILE=/etc/ssl/certs/ca-certificates.crt
```
Teilt OpenSSL und Caddy mit, wo Konfiguration und Zertifikate liegen. Caddy speichert seine Daten (u. a. die
Let's-Encrypt-Zertifikate) nach der XDG-Konvention unter `$XDG_DATA_HOME/caddy` → `/data/caddy` – genau dort ist in
`compose.yaml` das Volume `caddy_data` eingehängt.

```dockerfile
RUN <<-EOF
	mkdir -p /data/caddy /config/caddy
	chown -R www-data:www-data /data /config
	# Remove setuid/setgid bits
	find / -perm /6000 -type f -exec chmod a-s {} + 2>/dev/null || true
EOF
```
- Ordner für Caddy anlegen und `www-data` übergeben (der Prozess läuft gleich als `www-data` und muss dort schreiben).
- **Setuid/Setgid entfernen:** Programme mit diesem Bit laufen mit den Rechten ihres **Besitzers** (meist root), egal
  wer sie startet – z. B. `su`, `passwd`. Ein Angreifer, der Code im Container ausführen kann, könnte darüber zu root
  werden. Das Bit bei allen Dateien zu entfernen, schließt diesen Weg. Klassische Härtungsmaßnahme.

```dockerfile
COPY --link --exclude=var --from=frankenphp_prod_builder /app /app
COPY --chown=www-data:0 --from=frankenphp_prod_builder /app/var /app/var
RUN chmod g=u /app/var
```
Die App (Code, `vendor/`, `.env.local.php`) aus dem Builder übernehmen. Der Code gehört **root** und ist für
`www-data` nur lesbar – die App kann ihren eigenen Code nicht verändern (gut, falls jemand eine Lücke findet).
Nur `var/` (Cache, Logs) gehört `www-data`, weil Symfony dort schreiben muss.

```dockerfile
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
```
Der Entrypoint, wie in der Basis.

```dockerfile
# www-data, numeric so runtimes can verify runAsNonRoot
USER 33
```
**Ab hier läuft alles als `www-data`** (Benutzer-ID 33 in Debian). Die Zahl statt des Namens, weil manche Plattformen
(Kubernetes `runAsNonRoot`) nur anhand der Zahl prüfen können, dass es nicht root (0) ist.
Wie darf ein Nicht-root-Prozess die Ports 80/443 öffnen? Normalerweise sind Ports < 1024 root vorbehalten; Docker
setzt in Containern die Kernel-Einstellung `net.ipv4.ip_unprivileged_port_start` so, dass das geht (nicht geprüft;
dass es funktioniert, zeigt unser laufender Server).

```dockerfile
WORKDIR /app
ENTRYPOINT ["docker-entrypoint"]
HEALTHCHECK ...
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]
```
Wie in der Basis – müssen wiederholt werden, weil diese Stage nicht von der Basis erbt.

---

## Alternativen

| Alternative | Vorteil | Nachteil / warum nicht |
|---|---|---|
| Ein einziges Stage für alles | einfacher | Prod-Image enthält Composer, Xdebug, Build-Werkzeuge; größer, mehr Angriffsfläche |
| Offizielle `php:8.5-fpm`-Images + Nginx | sehr verbreitet | zwei Container, eigenes TLS (certbot), mehr Konfiguration |
| Alpine statt Debian | kleiner | musl statt glibc; bei PHP gelegentlich Kompatibilitätsunterschiede (nicht geprüft für unseren Fall); Template ist auf Debian ausgelegt |
| „Distroless“-Images (ohne Shell) | noch kleinere Angriffsfläche | Debugging im Container unmöglich (`docker exec … sh` geht nicht), Entrypoint-Skript bräuchte Shell |
| Statische FrankenPHP-Binary (eine Datei mit allem) | extrem einfaches Deployment | Extensions zur Build-Zeit festgelegt, weniger flexibel; nicht Template-Standard |
| Basis-Image per Digest pinnen | reproduzierbare Builds | Sicherheitsupdates nur durch aktives Aktualisieren (Dependabot könnte das übernehmen) |

## Sicherheit und Best Practices – Checkliste

| Praxis | Status bei uns |
|---|---|
| Prozess läuft nicht als root | ✅ Prod: `USER 33` (www-data). Dev: root (bequemer mit Bind-Mount, nur lokal) |
| Code für den Prozess nicht beschreibbar | ✅ `/app` gehört root, nur `var/` gehört www-data |
| Keine Build-Werkzeuge im Prod-Image | ✅ Multi-Stage; kein Composer, kein git, kein Xdebug |
| Keine Secrets im Image | ✅ Secrets nur zur Laufzeit; `.env.local` per `.dockerignore` ausgeschlossen. ⚠️ Nie Secrets in committete `.env`-Dateien – `dump-env` würde sie ins Image backen |
| Setuid-Bits entfernt | ✅ |
| Keine Dev-Pakete in Prod | ✅ `--no-dev` |
| Basis-Images aktuell | ✅ `pull: true` in der CI holt bei jedem Build die neueste 1.x/8.5.x; Dependabot meldet Major-Sprünge |
| Kleine Images | ✅ 301 MB |
| Reproduzierbare Downloads | ⚠️ Symfony Language Server im Dev-Image ohne Version/Prüfsumme – Kandidat zum Entfernen |
| Image auf bekannte Schwachstellen scannen | ❌ noch nicht. Möglich mit `docker scout cves` oder Trivy in der CI – Kandidat für später |
| `.dockerignore` gepflegt | ✅ schließt `.git`, `vendor`, `var`, `.env.local`, Tests, Doku aus |
