# 23 AssetMapper: CSS und JavaScript ohne Bundler

Stand 2026-11-23. Installiert: `symfony/asset-mapper` v8.1.11 (`composer.lock`). Quellen: symfony.com/doc/current/frontend.html
und symfony.com/doc/current/frontend/asset_mapper.html (gelesen 2026-11-23).

## 1. Das Problem, das jedes Frontend-Werkzeug löst

Der Browser braucht CSS, JavaScript und Bilder unter einer Adresse. Dabei gibt es drei Aufgaben:

1. **Ausliefern:** Dateien aus dem Projekt über HTTP erreichbar machen.
2. **Cache brechen:** Der Browser soll Dateien lange behalten dürfen, aber eine neue Version sofort laden. Lösung: der
   Inhalt bestimmt den Dateinamen (`app-3lSfDo5.css`). Ändert sich der Inhalt, ändert sich der Name. Das nennt man
   **Versionierung** oder **Fingerprinting**.
3. **Abhängigkeiten auflösen:** `import { Controller } from '@hotwired/stimulus'` sagt nicht, wo die Datei liegt.
   Irgendwer muss den Namen in eine Adresse übersetzen.

Ein **Bundler** (Webpack, Vite, Rsbuild) löst Aufgabe 3, indem er vor dem Ausliefern alle Module zu wenigen großen
Dateien zusammenklebt. Dafür braucht er Node.js und einen Build-Schritt.

## 2. Das Konzept von AssetMapper

AssetMapper löst Aufgabe 3 **im Browser** statt beim Bauen: mit **Importmaps**, einer Funktion, die alle aktuellen
Browser eingebaut haben. Laut Symfony: „lets you write modern JavaScript and CSS without the complexity of using a
bundler“, „It runs entirely in PHP“.

### Was AssetMapper tut

- **Abbilden und versionieren:** Jede Datei unter `assets/` bekommt eine öffentliche, versionierte Adresse
  (`assets/styles/app.scss` → nach dem Sass-Build `/assets/styles/app-3lSfDo5.css`). In Twig: `asset('images/…')`.
- **Importmap schreiben:** `{{ importmap('app') }}` in `templates/base.html.twig` gibt aus:
  - `<script type="importmap">` mit einer Tabelle „Name → versionierte Adresse“, z. B.
    `"@hotwired/stimulus": "/assets/vendor/@hotwired/stimulus/stimulus.index-….js"`. Der Browser schlägt bei jedem
    `import` darin nach.
  - `<link rel="modulepreload">` für die Module, die sicher gebraucht werden (lädt parallel statt nacheinander).
  - `<script type="module">import 'app';</script>` zum Starten.
  - Ein Shim für ältere Browser ohne Importmaps (laut Doku standardmäßig von einem CDN).
- **CSS aus JavaScript:** `assets/app.js` enthält `import './styles/app.scss';`. AssetMapper erkennt das und macht
  daraus ein `<link rel="stylesheet">`; JavaScript lädt das CSS nicht selbst.
- **Pakete von außen:** `bin/console importmap:require bootstrap` lädt das Paket nach `assets/vendor/` (in
  `.gitignore`) und trägt es mit Version in `importmap.php` ein. `importmap.php` ist sozusagen das `composer.json`
  fürs Frontend; `importmap:install` holt die Dateien wieder (bei uns als Composer-Skript nach `composer install`).

### Entwicklung vs. Produktion

- **dev:** Symfony liefert `/assets/…` bei jeder Anfrage selbst aus, mit dem aktuellen Inhalt. Kein Build nötig, Datei
  speichern, Seite neu laden.
- **prod:** `bin/console asset-map:compile` schreibt alle versionierten Dateien nach `public/assets/`. Caddy liefert sie
  dann direkt aus, ohne PHP. Bei uns im `Dockerfile`, direkt nach `sass:build`.

### Was AssetMapper nicht tut

- **Kein Zusammenkleben:** Jedes Modul ist eine eigene Datei. Mit HTTP/2 und HTTP/3 ist das kaum ein Nachteil, weil
  viele Dateien über eine Verbindung laufen.
- **Kein Übersetzen:** Sass, TypeScript oder JSX versteht der Browser nicht. Dafür gibt es eigene Bundles, die vorher
  übersetzen. Bei uns `symfonycasts/sass-bundle` (Dart Sass, `bin/console sass:build`). Für TypeScript nennt die Doku
  `sensiolabs/typescript-bundle`, für Tailwind `symfonycasts/tailwind-bundle`.
- **Kein Verkleinern (Minify):** „The AssetMapper component doesn't minify assets.“ Optional mit dem SensioLabs
  Minify Bundle. Unser CSS ist trotzdem kompakt, weil Sass in prod mit `style: compressed` übersetzt
  (`config/packages/symfonycasts_sass.yaml`).
- **Kein Tree Shaking:** Ungenutzter Code in einem importierten Modul wird mitgeliefert (deshalb W5 in
  `planung/wartung.md`).

### Bei uns

| Datei | Rolle |
|---|---|
| `config/packages/asset_mapper.yaml` | Pfad `assets/`; `missing_import_mode: strict` (Fehler bei unbekanntem Import, in prod nur Warnung); Sass-Partials `_*.scss` in Unterordnern ausgenommen |
| `importmap.php` | Drei Einstiegspunkte (`app`, `design`, `admin`) und die Pakete Bootstrap 5.3.8, Popper, Stimulus, SimpleWebAuthn |
| `assets/app.js` | Startet Stimulus und importiert `app.scss` |
| `Dockerfile` | `sass:build`, dann `asset-map:compile`, danach Sass-Programm und Bootstrap-Quellen löschen |

## 3. Alternativen

| | AssetMapper (bei uns) | Webpack Encore | Symfony Reprise mit Vite oder Rsbuild | Dateien von Hand in `public/` |
|---|---|---|---|---|
| Node.js nötig | nein | ja | ja | nein |
| Build-Schritt | nein (nur Sass bei uns) | ja | ja | nein |
| Versionierung | immer | optional | ja | selbst machen |
| TypeScript, JSX, Vue-Dateien | über Zusatz-Bundles bzw. nur eingeschränkt | ja | ja | nein |
| Tree Shaking, Code Splitting | nein | ja | ja | nein |
| Stand laut Symfony | empfohlen für neue Projekte | „low-maintenance mode (bug fixes, security patches … only)“ | neuere Alternative zu Encore | – |

Die Spalten zu AssetMapper und Encore folgen der Vergleichstabelle auf symfony.com/doc/current/frontend.html; die Spalte
„von Hand“ ist meine Einordnung.

- **Webpack Encore:** Symfonys Hülle um Webpack, lange der Standard in Symfony-Projekten. Kann alles, was ein Bundler kann, braucht
  aber Node, `package.json`, `node_modules` und einen Build bei jeder Änderung.
- **Symfony Reprise:** bindet moderne Bundler (Vite, Rsbuild) an Symfony an. Laut Doku erledigen diese „Sass/PostCSS,
  TypeScript, JSX/Vue/Svelte, code splitting, minification and Hot Module Replacement“ selbst. Die richtige Wahl für
  eine große JavaScript-Anwendung (React, Vue).
- **Von Hand:** Dateien direkt in `public/` legen und per `<link>`/`<script>` einbinden. Ohne Versionierung muss man
  Cache-Probleme selbst lösen (`?v=2` anhängen), ohne Importmap gibt es keine `import`-Namen.

### Warum AssetMapper für uns passt

Die Website ist serverseitig gerendert (Twig); JavaScript gibt es nur in kleinen Stimulus-Controllern. Dafür lohnt kein
Node im Projekt, im Image und in der CI. Entschieden am 2026-11-11 mit Bootstrap und dem Sass-Bundle
(`projektstand.md`, „Getroffene Entscheidungen“, CSS-Framework). Ein Wechsel zu Reprise wäre sinnvoll, wenn ein Teil
der App eine große JavaScript-Oberfläche wird, etwa das spätere Booking-Formular, falls es mehr als Stimulus braucht.

### Fallstricke

- **`assets/vendor/` nicht committen:** Die Dateien kommen aus `importmap.php` über `importmap:install`.
- **Neue Pakete nur über `importmap:require`:** Ein `import 'irgendwas'` ohne Eintrag in `importmap.php` scheitert in dev
  sofort (`strict`), in prod nur mit Warnung, und im Browser fehlt das Modul.
- **Reihenfolge im Dockerfile:** erst `sass:build`, dann `asset-map:compile`, sonst fehlt das übersetzte CSS im Image.
