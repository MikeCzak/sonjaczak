# 22 Wartung: Sicherheitsprüfung, Runner, ungenutzte Dienste, OPcache, CSS

Stand 2026-11-23. Erklärt die Begriffe hinter dem Plan `planung/wartung.md` (W1–W5). Quellen stehen bei den Fakten;
was Einschätzung ist, ist als solche formuliert.

## 1. `composer audit`: bekannte Lücken in Abhängigkeiten (W1)

### Das Problem

Unser Code ist der kleinste Teil dessen, was in Produktion läuft. `composer.lock` listet 104 Pakete für Produktion und 38 für die Entwicklung (Stand 2026-11-23) (Symfony,
Doctrine, dompdf, WebAuthn …), jedes in einer festen Version. Wird in einer davon eine Sicherheitslücke gefunden,
läuft sie bei uns, bis wir die Version wechseln. Das nennt man ein Risiko in der **Lieferkette** (Supply Chain).

### Advisories

Eine **Advisory** (Sicherheitsmeldung) beschreibt eine Lücke: welches Paket, welche Versionen betroffen sind, ab welcher
Version sie behoben ist, wie schwer sie wiegt (`low` bis `critical`). Sie hat Kennungen wie **CVE-2026-12345** (weltweites
Verzeichnis) oder **GHSA-xxxx-xxxx-xxxx** (GitHub Advisory Database). Packagist sammelt die Meldungen für PHP-Pakete,
und `composer audit` fragt sie dort ab (getcomposer.org/doc/03-cli.md, „audit“).

### Was der Befehl tut

```bash
composer audit --locked --abandoned=report
```

- Vergleicht jede Paketversion mit den bekannten Meldungen und gibt eine Tabelle aus.
- `--locked`: prüft `composer.lock` statt des Ordners `vendor/`. In der CI ist beides gleich; `--locked` sagt aber genau,
  was gemeint ist: die Versionen, die ins Image kommen.
- `--abandoned=report`: Ein **aufgegebenes** Paket (der Autor pflegt es nicht mehr) wird gemeldet, lässt den Befehl aber
  nicht scheitern. Ohne die Option scheitert er auch daran (Standard `fail`, getcomposer.org/doc/06-config.md).
- **Exit-Code:** 0 ohne Befund, sonst ungleich 0 (03-cli.md). Darauf beruht die CI: Ein Schritt mit Exit-Code ungleich 0
  ist rot, und weil „Build“ `needs: tests` hat, wird dann nichts gebaut und nichts ausgeliefert.

### Was Composer sonst schon tut

- Bei `composer update` und `composer require` blockiert Composer Versionen mit bekannter Lücke von sich aus
  (`policy.advisories.block`, „Defaults to `true`“, 06-config.md) und zeigt am Ende eine Kurzfassung des Audits.
- Bei `composer install` (so baut das Dockerfile) prüft Composer nichts, außer man gibt `--audit` an (03-cli.md).

Die Lücke, die bleibt: Eine Meldung, die erst **nach** unserem letzten `composer update` erscheint. Genau die fängt der
Schritt in der CI beim nächsten Push.

### Und zwischen zwei Pushes?

Die CI läuft nur, wenn wir pushen. Erscheint eine Meldung an einem ruhigen Tag, sagt uns die CI nichts. Dafür gibt es
die **Dependabot alerts** von GitHub: Sie vergleichen `composer.lock` laufend mit der Advisory-Datenbank und melden sich
per Mail und im Tab „Security“ (`erklaerungen/07`). CI-Audit und Alerts ergänzen sich: das eine blockiert den Deploy,
das andere weckt uns auf.

### Fallstrick: der blockierte Notfall-Fix

Erscheint eine Meldung zu einem Paket, während wir dringend etwas anderes ausliefern wollen, ist die CI rot, obwohl
unser Commit nichts damit zu tun hat. Auswege, in dieser Reihenfolge:

1. `composer update <paket>` auf die reparierte Version (meist ein Patch-Release, harmlos).
2. Gibt es noch keine reparierte Version und betrifft die Lücke uns nachweislich nicht: die Meldung gezielt ausnehmen,
   mit Begründung, in `composer.json` unter `config.policy.advisories.ignore-id` (06-config.md). Nie den ganzen Schritt
   abschalten.

## 2. Runner und Runner-Label (W2)

### Was ein Runner ist

Jeder Job in `.github/workflows/ci.yaml` läuft auf einem **Runner**: einer virtuellen Maschine, die GitHub für den Job
startet und danach wegwirft. `runs-on: ubuntu-latest` sagt, welche Art Maschine. Auf ihr ist ein **Runner-Image**
installiert: Betriebssystem plus viele Werkzeuge (Docker, Git, Compilers …). Das Image steht im Log jedes Laufs unter
„Operating System“ und „Image“.

### Labels

Das Label wählt das Image. Laut docs.github.com gibt es für x64 unter anderem `ubuntu-22.04`, `ubuntu-24.04`,
`ubuntu-26.04` und `ubuntu-latest`. Die festen Labels bleiben bei ihrer Version; `ubuntu-latest` zeigt auf „the latest
stable images that GitHub provides“ und wandert mit.

### Was gerade passiert ist

GitHub hat angekündigt, `ubuntu-latest` von 24.04 auf 26.04 umzustellen, schrittweise ab 2026-10-19, fertig bis
2026-11-19 (github.com/actions/runner-images/issues/14748). Neu sind unter anderem ein anderer Kernel, ein neues systemd
und ein neueres Docker. Andere Projekte melden in der Ankündigung Brüche. Unser Lauf vom 2026-11-23 lief bereits auf
„Image: ubuntu-26.04“, grün. Wir hatten Glück, aber keine Kontrolle.

### Pinnen

**Pinnen** heißt: eine feste Version eintragen statt einer, die sich bewegt. Das gleiche Prinzip kennst du von
`composer.lock` (feste Paketversionen) oder Docker-Tags (`postgres:18-alpine` statt `postgres:latest`). Der Gewinn:
Ein roter Lauf hat genau eine Ursache, nämlich unsere Änderung. Der Preis: Den Wechsel auf eine neue Version muss man
selbst anstoßen. GitHub unterstützt je Betriebssystem höchstens zwei GA-Versionen und kündigt das Auslaufen einer
Version mit Vorlauf und kurzen gezielten Ausfällen („brownouts“) an (README von actions/runner-images).

Abgrenzung zu „Actions auf Commit-SHA pinnen“ (eigener offener Punkt): Dort geht es um den Code der Actions
(`actions/checkout@v7`), den jemand austauschen könnte; hier um die Maschine.

## 3. Mercure und Vulcain: ungenutzte Dienste (W3)

### Mercure

Ein Protokoll und ein Dienst (**Hub**), über den der Server dem Browser von sich aus Neuigkeiten schicken kann, z. B.
„Einheit wurde verschoben“, ohne dass der Browser neu lädt. Technik: **Server-Sent Events (SSE)**, eine HTTP-Verbindung,
die offen bleibt und über die der Server Nachrichten nachschiebt. Wer etwas veröffentlichen will, schickt einen
`POST` mit einem signierten **JWT** an den Hub; Browser abonnieren mit `EventSource` (mercure.rocks). Der Hub ist bei
FrankenPHP eingebaut und läuft unter `/.well-known/mercure`.

Bei uns: Das Template hat ihn eingeschaltet, mit `anonymous` (abonnieren ohne Token) und `subscriptions` (Abfrage
der Abos). Der Hub antwortet in Produktion (2026-11-23 geprüft), aber niemand veröffentlicht etwas.

### Vulcain

Eine Erweiterung für APIs: Ein Client kann mit dem Header `Preload` verlangen, dass der Server verknüpfte Ressourcen
gleich mitschickt (über **103 Early Hints** oder HTTP/2 Server Push), und mit `Fields` nur bestimmte Felder anfordern
(github.com/dunglas/vulcain). Nützlich für JSON-APIs mit vielen Verweisen (z. B. API Platform). Wir haben keine
solche API.

### Warum abschalten

- **Angriffsfläche:** Jeder Endpunkt ist Code, der Anfragen von außen verarbeitet. Ein Hub mit `anonymous` lässt jeden
  eine SSE-Verbindung öffnen, die offen bleibt. Bei 850 MiB RAM ist jede fremde Verbindung, die Ressourcen hält, eine
  zu viel. Konkrete Lücken in Mercure kenne ich nicht; das Prinzip „was nicht läuft, kann nicht angegriffen werden“
  gilt trotzdem.
- **Secrets:** Der Hub braucht `CADDY_MERCURE_JWT_SECRET`. Ein Secret, das nichts schützt, muss trotzdem gepflegt werden.
- **Verständlichkeit:** Weniger Konfiguration, die man beim Lesen erklären muss.

### Konfiguration vs. Binary

Die Module stecken im offiziellen FrankenPHP-Binary („Mercure and Vulcain are included in the official build, but feel
free to remove them.“, frankenphp.dev/docs/docker/). Caddy führt ein Modul aber nur aus, wenn das `Caddyfile` es
einträgt („Mercure support is disabled by default“, frankenphp.dev/docs/mercure/). Den Code ganz loszuwerden hieße,
FrankenPHP selbst zu bauen (xcaddy); das lohnt sich für uns nicht.

## 4. OPcache (W4)

### Was OPcache tut

PHP übersetzt jede Datei vor dem Ausführen in **Opcodes** (Bytecode für die PHP-Maschine). Ohne Cache geschieht das bei
jeder Anfrage für jede Datei. **OPcache** legt die übersetzten Opcodes in einen **gemeinsamen Speicher** (Shared
Memory), aus dem alle Anfragen lesen. Bei Symfony mit Hunderten Dateien je Anfrage ist das der größte einzelne
Geschwindigkeitsgewinn (symfony.com/doc/current/performance.html).

### Die Einstellungen in `frankenphp/conf.d/10-app.ini`

| Einstellung | Bei uns | PHP-Standard | Bedeutung (php.net, opcache.configuration) |
|---|---|---|---|
| `opcache.memory_consumption` | 256 | 128 | Größe des gemeinsamen Speichers in MB |
| `opcache.interned_strings_buffer` | 32 | 8 | Speicher in MB für **Interned Strings**: gleiche Zeichenketten (Klassennamen, Array-Schlüssel) nur einmal |
| `opcache.max_accelerated_files` | 32531 | 10000 | Höchstzahl Dateien im Cache (PHP rundet auf eine Primzahl auf) |
| `opcache.validate_timestamps` | 0 (nur prod) | 1 | Nicht prüfen, ob sich Dateien geändert haben; im Image ändern sie sich nie |
| `opcache.preload` | `config/preload.php` (prod) | – | Beim Start die wichtigsten Klassen vorab laden |

Die großzügigen Werte stammen aus dem Template bzw. der Symfony-Doku, geschrieben für Server mit viel RAM.

### Warum messen statt raten

Wie viel davon belegt ist, hängt von unserer App ab (Zahl der Dateien, Größe von Doctrine-Proxys, Twig-Cache …).
`opcache_get_status()` liefert es genau: `memory_usage` (`used_memory`, `free_memory`, `wasted_memory`),
`interned_strings_usage` und `opcache_statistics` mit `num_cached_scripts`, `hits`, `misses`, `oom_restarts`
(php.net, opcache-get-status).

- **wasted_memory:** Speicher von Skripten, die ersetzt wurden. Bei uns (Image ändert sich nie im Betrieb) sollte er
  nahe 0 sein.
- **oom_restarts:** wie oft der Cache voll war und neu begann. Muss 0 sein; sonst ist der Speicher zu klein.

### Fallstrick: CLI und Webserver

`docker compose exec php bin/console …` startet einen **eigenen** PHP-Prozess. Er hat nicht den Cache des Webservers,
und OPcache ist für die CLI standardmäßig aus (`opcache.enable_cli`, Standard 0). Ein Befehl misst also nichts
Brauchbares. Darum eine Seite im Admin: Sie läuft im FrankenPHP-Worker und sieht den echten Cache.

### Reserviert ist nicht belegt

256 MB sind **reserviert**, nicht unbedingt im RAM. Linux ordnet einem Prozess Speicherseiten in der Regel erst zu,
wenn er sie beschreibt (allgemeines Verhalten, für OPcache nicht eigens nachgelesen). Ist OPcache zu 40 MB gefüllt,
kosten die übrigen 216 MB vermutlich kaum echten RAM. Darum gehört zur Entscheidung auch `docker stats` vorher und
nachher: Erst wenn eine Senkung dort sichtbar wird, hat sie etwas gebracht.

## 5. CSS-Größe (W5)

### Drei Zahlen, die man unterscheiden muss

| | Unser `app.css` (2026-11-23) | Was es kostet |
|---|---|---|
| Unkomprimiert | 243 KB | Der Browser muss alles einlesen und auswerten (Parsen), bei jedem Seitenaufruf ohne Cache im Speicher |
| Mit Brotli übertragen | 30 KB | Ladezeit über das Netz, einmal pro neuer Version |
| Mit gzip übertragen | 38 KB | Dasselbe für Browser ohne Brotli |

Caddy komprimiert jede Antwort (`encode zstd br gzip`), und der Dateiname enthält einen Hash (`app-3lSfDo5.css`), sodass
der Browser die Datei behalten darf, bis sie sich ändert. Deshalb ist der Gewinn bei uns eher klein.

### Woher die Größe kommt

`@import 'bootstrap';` holt alle Teile: Grid, alle Komponenten (Modal, Toast, Offcanvas, Popover, Pagination …) und
Tausende Hilfsklassen (`mt-3`, `d-flex`, `text-center` …), auch die, die wir nie benutzen. Sass weiß nicht, welche
Klassen im HTML stehen; es übersetzt einfach alles.

### Zwei Wege zum Verkleinern

1. **Weniger importieren:** Bootstrap empfiehlt selbst, nur benötigte Teile zu importieren (Option B,
   getbootstrap.com/docs/5.3/customize/sass/). Pflicht sind `functions`, `variables`, `variables-dark`, `maps`,
   `mixins`, `root`; danach Komponente für Komponente. Arbeit: herausfinden, was wir benutzen. Risiko: Fehlt ein Teil,
   sieht eine Seite kaputt aus, und das merkt kein Test.
2. **Unbenutztes nachträglich entfernen (PurgeCSS):** Ein Werkzeug liest alle Templates und streicht CSS-Regeln, deren
   Klassen nirgends vorkommen. Braucht Node, und Klassen, die erst JavaScript setzt (z. B. `show`, `collapsing`), gehen
   leicht verloren.

Allgemein heißt dieses Prinzip **Tree Shaking** (bei JavaScript) bzw. **Purging** (bei CSS): ausliefern, was gebraucht
wird, nicht alles, was vorhanden ist.
