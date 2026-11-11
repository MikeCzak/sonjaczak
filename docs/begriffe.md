# Begriffe

Kurze Erklärungen zu den Bausteinen dieses Projekts. Wird laufend ergänzt; Details schauen wir uns an, wenn die
App einmal deployt ist. Jeweils mit Hinweis, wo der Begriff im Projekt vorkommt.

## Docker

**Image** – Unveränderliche Vorlage (Dateisystem + Startbefehl), aus der Container gestartet werden. Wird aus
dem `Dockerfile` gebaut. Bei uns: `app-php-dev` (lokal), später `app-php-prod` (Server, über ghcr.io).

**Container** – Laufende Instanz eines Images. Änderungen im Container gehen verloren, wenn er neu erstellt wird –
außer sie liegen in einem Volume.

**Volume** – Von Docker verwalteter Speicherplatz, der Container-Neustarts und -Neuerstellung überlebt.
Bei uns: `database_data` (PostgreSQL-Daten), `caddy_data` (Zertifikate), `caddy_config`.
`docker compose down -v` löscht sie – lokal also auch die Datenbank.

**Bind-Mount** – Ein Ordner des Host-Rechners wird direkt in den Container eingeblendet. Bei uns in Dev:
Projektordner → `/app` (siehe `compose.override.yaml`). Änderungen in der IDE sind sofort im Container sichtbar.

**Anonymes Volume** – Volume ohne Namen, z. B. `/app/var` in `compose.override.yaml`. Überdeckt den Teil des
Bind-Mounts, damit Cache und Logs im Container bleiben (schneller auf dem Mac).

**Dockerfile** – Bauanleitung für Images.

**Multi-Stage-Build** – Ein Dockerfile mit mehreren `FROM … AS name`-Abschnitten (Stages). Eine Stage kann
Dateien aus einer anderen übernehmen (`COPY --from=…`). Bei uns: `frankenphp_base` → `frankenphp_dev` bzw.
`frankenphp_prod_builder` → `frankenphp_prod`. Das Prod-Image enthält nur das Nötigste (kein Composer, kein git).

**Layer-Cache** – Jede Anweisung im Dockerfile erzeugt eine Schicht. Unveränderte Schichten werden beim nächsten
Build wiederverwendet. Deshalb kopiert der Prod-Build zuerst nur `composer.*` und installiert die Abhängigkeiten,
und erst danach den Quellcode.

**Entrypoint** – Skript, das bei jedem Containerstart vor dem eigentlichen Befehl läuft. Bei uns
`frankenphp/docker-entrypoint.sh`: `composer install` falls nötig, auf die Datenbank warten, Migrationen.

**Healthcheck** – Befehl, mit dem Docker prüft, ob ein Container funktioniert („healthy“). `docker compose up --wait`
wartet darauf. php: Caddy-Metrik-Endpunkt; database: `pg_isready`.

**Docker Compose** – Beschreibt mehrere Container (Services), ihre Netzwerke, Volumes und Ports in YAML.
Services erreichen sich untereinander über ihren Namen (z. B. Host `database` in `DATABASE_URL`).

**Override-Datei** – `compose.override.yaml` wird automatisch zusätzlich zu `compose.yaml` geladen (lokal: Dev-Image,
Bind-Mount, Xdebug, DB-Port). `compose.prod.yaml` wird nur mit `-f compose.yaml -f compose.prod.yaml` geladen.

**Veröffentlichter Port** – `ports:` in Compose macht einen Container-Port auf dem Host erreichbar. Docker trägt
dafür eigene Firewall-Regeln ein und umgeht damit ufw – deshalb bekommt die Datenbank in Produktion keinen.

**ghcr.io** – GitHub Container Registry: Ablage für die in der CI gebauten Images; der Server holt sie dort ab.

## Webserver und PHP

**FrankenPHP** – Webserver auf Basis von Caddy mit eingebautem PHP. Ersetzt die Kombination Nginx + PHP-FPM
durch einen Prozess in einem Container.

**Caddy** – Der Webserver in FrankenPHP. Holt HTTPS-Zertifikate automatisch (lokal von einer eigenen Test-CA,
in Produktion von Let's Encrypt). Konfiguration: `frankenphp/Caddyfile`.

**SERVER_NAME** – Für welche Hostnamen Caddy zuständig ist und Zertifikate ausstellt. Lokal `localhost`,
in Produktion `sonjaczak.com`.

**Let's Encrypt** – Kostenlose Zertifizierungsstelle. Hat Rate-Limits, deshalb muss das Volume `caddy_data` auf dem
Server erhalten bleiben.

**Worker-Modus** – FrankenPHP bootet Symfony einmal und verarbeitet danach viele Requests im selben Prozess
(statt bei jedem Request neu zu starten wie bei PHP-FPM). Schnell, aber: Zustand in Services bleibt zwischen
Requests erhalten, wenn er nicht zurückgesetzt wird (`ResetInterface`).

**OPcache** – PHP-Cache für kompilierten Code im Arbeitsspeicher. Einstellungen in `frankenphp/conf.d/*.ini`.

**Preloading** – OPcache lädt beim Start festgelegte Klassen (`config/preload.php`) vorab. Nur in Prod.

**`opcache.validate_timestamps = 0`** – PHP prüft nicht mehr, ob sich Dateien geändert haben. Geht nur, weil der
Code im Prod-Image unveränderlich ist; Code-Änderung = neues Image.

**Xdebug** – Debugger/Profiler für PHP, nur im Dev-Image. Schrittweises Debugging muss eingeschaltet werden
(`XDEBUG_MODE=develop,debug`).

**Mercure** – Protokoll für Echtzeit-Push vom Server an den Browser, als Caddy-Modul eingebaut. Nutzen wir derzeit nicht.

**Vulcain** – Protokoll zum Vorladen verknüpfter API-Ressourcen, ebenfalls Caddy-Modul. Nutzen wir derzeit nicht.

## Composer und Symfony

**Composer** – PHP-Paketmanager. `composer.json` = gewünschte Pakete und Versionsbereiche,
`composer.lock` = exakt installierte Versionen (wird committet, damit überall dasselbe installiert wird).

**`content-hash`** – Prüfsumme in `composer.lock` über relevante Teile von `composer.json`. Ändert sich
`composer.json`, passt sie nicht mehr; `composer update --lock` berechnet sie neu, ohne Pakete zu ändern.

**Symfony Flex** – Composer-Plugin. Führt beim Installieren eines Pakets dessen Rezept aus.

**Rezept (Recipe)** – Anleitung für Flex pro Paket (Repository `symfony/recipes`): Bundle in `config/bundles.php`
registrieren, Konfiguration nach `config/packages/` kopieren, Variablen in `.env` eintragen und – weil
`"docker": true` gesetzt ist – Services in `compose*.yaml` und Zeilen ins `Dockerfile` schreiben
(zwischen den Markern `###> paket ###` … `###< paket ###`).

**`symfony.lock`** – Hält fest, welche Rezepte in welcher Version gelaufen sind.

**Pack** – Sammelpaket ohne eigenen Code, das mehrere Pakete installiert (z. B. `symfony/orm-pack`).

**Skeleton** – Minimales Symfony-Startprojekt (`symfony/skeleton`), ohne Twig, Doctrine usw.

**`.env`-Dateien** – Standardwerte für Umgebungsvariablen. Reihenfolge (spätere gewinnen): `.env` → `.env.local` →
`.env.$APP_ENV` → `.env.$APP_ENV.local`. Echte Umgebungsvariablen (z. B. aus Compose) gewinnen immer.
`.env` und `.env.dev` sind committet (nur Standardwerte), `*.local` nie.

**`APP_SECRET`** – Geheimer Schlüssel von Symfony (z. B. für CSRF-Tokens). Dev-Wert in `.env.dev`, Prod-Wert nur
als Umgebungsvariable auf dem Server.

**`composer dump-env prod`** – Kompiliert die `.env`-Dateien in `.env.local.php`, damit Prod sie nicht bei jedem
Request parsen muss.

**Bootstrapping** – Das erstmalige Anlegen des Symfony-Projekts. Bei uns hat das der Entrypoint beim ersten
Containerstart erledigt (`composer create-project symfony/skeleton`), weil `composer.json` nur `{}` enthielt.
Die Ordner `config/`, `bin/`, `public/` usw. haben dabei die Rezepte der Grundpakete angelegt.

**Composer-Plugin** – Paket, das Code bei jedem Composer-Lauf ausführt (z. B. Flex). Muss in `composer.json` unter
`allow-plugins` ausdrücklich erlaubt werden.

**`recipes-contrib`** – Community-Rezepte. Bei uns aus (`"allow-contrib": false`), daher z. B. die PHPStan-Konfiguration
von Hand.

## Tests und CI

**PHPUnit** – Test-Framework. Konfiguration `phpunit.dist.xml` (bricht auch bei Deprecations/Warnungen ab), Tests in
`tests/`. `KernelTestCase` bootet Symfony und gibt Zugriff auf Services; `WebTestCase` simuliert HTTP-Requests.

**Test-Umgebung** – `APP_ENV=test`, Werte aus `.env.test`. Eigene Datenbank `app_test` (Suffix aus
`config/packages/doctrine.yaml`), damit Tests nie die Dev-Daten verändern.

**PHPStan** – Statische Analyse: findet Typfehler, ohne den Code auszuführen. Level 0–10, `max` = strengstes.
Konfiguration `phpstan.dist.neon`. Die Symfony-Erweiterung liest den kompilierten Container
(`var/cache/dev/App_KernelDevDebugContainer.xml`), um zu wissen, welche Klasse hinter einem Service steckt.

**GitHub Actions** – CI von GitHub. Workflows in `.github/workflows/*.yaml`; ein Workflow hat Jobs, ein Job hat
Steps. Läuft bei jedem Push auf `main` und bei Pull Requests.

**Docker Bake** – Build-Werkzeug von Docker (`docker buildx bake`), das mehrere Images aus einer Beschreibung baut.
Kann Compose-Dateien lesen: Jeder Service mit `build:` wird ein Build-Ziel, das Feld `image:` wird zum Tag.

**Smoke-Test** – Minimaltest „startet es überhaupt?“. Bei uns im Build-Job: Prod-Image starten, Symfony-Infos
ausgeben, eine DB-Abfrage – erst dann wird das Image hochgeladen.

**`GITHUB_TOKEN`** – Token, den GitHub für jeden Workflow-Lauf automatisch erzeugt; Rechte über `permissions:`
im Workflow. Damit lädt die CI das Image nach ghcr.io, ohne dass wir ein eigenes Secret anlegen müssen.

**Personal Access Token (classic)** – Token für einen GitHub-Account mit frei wählbaren Rechten (Scopes). Der Server
braucht einen mit nur `read:packages`, um das private Image herunterzuladen.

**GitHub-Environment** – Benannte Zielumgebung (bei uns `production`) mit eigenen Secrets. Nur Jobs mit
`environment: production` sehen diese Secrets.

**Forced Command** – Option in `~/.ssh/authorized_keys` (`command="…"`): Egal was der Client verlangt, sshd führt nur
diesen Befehl aus; der verlangte Befehl steht in `SSH_ORIGINAL_COMMAND`. `restrict` schaltet zusätzlich
Port-Forwarding, Agent-Forwarding und Terminal ab. Siehe `man sshd`, Abschnitt AUTHORIZED_KEYS FILE FORMAT.

**`known_hosts` / Host-Key** – Jeder SSH-Server hat einen eigenen Schlüssel. Ist er in `known_hosts` hinterlegt,
erkennt der Client, wenn sich ein anderer Rechner als der Server ausgibt. In der CI als Secret `SSH_KNOWN_HOSTS`.

**Compose-`.env`** – Datei `.env` neben den Compose-Dateien (oder `--env-file`). Compose setzt ihre Werte in
`${…}`-Platzhalter ein. Hat nichts mit Symfonys `.env` zu tun. Auf dem Server: `/srv/sonjaczak/.env`.

**`${VAR:?Meldung}`** – Compose-Platzhalter ohne Standardwert: Fehlt `VAR` oder ist leer, bricht Compose mit der
Meldung ab.

**Rollback** – Zurück auf eine frühere Version: `deploy.sh` mit dem Hash eines älteren Commits.

**Dependabot** – GitHub-Dienst, der Pull Requests für Updates von Abhängigkeiten anlegt (`.github/dependabot.yaml`).

## Datenbank

**Doctrine DBAL** – Datenbank-Abstraktionsschicht (Verbindung, SQL ausführen).

**Doctrine ORM** – Bildet PHP-Klassen (Entities, `src/Entity/`) auf Tabellen ab.

**Migration** – Versionierte PHP-Datei mit SQL, die das Schema ändert (`src/Migrations/`). Ausgeführte Migrationen
stehen in der Tabelle `doctrine_migration_versions`. Schema-Änderungen nur so, nie mit `doctrine:schema:update --force`.

**`serverVersion`** – Teil von `DATABASE_URL`. Sagt Doctrine, welche PostgreSQL-Version läuft, damit es passendes
SQL erzeugt. Muss zur tatsächlichen Version (18) passen.

**PGDATA** – Datenverzeichnis von PostgreSQL. Im offiziellen Image ab Version 18 `/var/lib/postgresql/18/docker`,
deshalb hängt das Volume an `/var/lib/postgresql`.

**`shared_buffers`** – Arbeitsspeicher, den PostgreSQL als Cache für Tabellendaten nutzt. Standard 128MB, wird
erst bei Bedarf belegt. Für den Server begrenzen wir ihn.

## Frontend

**Twig** – Template-Sprache von Symfony. Templates liegen in `templates/`, `base.html.twig` ist das Grundgerüst,
von dem alle Seiten erben.

**AssetMapper** – Symfony-Komponente, die CSS, JavaScript und Bilder aus `assets/` ausliefert, ohne Node und
ohne Bundler (Webpack). In Produktion kopiert `asset-map:compile` alles nach `public/assets/` und hängt einen Hash
an den Dateinamen (`app-fSic5N9.css`), damit Browser nach einem Deploy nie eine alte Version aus dem Cache nehmen.

**Importmap** – Browser-Standard, der Namen wie `bootstrap` einer JavaScript-Datei zuordnet, damit `import
'bootstrap'` ohne Bundler funktioniert. Bei uns: `importmap.php`. Externe Pakete lädt `importmap:install` nach
`assets/vendor/` (nicht im Repo). Dependabot aktualisiert sie nicht; Prüfen mit `importmap:outdated`.

**Sass / Dart Sass** – Sprache, die zu CSS kompiliert wird (Variablen, Verschachtelung, Importe). Bootstrap ist
in Sass geschrieben; über Sass-Variablen passen wir Farben und Schriften an. Dart Sass ist der Compiler; das
`symfonycasts/sass-bundle` lädt ihn als Binärdatei nach `var/dart-sass/`. Ergebnis: `var/sass/`.

**Übersetzungsdomain** – Eine Gruppe von Übersetzungen in einer eigenen Datei, z. B. `messages.de.yaml`,
`content.de.yaml`. Schema: `docs/planung/seitenstruktur.md`, Abschnitt 5.

**KnpMenuBundle / MenuBuilder** – Bundle zum Aufbau von Menüs als Baum. `App\Menu\MenuBuilder` legt die Einträge an
(Beschriftung = Übersetzungsschlüssel, Ziel = Route); `knp_menu_render('main', …)` gibt sie über das Template
`templates/menu/main.html.twig` aus. Welcher Eintrag aktiv ist, erkennt das Bundle an der aktuellen Route
(`RouteVoter`).

**Twig-Global** – Variable, die in jedem Template verfügbar ist, ohne dass der Controller sie übergibt. Bei uns
in `config/packages/twig.yaml`: `contact_email`, `youtube_url`.
