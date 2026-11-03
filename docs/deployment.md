# Deployment

Wie ein Push auf `main` auf dem Server landet, und wie der Server dafür einmalig eingerichtet wird.
Begriffe: [begriffe.md](begriffe.md).

## Ablauf

```
git push (main)
   │
   ▼  GitHub Actions (.github/workflows/ci.yaml)
Tests ──► Build ──► Deploy
                     │  ssh deploy@<server> <commit-hash>
                     ▼
Server: /srv/sonjaczak/deploy.sh <commit-hash>        (forced command des SSH-Keys)
   1. docker pull ghcr.io/mikeczak/sonjaczak/app-php-prod:<hash>
   2. compose.yaml + compose.prod.yaml aus dem Image nach /srv/sonjaczak/release/ kopieren
   3. docker compose up --wait   (Secrets aus /srv/sonjaczak/.env)
   4. doctrine:migrations:migrate
   5. ungenutzte Images löschen
```

**Sicherheitskonzept:** Der Benutzer `deploy` ist in der Gruppe `docker` und damit faktisch root
(„The docker group grants root-level privileges to the user“, Docker-Doku *Linux post-installation steps*).
Deshalb darf sein SSH-Key nur `deploy.sh` ausführen (`restrict,command=…` in `authorized_keys`), und das Skript
akzeptiert nur einen 40-stelligen Commit-Hash. Die Compose-Dateien kommen aus dem Image, nicht über SSH –
wer den Key hat, kann also nur Images starten, die unsere CI gebaut hat.

## Verzeichnisse auf dem Server

| Pfad | Besitzer, Rechte | Inhalt |
|---|---|---|
| `/srv/sonjaczak/` | root:deploy, 750 | |
| `/srv/sonjaczak/deploy.sh` | root:root, 755 | Deploy-Skript (Kopie von `deploy/deploy.sh`, manuell aktualisieren) |
| `/srv/sonjaczak/.env` | root:deploy, 640 | Secrets und `SERVER_NAME` (Compose-Variablen, **nicht** die Symfony-`.env`) |
| `/srv/sonjaczak/release/` | deploy:deploy, 750 | Compose-Dateien der laufenden Version, `CURRENT` (Hash), Lock-Datei |

## Einmalige Einrichtung

In den Befehlen `SERVER_IP` durch die IPv4-Adresse des VPS ersetzen.

### B1. Benutzer und Verzeichnisse (auf dem Server, als mike)

```bash
sudo useradd --create-home --shell /bin/sh deploy
sudo usermod -aG docker deploy

sudo install -d -o root   -g deploy -m 750 /srv/sonjaczak
sudo install -d -o deploy -g deploy -m 750 /srv/sonjaczak/release
```

`--shell /bin/sh` ist nötig: Auch der erzwungene Befehl wird über die Login-Shell gestartet.

### B2. Deploy-Skript (vom Mac aus kopieren, dann auf dem Server installieren)

```bash
# Mac, im Projektordner
scp deploy/deploy.sh mike@SERVER_IP:/tmp/deploy.sh

# Server
sudo install -o root -g root -m 755 /tmp/deploy.sh /srv/sonjaczak/deploy.sh && rm /tmp/deploy.sh
```

Das Skript gehört root und ist für `deploy` nicht schreibbar. Ändert sich `deploy/deploy.sh` im Repo, diesen Schritt
wiederholen.

### B3. Secrets (auf dem Server)

```bash
sudo install -o root -g deploy -m 640 /dev/null /srv/sonjaczak/.env
for i in 1 2 3; do openssl rand -hex 32; done      # drei Zufallswerte erzeugen
sudo nano /srv/sonjaczak/.env
```

Inhalt (je einen der Zufallswerte einsetzen):

```
SERVER_NAME=sonjaczak.com
APP_SECRET=<zufallswert 1>
POSTGRES_PASSWORD=<zufallswert 2>
CADDY_MERCURE_JWT_SECRET=<zufallswert 3>
```

**Fallstrick:** `POSTGRES_PASSWORD` wird nur beim allerersten Start (leeres Volume) in die Datenbank übernommen.
Später den Wert in `.env` zu ändern, ändert das Passwort in der Datenbank nicht.

Die Werte zusätzlich im Passwort-Manager ablegen.

### B4. Zugriff auf ghcr.io

1. Auf GitHub: Settings → Developer settings → Personal access tokens → **Tokens (classic)** → Generate new token.
   Name z. B. `sonjaczak-server-pull`, Ablaufdatum setzen (Erinnerung eintragen!), **nur** Scope `read:packages`.
2. Auf dem Server als `deploy` anmelden (Token einfügen, Enter, dann Strg+D):

```bash
sudo -u deploy -H docker login ghcr.io -u MikeCzak --password-stdin
```

Docker speichert den Token in `/home/deploy/.docker/config.json` (unverschlüsselt, nur für deploy lesbar).
Läuft der Token ab, schlägt der Deploy beim `docker pull` fehl → neuen Token anlegen, Schritt wiederholen.

### B5. SSH-Key für GitHub Actions

```bash
# Mac: eigenes Schlüsselpaar nur für das Deployment, ohne Passphrase (die CI kann keine eingeben)
ssh-keygen -t ed25519 -N "" -C "github-actions-deploy" -f ~/.ssh/sonjaczak_deploy
cat ~/.ssh/sonjaczak_deploy.pub
```

```bash
# Server: Public Key mit Einschränkung eintragen (Zeile aus dem cat oben einsetzen)
sudo install -d -o deploy -g deploy -m 700 /home/deploy/.ssh
echo 'restrict,command="/srv/sonjaczak/deploy.sh" ssh-ed25519 AAAA…REST…DES…KEYS github-actions-deploy' \
  | sudo tee /home/deploy/.ssh/authorized_keys >/dev/null
sudo chown deploy:deploy /home/deploy/.ssh/authorized_keys
sudo chmod 600 /home/deploy/.ssh/authorized_keys
```

Test vom Mac – erwartet ist die Usage-Meldung des Skripts, **keine** Shell:

```bash
ssh -i ~/.ssh/sonjaczak_deploy deploy@SERVER_IP
# Usage: deploy.sh <40 character commit hash>
```

Falls stattdessen `Permission denied (publickey)` kommt, obwohl der Key stimmt: Ein frisch angelegter Benutzer hat ein
gesperrtes Passwort (`!`), was sshd je nach Konfiguration als gesperrtes Konto wertet. Dann
`sudo usermod -p '*' deploy` (kein Passwort-Login möglich, aber Konto nicht gesperrt) und erneut testen.

### C. GitHub-Environment und Secrets (Mac, mit gh)

```bash
gh api -X PUT repos/MikeCzak/sonjaczak/environments/production >/dev/null

gh secret set SSH_PRIVATE_KEY --env production < ~/.ssh/sonjaczak_deploy
gh secret set SSH_HOST        --env production --body "SERVER_IP"

# Host-Key des Servers festnageln. Fingerabdruck vergleichen mit: (Server) ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
ssh-keyscan -t ed25519 SERVER_IP 2>/dev/null | tee /dev/stderr | ssh-keygen -lf -
ssh-keyscan -t ed25519 SERVER_IP 2>/dev/null | gh secret set SSH_KNOWN_HOSTS --env production
```

Optional danach den privaten Key vom Mac löschen (`rm ~/.ssh/sonjaczak_deploy`) – er liegt dann nur noch bei
GitHub. Bei Bedarf einfach ein neues Paar erzeugen und B5/C wiederholen.

Im Browser lässt sich unter Repo → Settings → Environments → production zusätzlich „Deployment branches“ auf
`main` beschränken.

### D. DNS bei IONOS

Bei der Domain `sonjaczak.com` im IONOS-DNS:

- **A-Record** für `@` → IPv4 des VPS (ersetzt den bisherigen Eintrag `217.160.0.99`)
- **AAAA-Record** für `@` → IPv6 des VPS (ersetzt `2001:8d8:100f:f000::200`)
- **MX-Records (`mx00/mx01.ionos.de`) und SPF-TXT nicht anfassen** – die sind für Mail.
- `www` hat derzeit keinen Eintrag; Weiterleitung `www` → ohne `www` richten wir später ein.

Prüfen (Mac), bis die neuen Adressen erscheinen:

```bash
dig +short sonjaczak.com A
dig +short sonjaczak.com AAAA
```

Caddy holt das Zertifikat beim Start selbst (Let's Encrypt, Ersatz ZeroSSL; Quelle: Caddy-Doku *Automatic HTTPS*).
Voraussetzung: DNS zeigt auf den Server, Ports 80/443 offen. Ein gekauftes Zertifikat ist nicht nötig.

## Erster Deploy

Erst wenn B–D erledigt sind und `dig` die VPS-Adressen zeigt: pushen und den Lauf beobachten
(`gh run watch`). Danach `https://sonjaczak.com` öffnen – erwartet ist vorerst die Fehlerseite „Not Found“ von
Symfony mit gültigem Zertifikat, weil es noch keine Startseite gibt.

## Rollback

- **Über GitHub:** einen älteren, erfolgreichen Lauf öffnen → beim Job „Deploy“ „Re-run job“. Der Lauf verwendet den
  Commit-Hash von damals.
- **Auf dem Server:** `sudo -u deploy /srv/sonjaczak/deploy.sh <commit-hash>`

Datenbank-Migrationen werden dabei **nicht** zurückgerollt. Sobald es Migrationen gibt, müssen sie so geschrieben
sein, dass die vorherige Version mit dem neuen Schema noch läuft – oder der Rollback braucht einen manuellen Schritt.

## Nützliche Befehle auf dem Server

```bash
cat /srv/sonjaczak/release/CURRENT                         # laufende Version
sudo docker ps --filter label=com.docker.compose.project=sonjaczak   # Status
sudo docker logs -f sonjaczak-php-1                                   # Logs (Caddy/FrankenPHP)
sudo docker stats --no-stream                                         # RAM
free -m                                                               # RAM/Swap des Servers
```
