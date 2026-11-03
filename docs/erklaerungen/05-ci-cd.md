# 05 – CI/CD: GitHub Actions und Deployment

Dateien: `.github/workflows/ci.yaml`, `deploy/deploy.sh`, `.github/dependabot.yaml`.
Referenzen: GitHub-Doku *Workflow syntax for GitHub Actions*, Docker-Doku *Bake* und *GitHub Actions cache*,
README von `actions/checkout` und `docker/bake-action`, `man sshd` (Abschnitt AUTHORIZED_KEYS FILE FORMAT).
Einrichtung und Rollback: [../deployment.md](../deployment.md).

## Konzepte

### CI und CD

- **Continuous Integration (CI):** Jede Änderung wird automatisch gebaut und getestet. Fehler fallen sofort auf,
  nicht erst beim Deploy.
- **Continuous Delivery/Deployment (CD):** Was die Tests besteht, wird automatisch auslieferbar gemacht (Delivery) bzw.
  direkt ausgeliefert (Deployment). Wir machen Letzteres: Push auf `main` → live.

Voraussetzung dafür, dass das sicher ist: Tests, die etwas aussagen. Derzeit testen wir nur, dass alles startet und
die DB erreichbar ist. **Mit wachsender App müssen die Tests mitwachsen**, sonst geht Kaputtes automatisch live.

### GitHub Actions – die Begriffe

```
Workflow (ci.yaml)  ── ausgelöst durch Ereignis (push, pull_request, …)
 └─ Run (ein Durchlauf, z. B. 41657250962)
     ├─ Job „Tests“   ── läuft auf eigenem Runner (frische VM, ubuntu-latest)
     │   └─ Steps     ── nacheinander: Action (uses:) oder Shell-Befehl (run:)
     ├─ Job „Build“   ── needs: tests  → startet erst, wenn Tests grün
     └─ Job „Deploy“  ── needs: build
```

- **Runner:** eine von GitHub bereitgestellte, frische virtuelle Maschine pro Job. Nichts bleibt zwischen Jobs
  erhalten – deshalb baut „Build“ das Image neu, statt es von „Tests“ zu übernehmen (dort war es ein Dev-Image).
- **Action:** wiederverwendbarer Baustein (`uses: docker/bake-action@v7`), meist ein GitHub-Repo eines Dritten.
  → Fremder Code mit Zugriff auf den Job; siehe Sicherheit.
- **Secrets:** verschlüsselt bei GitHub gespeichert, im Log automatisch maskiert (`***`).
- **Environment** (`production`): eine benannte Zielumgebung mit eigenen Secrets und optionalen Schutzregeln
  (erlaubte Branches, Freigabe durch Personen, Wartezeit). Nur Jobs mit `environment: production` sehen deren Secrets.

### Der Weg eines Commits

```
git push
 → Tests:  Dev-Image bauen → Stack starten → HTTP/Mercure prüfen → Test-DB, Migrationen, PHPUnit, Schema, PHPStan
 → Build:  Prod-Image bauen → Prod-Stack in der CI starten → Smoke-Test → Push nach ghcr.io (Tag = Commit-Hash)
 → Deploy: SSH zum Server mit eingeschränktem Key → deploy.sh <hash> → pull, Compose-Dateien aus Image, up, migrate
```

Der wichtigste Gedanke: **Das Image, das getestet wurde (Build-Smoke-Test), ist bit-genau das, das auf dem Server
läuft.** Es wird nur einmal gebaut und dann nur noch per Hash referenziert.

---

## `ci.yaml` Zeile für Zeile

### Kopf

```yaml
---
name: CI
```
`---` markiert den Beginn eines YAML-Dokuments (optional). `name` erscheint in der GitHub-Oberfläche.

```yaml
on:
  push:
    branches:
      - main
  pull_request: ~
  workflow_dispatch: ~
```
**Auslöser:**
- `push` auf `main` – der normale Weg.
- `pull_request` – bei PRs (z. B. von Dependabot) laufen die Tests, aber **nicht** Build/Deploy (siehe `if:` unten).
  `~` ist YAML für „null“, also „ohne weitere Einschränkung“.
- `workflow_dispatch` – manuell startbar über die Oberfläche („Run workflow“). Laut Doku nur, wenn die Datei auf dem
  Standard-Branch liegt.

```yaml
permissions:
  contents: read
```
Rechte des automatischen `GITHUB_TOKEN` für **alle** Jobs: nur Code lesen. Jobs, die mehr brauchen, erweitern das
einzeln. **Prinzip der minimalen Rechte:** Standardmäßig hätte der Token je nach Repo-Einstellung deutlich mehr
Rechte – wird eine Action kompromittiert, kann sie nur, was der Token darf.

```yaml
concurrency:
  group: ${{ github.workflow }}-${{ github.head_ref || github.run_id }}
  cancel-in-progress: true
```
Doku: „at most one running job or workflow in a concurrency group at any time“.
- `github.head_ref` ist laut Doku **nur bei Pull Requests** gesetzt (Name des PR-Branches). Bei PRs heißt die Gruppe
  also z. B. `CI-dependabot/...` → ein neuer Push auf den PR bricht den alten Lauf ab (spart Zeit).
- Bei Pushes auf `main` ist `head_ref` leer → `github.run_id` (eindeutig pro Lauf) → **jeder Lauf hat eine eigene
  Gruppe**, nichts wird abgebrochen. Wichtig: ein laufendes Deployment soll nie durch einen neuen Push abgebrochen
  werden. Die Reihenfolge der Deploys regelt die Job-Concurrency unten.
- `${{ … }}` sind **Ausdrücke** von GitHub Actions, die vor der Ausführung ausgewertet werden.

### Job „Tests“

```yaml
jobs:
  tests:
    name: Tests
    runs-on: ubuntu-latest
```
Job-ID `tests` (für `needs:`), Anzeigename, Runner-Typ (aktuelle Ubuntu-VM von GitHub, Docker vorinstalliert).

```yaml
    steps:
      - name: Checkout code
        uses: actions/checkout@v7
        with:
          persist-credentials: false
```
Klont das Repo in den Runner. `persist-credentials: false` – laut README speichert checkout sonst den Token für
weitere git-Befehle (Standard `true`). Wir brauchen keine git-Befehle danach, also soll der Token nicht herumliegen,
wo spätere Schritte (oder kompromittierte Actions) ihn finden.

```yaml
      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v4
```
Richtet **Buildx** ein (Docker-CLI-Erweiterung für BuildKit mit erweiterten Funktionen wie externem Cache und Bake).

```yaml
      - name: Build Docker images
        uses: docker/bake-action@v7
        with:
          pull: true
          load: true
          source: .
          files: |
            compose.yaml
            compose.override.yaml
          set: |
            *.cache-from=type=gha,scope=${{github.ref}}
            *.cache-from=type=gha,scope=refs/heads/main
            *.cache-to=type=gha,scope=${{github.ref}},mode=max
```
**Bake** baut Images anhand einer Definitionsdatei – und kann **Compose-Dateien** als Definition lesen
(Docker-Doku *Bake – Compose file*): Aus `build:` und `image:` der Services wird, was und unter welchem Namen gebaut wird.
- `pull: true` – immer die neueste Version der Basis-Images holen (Sicherheitsupdates).
- `load: true` – das fertige Image in den lokalen Docker des Runners laden (sonst bliebe es nur im Build-Cache und
  `docker compose up` könnte es nicht starten).
- `source: .` – lokales Verzeichnis (das ausgecheckte Repo) als Kontext.
- `files` – hier Basis + **Dev**-Override → Dev-Image (mit PHPUnit/PHPStan, weil Dev-Abhängigkeiten installiert werden
  – im Dev-Image macht das der Entrypoint per `composer install`).
- `set` – überschreibt Werte für alle Targets (`*`): **Build-Cache** im GitHub-Actions-Cache (`type=gha`).
  - `cache-from` – Cache lesen: erst vom eigenen Branch, dann von `main`.
  - `cache-to … mode=max` – Cache schreiben, inklusive Zwischen-Stages (laut Doku ist `min` Standard). Spart beim
    nächsten Lauf Minuten.
  - `scope` – Name des Cache-Eintrags; laut Docker-Doku dürfen Workflows nur Caches des eigenen Branches, des
    Basis-Branches und des Standard-Branches lesen.

```yaml
      - name: Start services
        run: docker compose up --wait --no-build
```
Startet den Dev-Stack (wie lokal), `--wait` wartet auf `healthy`, `--no-build` nutzt das eben gebaute Image.

```yaml
      - name: Check HTTP reachability
        run: curl -v --fail-with-body http://localhost
      # - name: Check HTTPS reachability ...
      - name: Check Mercure reachability
        run: curl -vI --insecure --fail-with-body https://localhost/.well-known/mercure?match=test
```
- `--fail-with-body` – bei HTTP-Status ≥ 400 mit Fehler beenden (Step wird rot), aber die Antwort trotzdem ausgeben.
- `http://localhost` antwortet mit einer Weiterleitung auf HTTPS (Status 308, kein Fehler) – prüft nur, dass Caddy
  läuft. Der HTTPS-Check ist auskommentiert, weil `/` noch 404 liefert → **aktivieren, sobald es eine Startseite gibt**.
- Mercure: `-I` = nur Header (HEAD-Anfrage), `--insecure` = lokales Caddy-Zertifikat nicht prüfen.

```yaml
      - name: Create test database
        run: docker compose exec -T php bin/console -e test doctrine:database:create
      - name: Run migrations
        run: docker compose exec -T php bin/console -e test doctrine:migrations:migrate --no-interaction --allow-no-migration
```
- `exec -T` – Befehl im laufenden Container, `-T` ohne Terminal (in der CI gibt es keins).
- `-e test` – Symfony-Umgebung `test` → Datenbank `app_test` (Doctrine hängt in der Test-Konfiguration `_test` an).
- Migrationen auf die leere Test-DB anwenden: prüft, dass alle Migrationen **von null an** fehlerfrei laufen.
- `--allow-no-migration` – kein Fehler, solange es noch keine Migrationen gibt.

```yaml
      - name: Run PHPUnit
        run: docker compose exec -T php bin/phpunit
```
Die Tests (`tests/`), Konfiguration in `phpunit.dist.xml`.

```yaml
      - name: Doctrine Schema Validator
        run: docker compose exec -T php bin/console -e test doctrine:schema:validate
```
Prüft zwei Dinge: Sind die Entity-Mappings in sich korrekt (z. B. Relationen auf beiden Seiten passend)? Und
entspricht die Datenbank nach den Migrationen exakt den Entities? Schlägt fehl, wenn man eine Entity geändert und
**die Migration vergessen** hat – einer der häufigsten Fehler, den dieser Schritt abfängt.

```yaml
      # PHPStan reads the compiled dev container (see phpstan.dist.neon)
      - name: Warm up dev cache
        run: docker compose exec -T php bin/console -e dev cache:warmup
      - name: Run PHPStan
        run: docker compose exec -T php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
```
PHPStan analysiert den Code, ohne ihn auszuführen (Typen, nicht existierende Methoden, mögliche `null`-Fehler).
Die Symfony-Erweiterung liest den kompilierten Service-Container (XML im Dev-Cache), um zu wissen, welcher Service
welchen Typ hat – deshalb vorher den Cache aufwärmen. Level `max` = strengste Stufe; früh streng zu sein ist
billiger als später nachzurüsten.

### Job „Build“

```yaml
  build:
    name: Build
    needs: tests
    if: github.ref == 'refs/heads/main' && github.event_name != 'pull_request'
    runs-on: ubuntu-latest
```
- `needs: tests` – erst nach erfolgreichem Test-Job.
- `if:` – nur bei Push (oder manuellem Start) auf `main`. PRs bekommen kein Image – vor allem, weil Code aus einem PR
  (z. B. von einem Fork) sonst Zugriff auf Schreibrechte für die Registry bekäme.

```yaml
    permissions:
      contents: read
      packages: write
```
Hier – und nur hier – darf der `GITHUB_TOKEN` Pakete (Images) schreiben.

```yaml
    env:
      IMAGES_PREFIX: ghcr.io/mikeczak/sonjaczak/
      IMAGE_TAG: ${{ github.sha }}
      APP_SECRET: ci-smoke-test
      CADDY_MERCURE_JWT_SECRET: ci-smoke-test-mercure-jwt-secret
      POSTGRES_PASSWORD: ci-smoke-test
```
Umgebungsvariablen für alle Steps dieses Jobs. Compose liest sie zur Interpolation:
- `IMAGES_PREFIX` + `IMAGE_TAG` → `image:` in `compose.prod.yaml` → `ghcr.io/mikeczak/sonjaczak/app-php-prod:<sha>`.
  `github.sha` ist der volle Commit-Hash (40 Zeichen).
- Die drei „Secrets“ sind **absichtlich Dummy-Werte**: `compose.prod.yaml` verlangt sie, aber der Stack in der CI ist
  nach dem Job weg. Echte Secrets gibt es nur auf dem Server. Weil sie **zur Laufzeit** gesetzt werden, landen sie
  nicht im Image.

```yaml
    steps:
      - name: Checkout code (wie oben)
      - name: Set up Docker Buildx (wie oben)
      - name: Build production image
        uses: docker/bake-action@v7
        with:
          pull: true
          load: true
          source: .
          files: |
            compose.yaml
            compose.prod.yaml
          set: |
            *.cache-from=type=gha,scope=prod
            *.cache-to=type=gha,scope=prod,mode=max
```
Wie im Test-Job, aber mit `compose.prod.yaml` → Target `frankenphp_prod`, Name mit Commit-Hash. Eigener Cache-Scope
`prod`, damit Dev- und Prod-Cache sich nicht gegenseitig überschreiben (laut Doku überschreiben sich Builds mit
gleichem Scope).

```yaml
      - name: Start production stack
        run: docker compose -f compose.yaml -f compose.prod.yaml up --wait --no-build
      - name: Smoke test
        run: |
          docker compose -f compose.yaml -f compose.prod.yaml exec -T php bin/console about
          docker compose -f compose.yaml -f compose.prod.yaml exec -T php bin/console dbal:run-sql "SELECT 1"
```
**Smoke-Test** („raucht es beim Einschalten?“): Startet das Prod-Image genau so wie auf dem Server und prüft, dass
Symfony im Prod-Modus bootet (`about` zeigt Umgebung, Debug-Status, PHP-Version) und die DB erreicht. Fängt Fehler,
die nur im Prod-Image auftreten – z. B. eine Klasse, die nur in `require-dev` steckt, aber in Prod gebraucht wird.
`run: |` – YAML-Block mit mehreren Zeilen; jede Zeile ein Befehl, der Step bricht beim ersten Fehler ab.

```yaml
      - name: Log in to GitHub Container Registry
        uses: docker/login-action@v4
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}
      - name: Push image
        run: docker push "${IMAGES_PREFIX}app-php-prod:${IMAGE_TAG}"
```
Erst **nach** bestandenem Smoke-Test anmelden und hochladen – ein kaputtes Image kommt nie in die Registry.
`GITHUB_TOKEN` wird von GitHub pro Lauf automatisch erzeugt und verfällt danach; kein Secret, das wir pflegen müssen.

### Job „Deploy“

```yaml
  deploy:
    name: Deploy
    needs: build
    if: github.ref == 'refs/heads/main' && github.event_name != 'pull_request'
    runs-on: ubuntu-latest
    environment: production
```
- `environment: production` – nur dadurch sind `SSH_PRIVATE_KEY`, `SSH_HOST`, `SSH_KNOWN_HOSTS` verfügbar. Jobs
  ohne diese Zeile (Tests, Build) können den Deploy-Key gar nicht lesen – auch nicht, wenn dort eine Action
  kompromittiert wäre. Außerdem zeigt GitHub unter „Deployments“ die Historie.

```yaml
    concurrency:
      group: production
      cancel-in-progress: false
```
Job-Concurrency: Höchstens **ein** Deploy gleichzeitig, über alle Läufe hinweg. `cancel-in-progress: false` – ein
laufender Deploy wird nie abgebrochen (ein halber Deploy, z. B. mitten in einer Migration, wäre schlimmer als ein
langsamer). Ein neuer wartet. (Zusätzlich schützt `flock` im Skript auf dem Server.)

```yaml
    permissions: {}
```
Laut Doku: alle Rechte des `GITHUB_TOKEN` entzogen. Der Deploy-Job braucht keinen Zugriff auf GitHub, nur SSH.

```yaml
    steps:
      - name: Configure SSH
        env:
          SSH_PRIVATE_KEY: ${{ secrets.SSH_PRIVATE_KEY }}
          SSH_KNOWN_HOSTS: ${{ secrets.SSH_KNOWN_HOSTS }}
        run: |
          install -m 700 -d ~/.ssh
          printf '%s\n' "$SSH_PRIVATE_KEY" > ~/.ssh/id_ed25519
          chmod 600 ~/.ssh/id_ed25519
          printf '%s\n' "$SSH_KNOWN_HOSTS" > ~/.ssh/known_hosts
```
- Secrets über `env:` in Umgebungsvariablen und dann `"$VAR"` – **nicht** direkt `${{ secrets.X }}` im `run:`-Text.
  Grund: `${{ }}` wird vor der Ausführung **in den Skripttext eingesetzt**; Sonderzeichen im Wert würden dann als
  Shell-Code interpretiert (Script-Injection). Über Umgebungsvariablen bleibt es Daten. Bei Secrets ist das Risiko
  gering, bei Werten wie PR-Titeln (`github.event.pull_request.title`) ist es ein echtes Einfallstor – gute Gewohnheit.
- `install -m 700 -d ~/.ssh` – Ordner anlegen, nur für den Besitzer zugänglich (SSH verweigert zu offene Rechte).
- `printf '%s\n'` statt `echo` – gibt den Wert unverändert plus genau einen Zeilenumbruch aus (OpenSSH verlangt einen
  Zeilenumbruch am Ende der Schlüsseldatei; `echo` interpretiert in manchen Shells Escape-Sequenzen).
- `known_hosts` – der **öffentliche Host-Key des Servers**, den wir bei der Einrichtung per Fingerabdruck geprüft
  haben. Ohne ihn müsste SSH beim ersten Verbinden jedem Server vertrauen, der unter der IP antwortet
  (**Man-in-the-Middle**: Ein Angreifer im Netz könnte sich als unser Server ausgeben und … bekäme zwar nicht den
  privaten Key, aber wir würden auf einen falschen Server deployen und nichts merken).

```yaml
      - name: Deploy
        env:
          SSH_HOST: ${{ secrets.SSH_HOST }}
          IMAGE_TAG: ${{ github.sha }}
        run: ssh -o BatchMode=yes "deploy@${SSH_HOST}" "$IMAGE_TAG"
```
- `BatchMode=yes` – SSH fragt nie interaktiv (Passwort, „Host trauen?“), sondern scheitert sofort. In der CI würde eine
  Frage sonst bis zum Timeout hängen.
- Als „Befehl“ wird nur der Commit-Hash gesendet. Der Server ignoriert den Befehl und startet immer `deploy.sh`
  (forced command); der gesendete Text landet dort in `SSH_ORIGINAL_COMMAND`.
- Die Ausgabe von `deploy.sh` (`==> Pulling …`, `==> Deployed …`) erscheint direkt im Log des Jobs.

---

## `deploy/deploy.sh` Zeile für Zeile

### Das Sicherheitsmodell zuerst

Der Benutzer `deploy` ist in der Gruppe `docker` und damit **faktisch root**. Wer seinen SSH-Key stiehlt (z. B. durch
ein GitHub-Leck), hätte ohne Einschränkung den Server. Deshalb drei Schichten:

1. **`authorized_keys`**: `restrict,command="/srv/sonjaczak/deploy.sh" ssh-ed25519 AAAA…`
   - `command="…"` (**forced command**, `man sshd`): Egal, was der Client ausführen will, sshd startet **nur** dieses
     Programm. Der gewünschte Befehl steht in der Variable `SSH_ORIGINAL_COMMAND`.
   - `restrict`: schaltet alles Zusätzliche ab – Port-Weiterleitung, Agent-Weiterleitung, X11, Terminal (PTY). Ohne
     das könnte man trotz forced command z. B. über Port-Weiterleitung den Docker-Socket oder interne Dienste erreichen.
2. **Eingabeprüfung im Skript:** Nur exakt 40 Zeichen `0-9a-f`. Damit sind Shell-Tricks (`; rm -rf /`, `$(…)`) und
   Pfadangaben ausgeschlossen – getestet.
3. **Compose-Dateien kommen aus dem Image**, nicht vom Client. Wer den Key hat, kann nur Images starten, die **unsere
   CI** gebaut und gepusht hat. (Erste Planung sah vor, Compose-Dateien per SSH mitzuschicken – das hätte erlaubt,
   eine Compose-Datei mit `volumes: - /:/host` einzuschleusen = root.)

Und das Skript selbst gehört root und ist für `deploy` nicht beschreibbar – sonst könnte ein Angreifer es einfach
ändern.

**Verbleibendes Risiko:** Mit dem Key kann man einen **älteren** Commit deployen (Rollback erzwingen) – auf eine
Version, die z. B. eine inzwischen behobene Lücke enthält. Für unser Projekt akzeptabel; strengere Varianten (signierte
Images, nur Commits aus `main` zulassen) wären möglich.

### Zeilen

```sh
#!/bin/sh
```
**Shebang:** mit welchem Interpreter die Datei ausgeführt wird. `/bin/sh` (auf Ubuntu `dash`, POSIX-Shell) – bewusst
kein Bash-Spezialkram, deshalb mit ShellCheck geprüft.

```sh
set -eu
```
`-e` abbrechen bei Fehler, `-u` Fehler bei unbekannter Variable (wie `SHELL` im Dockerfile). Ein Deploy-Skript, das
nach einem fehlgeschlagenen `pull` einfach weitermacht, wäre gefährlich.

```sh
APP_DIR=/srv/sonjaczak
export IMAGES_PREFIX=ghcr.io/mikeczak/sonjaczak/
IMAGE="${IMAGES_PREFIX}app-php-prod"
```
Feste Werte. `export`, weil Compose `IMAGES_PREFIX` beim Einlesen von `compose.prod.yaml` braucht. **Fest im Skript**,
nicht vom Client – sonst könnte man ein beliebiges Image von irgendwo starten.

```sh
tag=${SSH_ORIGINAL_COMMAND:-${1:-}}
```
Hash aus `SSH_ORIGINAL_COMMAND` (Aufruf per SSH) oder, falls leer, aus dem ersten Argument (manueller Rollback mit
`sudo -u deploy /srv/sonjaczak/deploy.sh <hash>`). `${1:-}` statt `$1`, weil `set -u` sonst bei fehlendem Argument
abbrechen würde.

```sh
case "$tag" in
    *[!0-9a-f]* | "")
        echo "Usage: deploy.sh <40 character commit hash>" >&2
        exit 64
        ;;
esac
if [ "${#tag}" -ne 40 ]; then
    echo "Usage: ..." >&2
    exit 64
fi
```
**Allow-List-Prüfung:** `*[!0-9a-f]*` passt auf alles, was **irgendein** Zeichen außer 0–9/a–f enthält → abweisen.
Leer → abweisen. `${#tag}` = Länge, muss 40 sein.
Best Practice: erlaubte Zeichen festlegen (Allow-List), statt gefährliche zu suchen (Deny-List) – man vergisst sonst
immer ein gefährliches Zeichen.
`>&2` – Fehlermeldung auf stderr. `exit 64` – Konvention aus `sysexits.h`: 64 = falscher Aufruf (`EX_USAGE`).

```sh
exec 9>"$APP_DIR/release/.lock"
flock -n 9 || { echo "Another deployment is running" >&2; exit 75; }
```
**Sperre gegen parallele Deploys:** `exec 9>datei` öffnet die Datei als Dateideskriptor 9 für den Rest des Skripts.
`flock -n 9` setzt eine exklusive Sperre darauf; `-n` = nicht warten, sofort scheitern, wenn schon gesperrt. Die Sperre
endet automatisch, wenn das Skript endet (auch bei Absturz). Exit 75 = `EX_TEMPFAIL` („später erneut versuchen“).
Schützt z. B. vor gleichzeitigem CI-Deploy und manuellem Rollback.

```sh
docker pull --quiet "$IMAGE:$tag"
```
Image aus ghcr.io holen (mit dem Lese-Token aus `/home/deploy/.docker/config.json`). Gibt es den Hash nicht, bricht
das Skript hier ab – nichts wurde verändert.

```sh
container=$(docker create "$IMAGE:$tag")
trap 'docker rm "$container" >/dev/null' EXIT
docker cp "$container:/app/compose.yaml" "$APP_DIR/release/compose.yaml"
docker cp "$container:/app/compose.prod.yaml" "$APP_DIR/release/compose.prod.yaml"
```
- `docker create` legt einen Container an, **ohne ihn zu starten** – nur um Dateien aus dem Image zu kopieren.
- `trap '…' EXIT` – wird beim Ende des Skripts **immer** ausgeführt, auch bei Fehlern: räumt den Hilfscontainer weg.
- `docker cp` – Dateien aus dem Container ins Release-Verzeichnis.

```sh
compose() {
    docker compose \
        --project-name sonjaczak \
        --project-directory "$APP_DIR" \
        --env-file "$APP_DIR/.env" \
        -f "$APP_DIR/release/compose.yaml" \
        -f "$APP_DIR/release/compose.prod.yaml" \
        "$@"
}
```
Shell-Funktion, damit die lange Option-Liste nur einmal dasteht. `"$@"` = alle Argumente der Funktion.
- `--project-name sonjaczak` – fester Projektname → Container heißen `sonjaczak-php-1`, Volumes
  `sonjaczak_database_data`. Ohne festen Namen würde Compose ihn aus dem Ordnernamen ableiten (`release`) – ein
  späterer Ordnerwechsel würde dann **neue, leere Volumes** erzeugen.
- `--env-file` – Secrets und `SERVER_NAME` für die Interpolation.
- Genau zwei `-f`: **nie** `compose.override.yaml` (Dev, DB-Port!).

```sh
export IMAGE_TAG="$tag"
compose up --detach --wait --no-build --remove-orphans
```
- `IMAGE_TAG` → welches Image `compose.prod.yaml` meint.
- `--detach` – im Hintergrund laufen lassen; `--wait` – warten bis `healthy` (sonst scheitert das Skript und der
  CI-Job wird rot); `--no-build` – nie bauen; `--remove-orphans` – Container von Services entfernen, die es nicht
  mehr gibt (z. B. wenn wir einmal einen Service löschen).
- Compose ersetzt nur Container, deren Konfiguration sich geändert hat → beim normalen Deploy wird nur `php` neu
  erstellt, die Datenbank läuft weiter.
- **Kurze Downtime:** Der alte php-Container wird gestoppt, dann der neue gestartet – einige Sekunden ist die Seite
  nicht erreichbar. Zero-Downtime bräuchte zwei Container parallel und einen Proxy davor – für uns nicht nötig.

```sh
compose exec -T php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
```
Migrationen im neuen Container ausführen. Danach läuft die App gegen das neue Schema.
**Wichtige Reihenfolge-Frage für später:** Der neue Code läuft hier schon, **bevor** migriert wurde (wenige
Sekunden). Und beim Rollback läuft alter Code gegen neues Schema. Deshalb Migrationen „rückwärtskompatibel“ planen:
erst Spalte hinzufügen (alter Code ignoriert sie), später in einem eigenen Deploy alte Spalten entfernen
(„Expand/Contract“-Muster). Das besprechen wir beim Datenbankdesign genauer.

```sh
echo "$tag" >"$APP_DIR/release/CURRENT"
docker image prune --all --force >/dev/null
echo "==> Deployed $tag"
```
- `CURRENT` – merkt sich die laufende Version (zum Nachsehen).
- `image prune --all` – löscht **alle** Images, die von keinem Container verwendet werden. Spart Platz auf der
  8,7-GB-Platte; dafür muss ein Rollback das alte Image neu laden (dauert ein paar Sekunden).

---

## Alle Zugangsdaten im Überblick

| Zugangsdatum | Wer hat es | Was kann es | Lebensdauer | Bei Diebstahl |
|---|---|---|---|---|
| `GITHUB_TOKEN` | CI, pro Lauf automatisch | Job-abhängig: lesen / Images pushen | ein Lauf | kaum relevant (verfällt) |
| PAT (classic) `read:packages` | Server (`deploy`) | **alle** Pakete deines Kontos lesen | bis Ablaufdatum | Token auf GitHub widerrufen, neuen anlegen (B4) |
| Deploy-SSH-Key | GitHub-Secret / Server (public) | `deploy.sh <hash>` ausführen, sonst nichts | unbegrenzt | Zeile in `authorized_keys` löschen, neues Paar (B5/C) |
| `SSH_KNOWN_HOSTS` | GitHub-Secret | nichts (öffentlich) | bis Server-Neuinstallation | – |
| `APP_SECRET`, `POSTGRES_PASSWORD`, `CADDY_MERCURE_JWT_SECRET` | `/srv/sonjaczak/.env` + Passwort-Manager | Tokens fälschen / DB-Zugriff (nur aus dem Docker-Netz) | unbegrenzt | rotieren; DB-Passwort mit `ALTER USER` (nicht nur `.env`) |
| Dein persönlicher SSH-Key | Mac | Login als `mike` (sudo) | unbegrenzt | – getrennt vom Deploy-Key |

## Alternativen

| Alternative | Vorteil | Nachteil |
|---|---|---|
| **Pull statt Push** (Server fragt regelmäßig nach neuen Images, z. B. Watchtower) | kein eingehender SSH-Zugang nötig | zusätzlicher Container (RAM), Verzögerung, Migrationen schwerer zu steuern, schlechteres Feedback in der CI |
| **Self-hosted Runner** auf dem Server | kein SSH | Runner braucht RAM und hätte Docker-Rechte bei jedem Workflow – riskanter |
| **Deploy per `docker context`/DOCKER_HOST=ssh://** | kein Skript | Client steuert Docker vollständig → Key = root |
| **OIDC statt langlebiger Schlüssel** | keine dauerhaften Secrets | braucht Cloud-Anbieter mit OIDC-Unterstützung; für einen VPS nicht direkt verfügbar |
| **Fine-grained PAT** statt classic | genauer begrenzbar (einzelne Repos) | die GitHub-Doku zur Container Registry nennt für die Anmeldung nur Tokens (classic) – deshalb classic gewählt; bei Gelegenheit erneut prüfen, ob sich das geändert hat |
| **Kubernetes/Swarm mit Rolling Update** | kein Ausfall beim Deploy | viel zu groß für 850 MiB |

## Sicherheit und Best Practices – Checkliste

| Praxis | Status |
|---|---|
| Minimale Token-Rechte pro Job | ✅ `contents: read` global, `packages: write` nur Build, `{}` bei Deploy |
| Deploy-Secrets nur im Deploy-Job | ✅ Environment `production` |
| Environment auf Branch `main` beschränken | ⚠️ optional, noch nicht gesetzt |
| Host-Key festgelegt | ✅ `SSH_KNOWN_HOSTS` |
| Deploy-Key eingeschränkt | ✅ `restrict` + forced command + Hash-Prüfung + root-eigenes Skript |
| Keine Secrets im Skripttext | ✅ über `env:` |
| Kein Token in `.git/config` | ✅ `persist-credentials: false` |
| Nur getestete Images deployen | ✅ Push erst nach Smoke-Test, Deploy per Hash |
| Ein Deploy gleichzeitig, nie abbrechen | ✅ Job-Concurrency + `flock` |
| Actions auf **Commit-Hash** pinnen statt Tag (`@v7`) | ❌ Tags können vom Action-Autor verschoben werden; ein kompromittiertes Action-Repo könnte so Code in unsere CI bringen. Pinning auf SHA + Dependabot-Updates wäre die strengere Stufe |
| Workflow-Sicherheit automatisch prüfen | ❌ z. B. **zizmor** (Linter für GitHub-Actions-Workflows) – im Projektstand vorgemerkt |
| Abhängigkeits-Updates | ✅ Dependabot wöchentlich (Docker, Composer, Actions) |
| Tests aussagekräftig | ⚠️ derzeit nur Grundfunktionen – muss mit der App wachsen |
