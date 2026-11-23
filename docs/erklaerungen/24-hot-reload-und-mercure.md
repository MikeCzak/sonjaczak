# 24 Hot Reload und Mercure

Stand 2026-11-23. Quellen: frankenphp.dev/docs/hot-reload/, frankenphp.dev/docs/mercure/, mercure.rocks und
mercure.rocks/docs/hub/config (gelesen 2026-11-23), dazu unsere Dateien und ein Test im lokalen Container. Anlass:
Beim Entfernen von Mercure (`planung/wartung.md` W3) zeigte sich, dass der lokale Hot Reload den Hub braucht.

## 1. Worum es geht

Beim Entwickeln wiederholt sich ein Kreislauf: Datei ändern → speichern → Browser neu laden → Ergebnis ansehen. Hot
Reload nimmt den dritten Schritt ab: Der Browser merkt selbst, dass sich etwas geändert hat, und zeigt die neue Fassung.

Dafür muss der **Server dem Browser etwas mitteilen**, ohne dass der Browser gefragt hat. Normales HTTP kann das
nicht: Der Browser fragt, der Server antwortet, Ende. Genau diese Lücke füllt Mercure.

## 2. Zwei Mechanismen, die oft verwechselt werden

`compose.override.yaml` schaltet lokal zwei verschiedene Dinge ein:

```yaml
      FRANKENPHP_WORKER_CONFIG: watch
      FRANKENPHP_SITE_CONFIG: hot_reload
```

| | `watch` (im Worker) | `hot_reload` |
|---|---|---|
| Wirkt auf | den Server | den Browser |
| Was passiert | Worker werden neu gestartet, wenn sich eine Datei ändert | Der Browser lädt bzw. aktualisiert die Seite |
| Warum nötig | Im **Worker-Modus** bleibt die App zwischen Anfragen im Speicher (`erklaerungen/01`, Abschnitt 7). Ohne Neustart liefe weiter der alte PHP-Code | Ohne ihn müsstest du selbst neu laden |
| Braucht Mercure | nein | ja |

FrankenPHP beschreibt das so: „hot_reload: refreshes the browser when files change“ und „worker.watch: restarts the
worker when files change“; im Worker-Modus soll man beides kombinieren (frankenphp.dev/docs/hot-reload/). Erst beides
zusammen ergibt: speichern, und der Browser zeigt das Ergebnis mit dem neuen Code.

## 3. Mercure von Grund auf

### Server-Sent Events (SSE)

Grundlage ist eine Funktion, die jeder Browser kann: **Server-Sent Events**. Der Browser öffnet eine normale
HTTP-Anfrage, der Server antwortet mit `Content-Type: text/event-stream` und **beendet die Antwort nicht**. Solange die
Verbindung steht, schreibt er Nachrichten hinein, jede als kleiner Textblock:

```
id: urn:uuid:01a20969-7826-719d-8ed0-daa246f6b194
data: [{"effect_time":"2026-11-23T17:17:16Z","path_name":"/app/var/cache/dev/twig/53/…php", …}]

```

(echte Nachricht aus unserem Test, gekürzt). Im Browser heißt die Gegenstelle `EventSource`:
`new EventSource(url)` öffnet die Verbindung, `onmessage` wird bei jeder Nachricht aufgerufen. Bricht die Verbindung ab,
verbindet sich der Browser selbst neu und schickt die letzte `id` mit, damit der Server Verpasstes nachliefern kann.

Abgrenzung: **WebSockets** können in beide Richtungen senden, brauchen aber ein eigenes Protokoll. SSE geht nur vom
Server zum Browser, ist dafür gewöhnliches HTTP. Für „Server meldet Neuigkeiten“ reicht das.

### Der Hub

SSE allein löst nicht, **wer** die Nachrichten verteilt. PHP eignet sich schlecht dafür, Hunderte Verbindungen offen zu
halten. Mercure trennt das: Ein eigener Dienst, der **Hub**, hält die Verbindungen; die App schickt ihm nur kurze
Nachrichten. Mercure ist ein offenes Protokoll („A Mercure publish is one authenticated HTTP `POST`. A subscribe is one
`EventSource`.“, mercure.rocks). Bei FrankenPHP ist der Hub als Caddy-Modul eingebaut und läuft unter
`/.well-known/mercure`.

Die Begriffe:

- **Topic:** Kennung, worum es in einer Nachricht geht, meist eine URL, z. B. `https://example.com/books/1`. Sie muss
  nicht erreichbar sein, sie ist nur ein Name.
- **Publisher (veröffentlichen):** schickt `POST /.well-known/mercure` mit Topic und Daten. Nur mit einem **JWT**, das
  mit dem Publisher-Schlüssel signiert ist.
- **Subscriber (abonnieren):** öffnet `GET /.well-known/mercure?topic=…` (bei uns `?match=…`) als SSE-Verbindung und
  bekommt alle Nachrichten zu passenden Topics.
- **`anonymous`:** Abonnieren ohne Token, aber nur öffentliche Nachrichten. Private Nachrichten bekommt nur, wer ein
  Subscriber-JWT vorweist.
- **Transport und Verlauf:** Standard ist `bolt`, eine Datei (`mercure.db`), die frühere Nachrichten für das Nachliefern
  speichert (mercure.rocks/docs/hub/config).

### Wofür man Mercure in einer App nutzen würde

Beispiel aus unserer Welt: Sonja hat das Kursraster am Laptop offen, verschiebt eine Einheit am Handy, und der Laptop
zeigt die Änderung ohne Neuladen. Die App würde nach dem Speichern eine Nachricht an den Hub schicken, ein
Stimulus-Controller im Raster würde per `EventSource` zuhören und die Zeile austauschen. Das brauchen wir derzeit nicht;
deshalb läuft in Produktion kein Hub (W3).

## 4. Die Kette beim Hot Reload, Schritt für Schritt

1. **Dateien beobachten:** FrankenPHP beobachtet das Projektverzeichnis. Standard laut Doku:
   `./**/*.{css,env,gif,htm,html,jpg,jpeg,js,mjs,php,png,svg,twig,webp,xml,yaml,yml}`.
2. **Adresse zum Zuhören:** Für jede Anfrage setzt FrankenPHP die Server-Variable `FRANKENPHP_HOT_RELOAD`, bei uns z. B.
   `/.well-known/mercure?match=https%3A%2F%2Ffrankenphp.dev%2Fhot-reload%2F77c9c4c84e7d0ab7`: die Adresse des Hubs mit
   einem Topic, das nur für diesen Server gilt.
3. **Twig gibt die Einbindung aus** (`templates/base.html.twig`, nur wenn die Variable gesetzt ist, also nur lokal):
   ```twig
   {% set frankenphp_hot_reload = app.request.server.get('FRANKENPHP_HOT_RELOAD') %}
   {% if frankenphp_hot_reload %}
   <meta name="frankenphp-hot-reload:url" content="{{ frankenphp_hot_reload }}">
   <script src="https://cdn.jsdelivr.net/npm/idiomorph"></script>
   <script src="https://cdn.jsdelivr.net/npm/frankenphp-hot-reload/+esm" type="module"></script>
   {% endif %}
   ```
4. **Der Browser abonniert:** Das Skript `frankenphp-hot-reload` liest die Adresse aus dem `meta`-Tag und öffnet eine
   `EventSource` zum Hub.
5. **Du speicherst eine Datei.** `watch` startet die Worker neu, und FrankenPHP veröffentlicht eine Nachricht an das
   Topic: „When a file is modified, it pushes a Mercure update to the browser.“ Der Inhalt sind die geänderten Pfade.
6. **Der Browser aktualisiert:** Das Skript lädt die Seite im Hintergrund neu und tauscht mit **Idiomorph** nur die
   geänderten Teile des DOM aus („morphen“); ohne Idiomorph lädt es die ganze Seite neu („Reload the page (standard live
   reload) if Idiomorph is not present“).

Getestet am 2026-11-23: SSE-Verbindung zur Adresse aus dem `meta`-Tag geöffnet, `touch templates/base.html.twig`, nach
kurzer Zeit kam die Nachricht oben an.

### Warum morphen statt neu laden

Beim Morphen bleiben Scrollposition, Eingaben in Formularen und offene Elemente erhalten, soweit sich am HTML dort nichts
ändert. Ein voller Reload setzt alles zurück.

### `data-frankenphp-hot-reload-preserve`

Morphen ersetzt das DOM durch frisches HTML vom Server. Was JavaScript nach dem Laden am DOM geändert hat, ist danach
weg. Auf der Startseite blendet `slider.js` die Pfeile des Sliders ein (sie stehen mit `hidden` im HTML); nach einem
Morph wären sie wieder versteckt, und `slider.js` läuft nicht erneut. Darum trägt `page/home.html.twig` das Attribut
`data-frankenphp-hot-reload-preserve` an den Pfeilen, aber nur, wenn Hot Reload aktiv ist: Das Skript lässt dieses
Element beim Morphen in Ruhe.

## 5. Unsere Konfiguration

Seit W3 steht der Hub **nicht mehr im `Caddyfile`**, sondern wird lokal eingesetzt (`compose.override.yaml`):

```yaml
      CADDY_SERVER_EXTRA_DIRECTIVES: |
        mercure {
            name default
            issuer https://localhost {
                publisher {
                    jwt {env.MERCURE_DEV_JWT_KEY}
                }
                subscriber {
                    jwt {env.MERCURE_DEV_JWT_KEY}
                }
            }
            anonymous
        }
      MERCURE_DEV_JWT_KEY: "!DevOnlyMercureHubJWTSecretKey-NotUsedInProduction!"
```

- **`{$CADDY_SERVER_EXTRA_DIRECTIVES}`** steht im Site-Block des `Caddyfile`. Caddy ersetzt es beim Start durch den
  Inhalt der Variable. In Produktion ist sie leer, dort gibt es also keinen Hub.
- **`name default`:** Der Site-Block gilt für zwei Adressen (`SERVER_NAME: localhost, php:80`). Ohne Namen legt Caddy je
  Adresse einen Hub an und verweigert den Start: „only one unnamed Mercure hub is allowed per configuration“ (beim
  Umsetzen so aufgetreten). Mit Namen teilen sich beide einen Hub.
- **`issuer` mit `publisher` und `subscriber`:** wer Tokens ausstellen darf und mit welchem Schlüssel sie geprüft werden.
  FrankenPHP veröffentlicht die Hot-Reload-Nachrichten intern; der Schlüssel ist nur lokal und darf deshalb fest im Repo
  stehen.
- **`anonymous`:** Das Hot-Reload-Skript abonniert ohne Token.
- **`not path /.well-known/mercure*`** im Matcher `@phpRoute` (`Caddyfile`): Anfragen an den Hub dürfen nicht an
  `index.php` weitergeleitet werden. In Produktion endet die Adresse dadurch mit 404, ohne Symfony zu starten.
- **Weggefallen** gegenüber dem Template: `subscriptions` (Abfrage der Abos), `playground` (Testoberfläche), die
  `MERCURE_*`-Variablen für das nicht installierte `symfony/mercure-bundle` und `CADDY_MERCURE_JWT_SECRET` in
  Produktion.

## 6. Warum nicht in Produktion

- FrankenPHP: „This feature is intended for development environments only.“ Die Nachrichten enthalten Dateipfade aus
  dem Container (siehe Beispiel oben); laut Doku legt Hot Reload interne Details offen.
- Ein Hub braucht ein Secret, hält Verbindungen offen und ist Code, der Anfragen von außen annimmt (`erklaerungen/22`,
  Abschnitt 3). Ohne Nutzen in Produktion ist das nur Angriffsfläche.
- `opcache.validate_timestamps = 0` in Produktion: PHP prüft dort gar nicht auf geänderte Dateien, weil sich im Image
  nichts ändert.

## 7. Fallstricke

- **Skripte vom CDN:** Die beiden Skripte kommen lokal von `cdn.jsdelivr.net`. Ohne Internet funktioniert Hot Reload
  nicht (die Seite selbst schon), und der Browser lädt beim Entwickeln Code von einem fremden Server. In Produktion
  werden sie nie ausgegeben.
- **Zustand aus JavaScript geht beim Morphen verloren** (siehe Slider). Stimulus-Controller verbinden sich nach dem
  Morphen neu, weil Stimulus Änderungen am DOM selbst beobachtet (allgemeines Stimulus-Verhalten, hier nicht eigens
  nachgelesen). Werte, die ein Controller nur im Speicher hält, sind danach zurückgesetzt.
- **Auch Cache-Dateien lösen aus:** Die Nachricht im Test nannte eine Datei in `var/cache/dev/twig/`. Symfony schreibt
  beim Rendern Cache-Dateien, die zum Beobachtungsmuster passen. Lädt die Seite gelegentlich zweimal, ist das der
  Grund.
- **PHP-Änderung ohne `watch`:** Fehlt `watch`, lädt der Browser zwar neu, bekommt aber Antworten vom alten Code im
  Worker.

## 8. Alternativen zum Hot Reload

| | Pro | Contra |
|---|---|---|
| FrankenPHP `hot_reload` (bei uns) | Eingebaut, morpht statt neu zu laden, kein Node | Braucht einen Hub (lokal), Skripte vom CDN |
| Bundler mit HMR (Vite über Symfony Reprise) | Tauscht sogar JavaScript-Module im laufenden Browser | Node und Build-Werkzeug nötig (`erklaerungen/23`) |
| Browser-Erweiterung (z. B. LiveReload) | Unabhängig vom Server | Eigener Dienst, der Dateien beobachtet; Erweiterung im Browser |
| Selbst neu laden | Nichts einzurichten | Der Handgriff, den Hot Reload abnimmt |
