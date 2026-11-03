# 01 – Grundkonzepte

Bevor die einzelnen Dateien Sinn ergeben, braucht es ein Bild davon, welche Teile es gibt und wie sie
zusammenspielen.

## Das Gesamtbild

```
Mac (Entwicklung)                       GitHub                                Server (IONOS-VPS)
─────────────────                       ──────                                ──────────────────
Code + Dockerfile + compose*.yaml  ──►  Repo  ──► Actions: Tests ─► Build ─►  ghcr.io (Registry)
docker compose up (Dev-Image)                                 │                    │ docker pull
                                                              └─ Deploy ─ ssh ─►  deploy.sh ─► docker compose up
                                                                                   (Prod-Image + PostgreSQL)
```

Drei Orte, **dieselbe Beschreibung** der Umgebung (Dockerfile + Compose-Dateien). Genau das ist der Kern von Docker:
„Läuft bei mir“ soll heißen „läuft überall“.

---

## 1. Container

### Konzept

Ein Container ist **ein ganz normaler Linux-Prozess**, den der Kernel mit zwei Mechanismen abschottet:

- **Namespaces** – der Prozess sieht nur „seine“ Welt: eigene Prozessliste (er selbst ist PID 1), eigenes
  Dateisystem, eigenes Netzwerk mit eigener IP, eigene Hostnamen, eigene Benutzer-IDs-Sicht.
- **cgroups** – begrenzen und messen Ressourcen (RAM, CPU). Daher kommen die Zahlen in `docker stats`.

Es gibt **keinen eigenen Kernel** im Container. Das ist der Unterschied zur virtuellen Maschine:

| | Virtuelle Maschine | Container |
|---|---|---|
| Kernel | eigener | teilt den des Hosts |
| Start | Sekunden bis Minuten (Betriebssystem bootet) | Millisekunden (Prozess startet) |
| Overhead | ganzes Betriebssystem im RAM | nur der Prozess selbst |
| Isolation | stark (Hypervisor) | schwächer (gleicher Kernel) |

Unser VPS **ist** eine VM (bei IONOS), und in ihr laufen Container. Auf dem Mac läuft Docker Desktop ebenfalls in einer
kleinen Linux-VM, weil macOS keinen Linux-Kernel hat – deshalb ist z. B. Dateizugriff über Bind-Mounts auf dem Mac
langsamer als unter Linux.

### Sicherheit

- Weil der Kernel geteilt wird, ist ein Container **keine Sicherheitsgrenze wie eine VM**. Ein Fehler im Kernel kann
  einen Ausbruch ermöglichen. Deshalb: Prozesse im Container nicht als root laufen lassen (unser Prod-Image läuft als
  `www-data`, siehe [02-dockerfile.md](02-dockerfile.md)), Kernel aktuell halten (unattended-upgrades).
- Wer den **Docker-Daemon** steuern darf, ist faktisch root auf dem Host: Er kann einen Container starten, der `/` des
  Hosts einbindet. Docker-Doku: „The docker group grants root-level privileges to the user“
  (*Linux post-installation steps*). Darauf baut das ganze Sicherheitskonzept des Deployments auf
  ([05-ci-cd.md](05-ci-cd.md)).

---

## 2. Image und Layer

### Konzept

Ein **Image** ist die Vorlage für einen Container: ein Dateisystem-Abbild plus Metadaten (welcher Befehl startet,
welche Umgebungsvariablen, welcher Benutzer). Ein **Container** ist ein laufendes (oder gestopptes) Exemplar davon.
Analogie: Image = Klasse, Container = Objekt.

Ein Image besteht aus **Layern** (Schichten). Jede Anweisung im Dockerfile, die Dateien verändert (`RUN`, `COPY`),
erzeugt einen Layer, der nur die **Änderungen** gegenüber dem vorherigen enthält. Beim Start legt Docker die Layer
übereinander und eine beschreibbare Schicht obendrauf. Was der Container zur Laufzeit schreibt, landet in dieser
obersten Schicht und ist **weg, sobald der Container gelöscht wird** – deshalb braucht die Datenbank ein Volume (s. u.).

Layer bringen zwei Vorteile:

- **Cache beim Bauen:** Hat sich an einer Anweisung und allem davor nichts geändert, nimmt Docker den fertigen Layer
  aus dem Cache. Deshalb kopiert das Dockerfile erst `composer.json`/`composer.lock`, installiert die Pakete und
  kopiert **danach** den Code: Eine Code-Änderung macht den teuren `composer install`-Layer nicht ungültig.
- **Teilen:** Zwei Images mit derselben Basis teilen sich deren Layer auf der Platte und beim Download.

### Tags und Digests

- `postgres:18-alpine` – **Tag**: ein Name, der auf ein Image zeigt. Tags sind **verschiebbar**: `18-alpine` zeigt
  nach dem nächsten Patch-Release auf ein neues Image.
- `sha256:c44905c0…` – **Digest**: Prüfsumme des Inhalts, unveränderlich.
- Unsere App-Images bekommen den **Commit-Hash als Tag** (`app-php-prod:9cd534a…`). Wir verschieben ihn nie; damit ist
  er praktisch so eindeutig wie ein Digest, aber lesbar und mit dem Git-Commit verknüpft. Das macht Rollbacks einfach.

### Alternativen und Best Practices

- **`latest`-Tag im Deployment** – verbreitet, aber man weiß nie, was läuft, und Rollback ist unklar. Deshalb nutzen
  wir ihn nicht.
- **Basis-Images per Digest pinnen** (`postgres:18-alpine@sha256:…`) – maximal reproduzierbar, aber man bekommt
  Sicherheitsupdates nur durch aktives Nachziehen. Wir pinnen auf Major-Version (`18`), bekommen Patches automatisch
  bei jedem Build, und Dependabot meldet neue Versionen. Für ein kleines Projekt ein vernünftiger Kompromiss.
- **Kleine Images**: weniger Pakete = weniger Angriffsfläche und weniger Platz auf der 8,7-GB-Platte. Unser
  Prod-Image kopiert nur die nötigen Dateien in ein schlankes Debian (siehe Multi-Stage in
  [02-dockerfile.md](02-dockerfile.md)).

---

## 3. Registry

Eine **Registry** ist ein Server, der Images speichert und ausliefert – wie GitHub für Code, nur für Images.
Docker Hub ist die bekannteste (daher kommen `postgres` und `dunglas/frankenphp`). Wir nutzen **ghcr.io** (GitHub
Container Registry): gleiche Rechteverwaltung wie das Repo, das Image ist privat, weil das Repo privat ist.

Ablauf: Die CI baut das Image und **pusht** es nach ghcr.io; der Server **pullt** es. Dadurch wird auf dem Server nie
gebaut (harte Regel: Build braucht viel RAM, der Server hat ~850 MiB).

**Alternativen:**
- Docker Hub (privat nur eingeschränkt kostenlos; nicht geprüft, wie die aktuellen Limits sind)
- Image als Datei per `docker save`/`scp`/`docker load` übertragen – geht ohne Registry, aber ohne Versionshistorie
  und mit viel Datenübertragung bei jedem Deploy
- Auf dem Server bauen (`git pull && docker compose build`) – häufigster Einsteiger-Weg; braucht Build-Werkzeuge und
  RAM auf dem Server, und der Server braucht Zugriff aufs Repo. Bei uns ausgeschlossen.

**Sicherheit:** Wer in die Registry pushen darf, bestimmt, was auf dem Server läuft. Deshalb pusht nur die CI (mit
dem kurzlebigen `GITHUB_TOKEN`), und der Server hat nur einen Lese-Token.

---

## 4. Docker Compose

### Konzept

Ein einzelner Container lässt sich mit `docker run …` starten – mit vielen Optionen (Ports, Volumes, Variablen,
Netzwerk). Bei mehreren Containern, die zusammengehören, wird das unübersichtlich. **Compose** beschreibt die ganze
Anwendung deklarativ in YAML: welche **Services** (bei uns `php` und `database`), welche Images, Ports, Volumes,
Umgebungsvariablen. `docker compose up` sorgt dafür, dass der **Ist-Zustand dem Soll-Zustand** entspricht: Was fehlt,
wird erstellt; was sich geändert hat, wird neu erstellt; was gleich ist, bleibt laufen. Deshalb lief beim Rollback die
Datenbank einfach weiter.

**Mehrere Dateien werden zusammengeführt** (`-f a.yaml -f b.yaml`): spätere Dateien ergänzen oder überschreiben
frühere. Wir nutzen:

| Datei | Wann geladen | Inhalt |
|---|---|---|
| `compose.yaml` | immer | Gemeinsame Basis |
| `compose.override.yaml` | lokal automatisch (Compose lädt sie standardmäßig, wenn kein `-f` angegeben ist) | Dev: Bind-Mount, Dev-Image, DB-Port |
| `compose.prod.yaml` | nur explizit mit `-f` (CI-Build, Server) | Prod: Prod-Image, Pflicht-Secrets, DB-Limits |

Details: [03-compose-und-frankenphp.md](03-compose-und-frankenphp.md).

### Alternativen

- **Kubernetes** – Orchestrierung für viele Server; für einen VPS mit 850 MiB völlig überdimensioniert.
- **Docker Swarm** – Compose-ähnlich, mit Rolling Updates über mehrere Knoten; brauchen wir nicht.
- **Ohne Docker** (PHP, Caddy, PostgreSQL direkt auf dem Server installieren) – weniger Overhead, aber Dev und Prod
  laufen auseinander, und Updates des Systems können die App beeinflussen.
- **Podman** – Docker-kompatibel, ohne zentralen root-Daemon (sicherheitlich interessant); kleinere Community,
  Template ist auf Docker ausgelegt.

---

## 5. Volumes und Bind-Mounts

Da die beschreibbare Schicht eines Containers mit ihm verschwindet, gibt es zwei Arten, Daten außerhalb zu halten:

- **Benanntes Volume** (`database_data`, `caddy_data`): Docker verwaltet einen Speicherort selbst
  (unter `/var/lib/docker/volumes/…`). Überlebt `docker compose down`; gelöscht wird es nur mit
  `docker compose down -v` oder `docker volume rm`. **Achtung:** `-v` löscht die Datenbank.
- **Bind-Mount** (`./:/app` in der Dev-Umgebung): ein Ordner des Hosts wird direkt in den Container eingeblendet.
  Änderungen am Mac sind sofort im Container sichtbar – das macht lokale Entwicklung ohne Neubauen möglich.
- **Anonymes Volume** (`/app/var` in `compose.override.yaml`): ein Volume ohne Namen, hier benutzt, um `var/` (Cache,
  Logs) **aus** dem Bind-Mount herauszunehmen – schneller auf dem Mac.

In **Produktion** gibt es keinen Bind-Mount für den Code: Der Code steckt im Image. Das ist Absicht – das Image ist
dann vollständig und unveränderlich, und was getestet wurde, ist exakt das, was läuft.

**Sicherheit/Best Practice:**
- Datenbank-Volumes sind **kein Backup**: Ein `down -v`, ein Plattenfehler oder ein kaputtes Upgrade, und alles ist
  weg. Backups sind ein offener Punkt (DSGVO) im Projektstand.
- Bind-Mounts von sensiblen Host-Pfaden (`/`, `/var/run/docker.sock`) in Container geben dem Container Macht über den
  Host – vermeiden.

---

## 6. Netzwerke und Ports

Compose legt für jedes Projekt ein **eigenes Netzwerk** an. Darin erreichen sich die Container über den
**Service-Namen** als Hostname: Deshalb steht in `DATABASE_URL` `@database:5432`. Von außen ist davon nichts
erreichbar, außer man **veröffentlicht** einen Port (`ports:`). Bei uns veröffentlicht nur `php` die Ports 80 und 443
(TCP) und 443 (UDP, für HTTP/3).

**Wichtigster Fallstrick (harte Regel):** Docker schreibt für veröffentlichte Ports eigene iptables-Regeln, die vor den
Regeln von ufw greifen. Ein veröffentlichter Datenbank-Port wäre **trotz ufw aus dem Internet erreichbar**. Deshalb
veröffentlicht nur `compose.override.yaml` (lokal) den DB-Port, und der Server lädt diese Datei nie.

Zusätzlicher Schutz: Die IONOS-Cloud-Firewall vor dem Server lässt nur 22, 80, 443 durch – auch das würde einen
versehentlich veröffentlichten Port blocken. Zwei Schichten sind besser als eine („Defense in Depth“).

---

## 7. Wie PHP ausgeführt wird: drei Modelle

Das ist der wichtigste Unterschied zu klassischen Setups.

### a) Apache + mod_php (klassisch)

Apache lädt PHP als Modul. Jede Anfrage: PHP startet das Skript, lädt Symfony, beantwortet, vergisst alles.

### b) Nginx + PHP-FPM (heute Standard)

Nginx nimmt Anfragen an, liefert statische Dateien selbst aus und reicht PHP-Anfragen über das FastCGI-Protokoll an
einen PHP-FPM-Pool weiter. Zwei Prozesse/Container, die zusammenarbeiten müssen. Auch hier: jede Anfrage startet bei
null (OPcache hält nur den kompilierten Code, nicht die geladenen Objekte).

### c) FrankenPHP (unser Setup)

FrankenPHP ist ein in Go geschriebener Server, der **Caddy** (Webserver) und **PHP** (als eingebettete Bibliothek) in
**einem Prozess** vereint. Zwei Betriebsarten:

- **Klassisch:** wie PHP-FPM, jede Anfrage startet bei null.
- **Worker-Modus (bei uns aktiv):** Symfony wird **einmal** gebootet und bleibt im Speicher. Jede Anfrage läuft im
  schon geladenen Kernel. Deutlich schneller.

Laut FrankenPHP-Doku startet der Worker-Modus standardmäßig **2 × Anzahl CPUs** Worker → bei 1 vCore 2 Worker.

**Was man im Worker-Modus beachten muss (wichtig für später):**
- **Zustand überlebt Anfragen.** Wer in einem Service etwas in einer Eigenschaft speichert (z. B. den aktuellen
  Benutzer, einen Zähler), sieht es in der nächsten Anfrage eines **anderen** Benutzers wieder. Symfony setzt seine
  eigenen Services zwischen Anfragen zurück (`ResetInterface`/`kernel.reset`), eigene Services müssen das ggf. auch.
  Bei Personendaten (DSGVO) ist das ein echtes Risiko.
- **Speicherlecks** summieren sich über viele Anfragen. FrankenPHP kann Worker nach einer Anzahl Anfragen neu starten
  (`max_requests`, laut Doku experimentell).
- **Code-Änderungen** werden erst nach Neustart des Workers aktiv; in Dev erledigt das `watch`.

**Alternativen:** Nginx + PHP-FPM ist erprobter und bekannter (viel Doku, viele Beispiele), braucht aber zwei Container
und eine eigene TLS-Lösung (z. B. certbot). Für unseren Server ist ein Container mit automatischem HTTPS die
schlankere Lösung.

### Caddy und automatisches HTTPS

Caddy holt für jede Domain in der Konfiguration selbst ein Zertifikat von Let's Encrypt (Ersatz: ZeroSSL), erneuert es
vor Ablauf und leitet HTTP auf HTTPS um (Caddy-Doku *Automatic HTTPS*). Bei uns geprüft: Zertifikat von Let's Encrypt,
90 Tage gültig, HTTP antwortet mit `308 Permanent Redirect`.

Wie Let's Encrypt prüft, dass uns die Domain gehört: Es ruft eine Datei unter `http://sonjaczak.com/.well-known/acme-challenge/…`
ab (HTTP-01) oder verbindet sich per TLS mit einer speziellen Antwort (TLS-ALPN-01). Deshalb müssen DNS auf den Server
zeigen und Port 80/443 offen sein. Die Zertifikate liegen im Volume `caddy_data` – ohne Volume würde Caddy bei jedem
Neustart neue anfordern und schnell in die Rate-Limits von Let's Encrypt laufen.

---

## 8. Symfony: Umgebungen, Flex, Recipes

### Umgebungen (`APP_ENV`)

Symfony kennt Umgebungen: `dev` (Debug, Profiler, ausführliche Fehler), `test` (für PHPUnit, eigene DB `app_test`),
`prod` (kein Debug, Cache wird nicht neu gebaut, Fehlerseite ohne Details – deshalb „Oops! An Error Occurred“).
Konfiguration pro Umgebung liegt in `config/packages/<env>/` bzw. in `when@<env>:`-Blöcken.

### `.env`-Dateien

Ladereihenfolge (spätere überschreiben frühere): `.env` → `.env.local` → `.env.<env>` → `.env.<env>.local`.
**Echte Umgebungsvariablen haben immer Vorrang** vor `.env`-Dateien. Im Container setzt Compose z. B. `DATABASE_URL`
als echte Variable – sie gewinnt gegen den Wert in `.env`.
Konvention: `.env` und `.env.<env>` werden committet (nur Standardwerte, keine Secrets), `*.local` nie.

Achtung, Namensverwechslung: Die **Compose-`.env`** (z. B. `/srv/sonjaczak/.env` auf dem Server) ist etwas anderes –
sie füllt `${VARIABLEN}` in den Compose-Dateien. Siehe [03-compose-und-frankenphp.md](03-compose-und-frankenphp.md).

### Flex und Recipes

Symfony ist in viele kleine Pakete aufgeteilt. **Flex** ist ein Composer-Plugin, das bei `composer require` zusätzlich
ein **Recipe** (Rezept) ausführt: Konfigurationsdateien anlegen, Bundles in `config/bundles.php` eintragen,
`.env`-Variablen ergänzen, ggf. Docker-Services in `compose.yaml` eintragen. Deshalb tauchen nach
`composer require symfony/orm-pack` plötzlich `config/packages/doctrine.yaml`, `src/Migrations/` und der
`database`-Service auf. Details und die Herkunft aller Dateien im Repo: [04-composer.md](04-composer.md).
