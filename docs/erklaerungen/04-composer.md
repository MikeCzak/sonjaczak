# 04 – Composer, Flex und die Herkunft der Projektdateien

Dateien: `composer.json`, `composer.lock`, `symfony.lock`.
Referenzen: Composer-Doku (getcomposer.org/doc: *Schema*, *Config*, *CLI*, *Scripts*), Quellcode von Symfony Flex
in `vendor/symfony/flex/` (Version 2.12.0 laut `composer.lock`).

## Konzepte

### Paketverwaltung

Composer installiert PHP-Bibliotheken („Pakete“) und deren Abhängigkeiten. Die Pakete kommen von **Packagist**
(packagist.org), dem zentralen Verzeichnis; der Code selbst liegt meist auf GitHub. Composer löst ein Puzzle: Jedes
Paket sagt, welche Versionen anderer Pakete es verträgt, und Composer sucht eine Kombination, die alle Bedingungen
erfüllt.

### `composer.json` vs. `composer.lock`

| | `composer.json` | `composer.lock` |
|---|---|---|
| Inhalt | **Wünsche**: „Doctrine ORM ab 3.7, unter 4.0“ | **Fakten**: „Doctrine ORM 3.7.x, exakt dieser Commit, diese Prüfsumme“ – für alle 100+ Pakete, auch indirekte |
| Geschrieben von | dir (bzw. `composer require`) | Composer |
| Gelesen von | `composer update` | `composer install` |

- `composer install` installiert **exakt** den Stand aus der Lock-Datei. Das tun der Docker-Build und die CI → überall
  identische Versionen.
- `composer update` sucht im Rahmen von `composer.json` die neuesten Versionen und **schreibt die Lock-Datei neu**.
  Das macht man bewusst (oder Dependabot per PR), testet und committet.
- **Best Practice für Anwendungen:** `composer.lock` immer committen. (Bei Bibliotheken, die andere einbinden, ist das
  anders – aber wir sind eine Anwendung.)

### Versionsbedingungen (Semantic Versioning)

Versionen folgen meist **SemVer**: `MAJOR.MINOR.PATCH`. Major = inkompatible Änderungen, Minor = neue Funktionen,
kompatibel, Patch = Fehlerbehebungen.

| Schreibweise | Bedeutung | Beispiel bei uns |
|---|---|---|
| `^3.7` | ≥ 3.7.0 und < 4.0.0 (alles Kompatible) | `doctrine/orm` |
| `8.1.*` | jede 8.1.x | Symfony-Pakete – Minor-Upgrade auf 8.2 nur bewusst |
| `>=8.5.12` | mindestens | `php` |
| `*` | beliebig | `ext-ctype` |
| `~3.7.1` | ≥ 3.7.1 und < 3.8.0 | (nicht genutzt) |

Symfony-Minor-Versionen sind laut Symfony-Versprechen rückwärtskompatibel, bringen aber neue Deprecations und
geänderte Recipes mit. Deshalb pinnen wir auf `8.1.*` und machen den Sprung auf 8.2 als eigenen, getesteten Schritt
(Termin im Projektstand).

### Autoloading

PHP muss wissen, in welcher Datei eine Klasse steht. Composer erzeugt `vendor/autoload.php`, das nach einer Regel
sucht: **PSR-4** – Namespace-Präfix ↔ Ordner. `App\Controller\HomeController` → `src/Controller/HomeController.php`.
Deshalb muss der Namespace exakt zur Ordnerstruktur passen, sonst „Class not found“.

### Composer-Plugins und Skripte – Code, der bei der Installation läuft

Composer kann zwei Arten von fremdem Code ausführen, **nicht nur** den Code, den die App später nutzt:
- **Plugins** (z. B. Flex) haken sich in Composer selbst ein.
- **Skripte** laufen bei Ereignissen wie `post-install-cmd`.

Das ist ein **Lieferketten-Risiko**: Ein kompromittiertes Paket mit Plugin könnte beim `composer install` auf deinem
Mac oder in der CI Code ausführen. Deshalb verlangt Composer (seit Version 2.2, nicht geprüft) eine ausdrückliche Erlaubnis in `allow-plugins`
(Standard laut Doku: `{}`, also keine Plugins). Das ist der Grund, warum wir `phpstan/extension-installer` nicht
freigegeben, sondern die PHPStan-Erweiterungen manuell eingebunden haben: ein Plugin weniger, das Code ausführen darf.

### Symfony Flex und Recipes

**Flex** ist ein Composer-Plugin von Symfony. Es tut drei Dinge:

1. **Recipes ausführen:** Bei `composer require` eines Pakets sucht Flex ein Rezept im Repository
   `github.com/symfony/recipes` (offiziell, geprüft) bzw. `symfony/recipes-contrib` (Community). Das Rezept legt
   Konfigurationsdateien an, trägt Bundles in `config/bundles.php` ein, ergänzt `.env`, Dockerfile und
   `compose.yaml` (die `###> paket ###`-Blöcke). Bei `composer remove` macht es das wieder rückgängig.
2. **Symfony-Versionen festhalten:** `extra.symfony.require` begrenzt **alle** `symfony/*`-Pakete auf eine Version
   (im Flex-Code: `Flex.php`, `PackageResolver.php`).
3. **Aliase:** `composer require orm` statt `symfony/orm-pack`.

**`symfony.lock`** merkt sich, welche Recipe-Version für welches Paket angewendet wurde. Damit kann
`composer recipes` zeigen, ob es neuere Rezepte gibt, und `composer recipes:update` sie einspielen (als Diff, den man
prüft). Gehört committet.

**Packs** (wie `symfony/orm-pack`, `symfony/test-pack`) sind leere Pakete, die nur mehrere andere Pakete als
Abhängigkeiten mitbringen. Flex ersetzt sie bei der Installation durch die einzelnen Pakete – deshalb steht in unserer
`composer.json` nicht `orm-pack`, sondern `doctrine/orm`, `doctrine/doctrine-bundle` usw.

### Woher kommen die Dateien im Projekt?

| Herkunft | Dateien |
|---|---|
| **Template** dunglas/symfony-docker (Commit `7fe681b`) | `Dockerfile`, `compose*.yaml`, `frankenphp/`, `.dockerignore`, `.github/workflows/ci.yaml` (später umgebaut), `.github/dependabot.yaml` |
| **`composer create-project symfony/skeleton`** – ausgeführt vom Entrypoint des Templates beim ersten Start (Commit `e6596c0`): Der Entrypoint fand eine leere `composer.json` (`{}`), installierte das Skeleton mit `SYMFONY_VERSION=8.1.*`, setzte `extra.symfony.docker` und **löschte sich danach selbst** aus Entrypoint und `compose.yaml` | `bin/console`, `public/index.php`, `src/Kernel.php`, `config/` (Grundgerüst), `.env`, `.env.dev`, `composer.json`/`.lock`, `symfony.lock` |
| **Recipes** von Flex bei `composer require` | Doctrine: `config/packages/doctrine*.yaml`, `src/Migrations/`, DB-Service in `compose.yaml`, `pdo_pgsql` im Dockerfile, `DATABASE_URL` in `.env`. Test-Pack: `phpunit.dist.xml`, `tests/bootstrap.php`, `.env.test` |
| **Von uns geschrieben** | `compose.prod.yaml` (Prod-Teil), `deploy/deploy.sh`, `phpstan.dist.neon`, `tests/DatabaseConnectionTest.php`, Build/Deploy-Jobs der CI, `docs/` |

Das „Skeleton“ ist das **minimalste** Symfony (Kernel, Konsole, Routing, Konfiguration). Alles Weitere – Twig,
Formulare, Security, Doctrine – kommt einzeln per `composer require` hinzu. Die Alternative `symfony/webapp-pack`
bringt alles auf einmal (inkl. Mailer, Messenger, Profiler, AssetMapper …) – mehr als wir anfangs brauchen.

---

## `composer.json` Zeile für Zeile

```json
"name": "sonjaczak/website",
```
Paketname im Format `hersteller/paket`. Für eine Anwendung, die nie veröffentlicht wird, nur ein Bezeichner.

```json
"type": "project",
```
Composer-Doku: „This denotes a project rather than a library.“ Beeinflusst z. B., wie Tools das Paket behandeln.

```json
"license": "proprietary",
```
Composer-Doku: für Closed-Source-Software. Das Skeleton bringt `MIT` mit; MIT hätte erlaubt, dass jeder den Code
weiterverwendet. Deshalb haben wir auch die `LICENSE`-Datei entfernt.

```json
"description": "Website of Sonja Czak, singer and vocal coach",
```
Beschreibung, rein informativ.

```json
"minimum-stability": "stable",
"prefer-stable": true,
```
- `minimum-stability` – welche Stabilitätsstufe Pakete mindestens haben müssen (`dev` < `alpha` < `beta` < `RC` <
  `stable`); Standard laut Doku `stable`. Keine Beta-Versionen in Produktion.
- `prefer-stable` – falls doch einmal eine niedrigere Stabilität erlaubt wird, bevorzugt Composer stabile Versionen.

```json
"require": {
    "php": ">=8.5.12",
```
**Plattform-Anforderung:** Composer verweigert die Installation auf einer zu alten PHP-Version. Wir haben `8.5.12`
gesetzt (die Version im Image), damit niemand versehentlich mit einem älteren PHP arbeitet, für das unser Code nicht
getestet ist. Nebeneffekt: Composer wählt nur Paketversionen, die PHP 8.5 unterstützen.

```json
    "ext-ctype": "*",
    "ext-iconv": "*",
```
Plattform-Anforderung: Diese PHP-Extensions müssen vorhanden sein (beide im Image enthalten). Hängt mit `replace`
unten zusammen.

```json
    "doctrine/doctrine-bundle": "^3.3",
    "doctrine/doctrine-migrations-bundle": "^4.0",
    "doctrine/orm": "^3.7",
```
Doctrine, aufgelöst aus `symfony/orm-pack`:
- `doctrine/orm` – das ORM: Objekte ↔ Tabellenzeilen, Entities, Repositories, DQL. Baut auf **DBAL** auf (Datenbank-
  Abstraktion, Verbindungen, SQL), das als Abhängigkeit mitkommt.
- `doctrine-bundle` – Integration in Symfony (Konfiguration, Konsolenbefehle, Services)
- `doctrine-migrations-bundle` – versionierte Schema-Änderungen (`make:migration`, `doctrine:migrations:migrate`)

```json
    "symfony/console": "8.1.*",
    "symfony/dotenv": "8.1.*",
    "symfony/flex": "^2",
    "symfony/framework-bundle": "8.1.*",
    "symfony/runtime": "8.1.*",
    "symfony/yaml": "8.1.*"
},
```
Das Skeleton:
- `console` – `bin/console` und Befehle
- `dotenv` – liest die `.env`-Dateien
- `flex` – siehe oben (eigene Versionierung `^2`, nicht an Symfony gekoppelt)
- `framework-bundle` – der Kern: Kernel, Routing, Controller, Service-Container, Cache
- `runtime` – entkoppelt **wie** die App gestartet wird von der App selbst. `public/index.php` gibt nur eine Funktion
  zurück, die den Kernel erzeugt; die Runtime entscheidet, ob klassisch, per FrankenPHP-Worker oder anders gestartet
  wird. Deshalb musste für den Worker-Modus am Code nichts geändert werden.
- `yaml` – YAML-Konfigurationsdateien lesen

```json
"require-dev": {
    "phpstan/phpstan": "^2.3",
    "phpstan/phpstan-doctrine": "^2.1",
    "phpstan/phpstan-phpunit": "^2.1",
    "phpstan/phpstan-symfony": "^2.1",
    "phpunit/phpunit": "^13.4",
    "symfony/browser-kit": "8.1.*",
    "symfony/css-selector": "8.1.*"
},
```
Nur für Entwicklung und CI, **nicht** im Prod-Image (`--no-dev`):
- PHPStan + Erweiterungen – statische Analyse; die Erweiterungen bringen PHPStan bei, was Symfony-Container,
  Doctrine-Repositories und PHPUnit-Asserts zurückgeben
- PHPUnit – Tests
- `browser-kit`, `css-selector` – für funktionale Tests: simulierte HTTP-Anfragen an die App und HTML durchsuchen
  (`$client->request('GET', '/')`, `$crawler->filter('h1')`)

```json
"config": {
    "allow-plugins": {
        "php-http/discovery": true,
        "symfony/flex": true,
        "symfony/runtime": true
    },
```
Welche Composer-Plugins Code ausführen dürfen (siehe Konzepte). `symfony/runtime` ist ebenfalls ein Plugin (erzeugt
`vendor/autoload_runtime.php`). `php-http/discovery` stammt aus dem Skeleton und ist bei uns gar nicht installiert
(nicht in `composer.lock`) – der Eintrag ist wirkungslos, könnte aber auch entfernt werden. Fehlt ein Plugin in der
Liste, fragt Composer interaktiv bzw. bricht in der CI ab.

```json
    "bump-after-update": true,
    "sort-packages": true
},
```
- `bump-after-update` – Composer-Doku: führt nach `update` den Befehl `bump` aus. `bump` hebt die **Untergrenzen** in
  `composer.json` auf die installierten Versionen an (aus `^3.3` wird z. B. `^3.3.2`). Dadurch zeigt `composer.json`
  ehrlich, welche Version mindestens gebraucht wird.
- `sort-packages` – `composer require` sortiert neue Pakete alphabetisch ein (übersichtlicher, weniger Merge-Konflikte).

```json
"autoload": {
    "psr-4": { "App\\": "src/" }
},
"autoload-dev": {
    "psr-4": { "App\\Tests\\": "tests/" }
},
```
PSR-4-Regeln (siehe Konzepte). `autoload-dev` nur für Tests – laut Doku nicht im Prod-Autoloader. Der doppelte
Backslash ist JSON-Escaping für einen einfachen Backslash.

```json
"replace": {
    "symfony/polyfill-ctype": "*",
    "symfony/polyfill-iconv": "*",
    "symfony/polyfill-php72": "*",
    ... bis "symfony/polyfill-php84": "*"
},
```
**Polyfills** sind Pakete, die neue PHP-Funktionen für alte PHP-Versionen nachbauen (z. B. `str_contains()` für
PHP < 8.0). `replace` sagt Composer laut Doku: „Diese Pakete werden durch dieses Paket ersetzt“ → Composer installiert
sie nicht, obwohl andere Pakete sie verlangen.
Begründung (Skeleton-Konvention): Wir laufen auf PHP 8.5 und haben `ext-ctype`/`ext-iconv` (deshalb oben verlangt) –
die Polyfills wären toter Code. Spart Dateien und Autoload-Arbeit.
Andere Polyfills (`polyfill-mbstring`, `polyfill-php85` u. a.) sind installiert, weil sie hier nicht ersetzt werden;
sie tun auf PHP 8.5 nichts Schädliches.

```json
"scripts": {
    "auto-scripts": {
        "cache:clear": "symfony-cmd",
        "assets:install %PUBLIC_DIR%": "symfony-cmd"
    },
    "post-install-cmd": ["@auto-scripts"],
    "post-update-cmd": ["@auto-scripts"]
},
```
- `post-install-cmd`/`post-update-cmd` – Composer-Ereignisse nach `install` bzw. `update` (Doku *Scripts*).
- `@auto-scripts` – das `@` ruft ein anderes Skript aus `scripts` auf.
- `auto-scripts` ist **kein Composer-Standard**: Composer kennt laut Doku nur Strings oder Listen; dieses Objekt wertet
  **Flex** aus (`Flex.php`: `executeAutoScripts`). `symfony-cmd` heißt „als `bin/console`-Befehl ausführen“.
  `cache:clear` baut den Cache neu, `assets:install` kopiert Bundle-Assets nach `public/bundles/`. `%PUBLIC_DIR%`
  ersetzt Flex durch `public`.
- Recipes ergänzen hier eigene Einträge (deshalb ein Objekt: Flex kann Schlüssel gezielt hinzufügen/entfernen).

```json
"conflict": {
    "symfony/symfony": "*"
},
```
`symfony/symfony` ist das alte Komplettpaket mit allen Komponenten. Zusammen mit einzelnen Komponenten würde es Chaos
geben – `conflict` verbietet laut Doku die gleichzeitige Installation.

```json
"extra": {
    "symfony": {
        "allow-contrib": false,
        "require": "8.1.*",
        "docker": true
    }
}
```
`extra` ist laut Composer-Doku ein freier Bereich für Plugins – hier für Flex:
- `allow-contrib: false` – Recipes aus `recipes-contrib` (Community, weniger geprüft) werden nicht automatisch
  angewendet; Flex fragt nach (`Flex.php` ~Zeile 401). Bewusste Entscheidung pro Paket statt pauschales Vertrauen.
- `require: "8.1.*"` – alle `symfony/*`-Pakete auf 8.1 begrenzen (siehe Konzepte). **Beim Upgrade auf 8.2 hier und in
  den `8.1.*`-Zeilen oben ändern**, dann `composer update "symfony/*"`.
- `docker: true` – Flex trägt Docker-Teile von Recipes (DB-Service in `compose.yaml`, Extensions im Dockerfile)
  ohne Rückfrage ein (`DockerComposeConfigurator.php`). Gesetzt vom Template-Entrypoint.

---

## Alternativen

- **Ohne Flex** (Bundles und Konfiguration von Hand) – volle Kontrolle, aber viel Handarbeit; bei Symfony unüblich.
- **`symfony/webapp-pack`** statt Skeleton – alles auf einmal; bewusst nicht gewählt, um nur Benötigtes zu haben.
- **Symfony CLI** (`symfony new`) – macht im Kern dasselbe wie `composer create-project`; im Docker-Setup nicht nötig.
- **Versionen exakt pinnen** (`"doctrine/orm": "3.7.2"`) – bringt bei Anwendungen wenig, weil die Lock-Datei
  ohnehin exakt festlegt; erschwert nur Updates.

## Sicherheit und Best Practices – Checkliste

| Praxis | Status |
|---|---|
| `composer.lock` committet, Builds mit `install` | ✅ |
| Plugins nur explizit erlaubt | ✅ `allow-plugins`, kein extension-installer |
| Community-Recipes nur nach Rückfrage | ✅ `allow-contrib: false` |
| Dev-Pakete nicht in Prod | ✅ `--no-dev` im Dockerfile |
| Bekannte Sicherheitslücken prüfen | ⚠️ Manuell möglich: `docker compose exec php composer audit` (heute: „No security vulnerability advisories found“). Kandidat für die CI. Dependabot meldet ebenfalls |
| Regelmäßig aktualisieren | ✅ Dependabot wöchentlich (PRs prüfen, CI muss grün sein) |
| Neue Pakete prüfen, bevor man sie hinzufügt | Gewohnheit: Wer pflegt es? Wie viele Downloads? Letzte Version? Tippfehler im Namen (Typosquatting)? |
| Nie `composer update` auf dem Server | ✅ Server hat gar kein Composer im Prod-Image |
