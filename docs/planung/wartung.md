# Wartung: CI absichern und Aufräumen

Stand: 2026-11-23, **freigegeben mit allen Empfehlungen** (Mike, 2026-11-23), in Umsetzung: W2. Erklärungen zu den Begriffen:
`erklaerungen/22-wartung.md`. Die Schritte heißen W1–W5 und sind unabhängig voneinander; jeder ist ein eigener Commit.

| Schritt | Was | Aufwand | Wirkung |
|---|---|---|---|
| W1 | `composer audit` in der CI | klein | Kein Deploy mit bekannter Sicherheitslücke in PHP-Paketen |
| W2 | Runner-Label pinnen | sehr klein | Betriebssystem der CI wechselt nur, wenn wir es wollen |
| W3 | Mercure und Vulcain abschalten | klein | Weniger Angriffsfläche, ein Secret weniger |
| W4 | OPcache messen | mittel | Zahl statt Schätzung für den RAM-Bedarf |
| W5 | Bootstrap-CSS verkleinern | mittel | Etwas weniger CSS, Gewinn klein |

## W1. `composer audit` in der CI

**Ausgangslage:** `composer audit` prüft die Pakete gegen bekannte Sicherheitsmeldungen (Advisories), standardmäßig über
die API von Packagist (getcomposer.org/doc/03-cli.md, „audit“). Lokal mit Composer 2.11.1 (im Image): „No security
vulnerability advisories found.“ In der CI läuft das bisher nicht. Dependabot alerts sind laut `erklaerungen/07` aus
(API `vulnerability-alerts`: 404, geprüft 2026-11-23).

**Lücke:** Ein Audit in der CI prüft nur beim Push. Wird eine Lücke in einem Paket bekannt, während wir nichts pushen,
merkt das niemand. Dafür sind die Dependabot alerts da.

### Optionen

**A. Schritt im Job „Tests“, scheitert bei Sicherheitslücken, meldet aufgegebene Pakete nur (Empfehlung)**

```yaml
      - name: Audit Composer packages
        run: docker compose exec -T php composer audit --locked --abandoned=report
```

- Pro: Kein Image mit bekannter Lücke geht live. Läuft im schon gestarteten Container, kostet Sekunden.
- Pro: `--locked` prüft `composer.lock`, also genau das, was ins Image kommt.
- Pro: `--abandoned=report` zeigt aufgegebene Pakete im Log, ohne den Deploy zu blockieren. Standard wäre `fail`
  (getcomposer.org/doc/06-config.md, „Defaults to `fail`“). Ein aufgegebenes Paket ist kein akutes Risiko, braucht
  aber einen Ersatz in Ruhe.
- Contra: Eine neue Meldung zu irgendeinem Paket blockiert auch einen dringenden Fix, der damit nichts zu tun hat.
  Ausweg: `composer update <paket>` oder, wenn es keinen Fix gibt, die Meldung in `composer.json` unter
  `config.policy.advisories.ignore-id` mit Begründung ausnehmen (06-config.md).
- Contra: Prüft auch `require-dev` (PHPUnit, PHPStan), die nie in Produktion laufen. Mit `--no-dev` ginge das weg;
  ich würde sie mitprüfen, weil sie in der CI mit Zugriff auf den Code laufen.

**B. Nur melden, nie scheitern** (`|| true` oder eigener Job mit `continue-on-error`)

- Pro: Blockiert nie.
- Contra: Ein Hinweis im Log, den niemand liest. Dann lieber gar nicht.

**C. Eigener Workflow nach Zeitplan** (z. B. wöchentlich)

- Pro: Findet Lücken auch ohne Push.
- Contra: Ein eigener Workflow mit eigenem Aufbau (Image bauen oder Composer installieren), Mails bei Fehlschlag.
  Dasselbe leisten die Dependabot alerts ohne Code. Achtung: Ein `schedule` im bestehenden Workflow würde auch Build
  und Deploy auslösen (deren Bedingung prüft nur den Branch).

### Empfehlung

A, und zusätzlich **Dependabot alerts einschalten** (Mike, GitHub → Settings → Advanced Security bzw. Code security;
kostet nichts, ändert keinen Code). Optional auch Dependabot security updates (PR mit der reparierten Version).

### Umsetzung

1. Schritt in `.github/workflows/ci.yaml` nach „Lint YAML files“.
2. Test: Lauf grün. Prüfen, dass der Schritt bei einer Lücke scheitert: lokal mit einer alten Version eines Pakets
   mit bekannter Meldung in einem Wegwerf-Branch, nicht auf `main` (oder nur den Exit-Code bei `composer audit` lokal
   ansehen).
3. Dokumentation: `erklaerungen/05` (CI Zeile für Zeile) ergänzen, `projektstand.md`.

## W2. Runner-Label pinnen

**Ausgangslage:** Alle drei Jobs laufen auf `ubuntu-latest`. GitHub hat dieses Label von Ubuntu 24.04 auf 26.04
umgestellt (Ankündigung github.com/actions/runner-images/issues/14748: ab 2026-10-19 über mehrere Wochen, Abschluss
geplant bis 2026-11-19). **Unser letzter Lauf (45133550109, 2026-11-23) lief schon auf „Image: ubuntu-26.04“ und war
grün.** Der Wechsel ist also passiert, ohne dass wir es gemerkt haben.

### Optionen

**A. `ubuntu-26.04` (Empfehlung)**

- Pro: Genau das, was heute läuft und grün ist. Der nächste Wechsel passiert erst, wenn wir das Label ändern.
- Pro: Längste Restlaufzeit. GitHub unterstützt „(at maximum) 2 GA images“ je Betriebssystem (README von
  actions/runner-images); 26.04 ist das neuere der beiden.
- Contra: Irgendwann (wenn 28.04 erscheint und danach 26.04 ausläuft) müssen wir selbst umstellen. GitHub kündigt das
  an, mit „brownouts“ vorher (README).

**B. `ubuntu-24.04`**

- Pro: Älter und damit länger erprobt.
- Contra: Wir würden zurück auf eine Version wechseln, auf der wir gerade nicht laufen, und sie läuft früher aus.

**C. `ubuntu-latest` lassen**

- Pro: Nie selbst umstellen.
- Contra: Der Wechsel kommt unangekündigt im Alltag (so wie jetzt), und ein roter Lauf hat dann zwei mögliche
  Ursachen: unser Commit oder das neue Betriebssystem.

### Umsetzung

1. `runs-on: ubuntu-26.04` in allen drei Jobs.
2. Test: Lauf grün, im Log „Image: ubuntu-26.04“.
3. Unter „Termine“ in `projektstand.md` nichts eintragen (kein Datum bekannt); GitHub kündigt das Auslaufen an.
   Ob Dependabot für `github-actions` auch `runs-on` anpasst, habe ich nicht geprüft (vermutlich nicht: es aktualisiert
   Actions, keine Runner-Labels).

## W3. Mercure und Vulcain abschalten

**Ausgangslage:** Das Template schaltet im `Caddyfile` einen Mercure-Hub und Vulcain ein. Wir nutzen beides nicht (kein
`symfony/mercure-bundle`, kein Code, der etwas veröffentlicht). Der Hub ist in Produktion von außen erreichbar
(`/.well-known/mercure?topic=test` antwortet mit 400 vom Hub, `/.well-known/mercure/subscriptions` mit 401; geprüft
2026-11-23), mit `anonymous` (Abonnieren ohne Token). Die CI prüft sogar, dass er erreichbar ist („Check Mercure
reachability“). Dazu verlangt `compose.prod.yaml` das Secret `CADDY_MERCURE_JWT_SECRET` (am Server in der
Umgebungsdatei).

### Optionen

**A. Aus der Konfiguration entfernen, Module bleiben im Binary (Empfehlung)**

Entfernen: Block `mercure { … }` und Zeile `vulcain` im `Caddyfile`, `not path /.well-known/mercure*` im Matcher, die
`MERCURE_*`-Variablen und Flex-Marker in `compose.yaml`, `compose.override.yaml` (`playground`), `compose.prod.yaml`,
den CI-Schritt „Check Mercure reachability“ und `CADDY_MERCURE_JWT_SECRET` im Job „Build“.

- Pro: Kein offener Endpunkt mehr, der Verbindungen offen halten kann (SSE), ein Secret weniger, einfachere
  Konfiguration.
- Pro: Kein eigener Build. Die Module sind im offiziellen FrankenPHP-Image enthalten („Mercure and Vulcain are included
  in the official build, but feel free to remove them.“, frankenphp.dev/docs/docker/); ohne Eintrag im `Caddyfile` tun
  sie nichts. Für Mercure sagt FrankenPHP das ausdrücklich: „Mercure support is disabled by default.“
  (frankenphp.dev/docs/mercure/). Für Vulcain steht es nicht da; ohne die Zeile `vulcain` ist die Middleware aber
  nicht in der Kette (Caddy-Prinzip, nicht eigens nachgelesen).
- Contra: Sollte später Echtzeit nötig werden (z. B. Raster live für zwei Geräte), müssten wir es wieder einbauen.
  Das ist eine Handvoll Zeilen aus dem Template.
- Am Server: `CADDY_MERCURE_JWT_SECRET` in der Umgebungsdatei wird überflüssig; stört nicht, kann später weg
  (Server-Schritt, Mike). Die Datei `mercure.db` (Verlauf des Hubs, Standard `transport bolt`, laut mercure.rocks
  in `/data/caddy/`) bleibt im Volume liegen; klein, kann ebenfalls weg.

**B. Eigenes FrankenPHP ohne die Module bauen** (xcaddy ohne `--with …mercure…`/`…vulcain…`)

- Pro: Der Code ist gar nicht im Image.
- Contra: Eigener Build von Caddy/FrankenPHP in der CI, länger, und wir verlieren das fertige offizielle Image samt
  seinen Updates. Unverhältnismäßig.

**C. Lassen, nur `anonymous` und `subscriptions` abschalten**

- Pro: Kleinste Änderung.
- Contra: Ungenutzter Dienst bleibt, samt Secret und CI-Schritt.

### Umsetzung

1. Änderungen wie bei A.
2. Lokal: `docker compose up --wait`, Seiten laden, `/.well-known/mercure` antwortet jetzt mit Symfonys 404.
3. CI grün, Smoke-Test grün.
4. Nach dem Deploy: `curl -I https://sonjaczak.com/.well-known/mercure` → 404. RAM vorher/nachher mit `docker stats`
   am Server ablesen (Mike), nur als Beobachtung.
5. Doku: `erklaerungen/03` (Hinweis „entfernt am …“), `projektstand.md`.

## W4. OPcache messen

**Ausgangslage:** `frankenphp/conf.d/10-app.ini` (aus dem Template, nach symfony.com/doc/current/performance.html):
`opcache.memory_consumption = 256`, `opcache.interned_strings_buffer = 32`, `opcache.max_accelerated_files = 32531`.
PHP-Standard wäre 128, 8 und 10000 (php.net, opcache.configuration). Der Server hat ~850 MiB RAM. Wie viel davon
OPcache wirklich belegt, wissen wir nicht.

**Warum nicht einfach mit der Konsole messen:** `bin/console` läuft als eigener CLI-Prozess. OPcache ist für die CLI
standardmäßig aus (`opcache.enable_cli`, Standard 0, php.net) und hätte ohnehin nicht den Speicher des Webservers.
Messen muss man **im laufenden FrankenPHP**, also über eine Anfrage an die Website. Eine Datei wie `public/status.php`
geht bei uns nicht: Das `Caddyfile` führt nur `index.php` aus.

### Optionen

**A. Seite „System“ im Admin, nur für Super-Admin (Empfehlung)**

Route `/admin/system` mit den Werten aus `opcache_get_status(false)`: belegter, freier und verschwendeter Speicher,
Zahl der Skripte, Interned Strings, Neustarts wegen vollem Speicher (`oom_restarts`), Trefferquote. Voter bzw.
`#[IsGranted('ROLE_SUPER_ADMIN')]`.

- Pro: Jederzeit ablesbar, auch nach Updates; kein Server-Zugang nötig; testbar.
- Contra: Etwas Code, der dauerhaft bleibt. Zeigt technische Details (nur Mike sieht sie).

**B. Einmalig von Hand am Server**

- Pro: Kein Code im Repo.
- Contra: Geht nur über einen Umweg (z. B. vorübergehend eine Route einbauen und wieder entfernen) oder indirekt über
  `docker stats` (zeigt den ganzen Container, nicht OPcache).

### Danach entscheiden

Mit den Zahlen aus Produktion (nach einigen Tagen Betrieb, alle Seiten einmal aufgerufen):

- Belegung deutlich unter 128 MB → `memory_consumption` auf z. B. das Doppelte der Belegung senken (Puffer für
  Wachstum), ebenso `interned_strings_buffer`.
- `oom_restarts` > 0 oder wenig frei → so lassen.

Ob eine Senkung tatsächlich RAM frei macht, ist offen: Linux belegt Speicher meist erst, wenn er beschrieben wird.
Reservierte, aber unbenutzte OPcache-Megabyte kosten dann kaum echten RAM (allgemeines Linux-Verhalten, für OPcache
nicht nachgelesen). Deshalb vorher und nachher `docker stats` vergleichen.

### Umsetzung

1. Controller, Template, Übersetzungen (`admin`), Test (Super-Admin sieht die Seite, Admin bekommt 403).
2. Nach dem Deploy: Werte ablesen (Mike), in `verlauf.md` notieren.
3. Entscheidung über die Werte als eigener kleiner Schritt (nur `10-app.ini`).

## W5. Bootstrap-CSS verkleinern

**Ausgangslage:** `assets/styles/app.scss` importiert ganz Bootstrap (`@import 'bootstrap'`). Gemessen in Produktion
(2026-11-23, `app-3lSfDo5.css`): **243 KB** unkomprimiert, **30 KB** mit Brotli und 38 KB mit gzip übertragen
(Caddy komprimiert, `encode zstd br gzip`). Dazu kommt das Bootstrap-JavaScript (61 KB, 16,5 KB mit Brotli), ebenfalls
ganz.

**Ehrliche Einordnung:** Übertragen werden nur ~30 KB, die der Browser danach zwischenspeichert (Dateiname mit Hash).
Der Gewinn ist klein, vor allem auf dem ersten Aufruf über das Handy. Der Browser muss die 243 KB aber auch einlesen.

### Optionen

**A. Nur benötigte Teile importieren** (Bootstrap „Option B“, getbootstrap.com/docs/5.3/customize/sass/)

Pflicht laut Bootstrap: `functions`, `variables`, `variables-dark`, `maps`, `mixins`, `root`; dann nur die
Komponenten, die wir nutzen (z. B. `reboot`, `type`, `grid`, `nav`, `navbar`, `forms`, `buttons`, `carousel`,
`accordion`, `tables`, …), zuletzt `utilities/api`.

- Pro: Von Bootstrap selbst empfohlen („We encourage the latter“); kein neues Werkzeug.
- Contra: Wir müssen herausfinden, welche Komponenten benutzt werden (Suche nach Klassen in Templates und JS). Fehlt
  eine, sieht das nur jemand, der die Seite ansieht; die Tests merken es nicht. Bei neuen Komponenten später an den
  Import denken.

**B. PurgeCSS** (entfernt alle Regeln, deren Klassen in keinem Template vorkommen)

- Pro: Größter Gewinn.
- Contra: Braucht Node, das wir bewusst nicht haben (Entscheidung CSS-Framework, 2026-11-11). Klassen, die erst per
  JavaScript entstehen, gehen leicht verloren.

**C. Lassen**

- Pro: Kein Risiko, kein Aufwand.
- Contra: Der Punkt bleibt offen.

### Empfehlung

A in Ruhe: zuerst eine Liste der benutzten Komponenten (ich erstelle sie aus Templates und JS), dann umstellen,
messen und alle Seiten in allen drei Themes im Browser durchsehen (Headless-Screenshots vorher/nachher). Bringt es
weniger als etwa ein Drittel, lassen wir es bei C und schließen den Punkt. Das JavaScript (nur benötigte Plugins
importieren) wäre ein eigener Schritt danach.

## Fragen an Mike

1. W1: Variante A mit `--abandoned=report` und mit `require-dev`? Schaltest du die Dependabot alerts ein?
2. W2: `ubuntu-26.04`?
3. W3: Variante A?
4. W4: Seite „System“ im Admin (A)?
5. W5: Variante A mit der Schwelle „ein Drittel“, sonst C?
6. Reihenfolge: W2, W1, W3 (je ein Push), dann W4, W5.
