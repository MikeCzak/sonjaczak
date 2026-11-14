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

In den Befehlen `SERVER_IP` durch die IPv4-Adresse des VPS ersetzen. Ob ein Schritt erledigt ist oder noch auf
Freigabe wartet, steht in `projektstand.md`.

**Nummerierung:** Bis 2026-11-13 hießen die Schritte B1–B8, C und D. Weil der Admin-Plan dieselben Buchstaben nutzt,
heißen sie jetzt S1–S10 (B1–B8 → S1–S8, C → S9, D → S10). In älteren Commits, im `verlauf.md` und im Text der
Token-Erinnerungen in der crontab des Servers stehen noch die alten Namen.

### S1. Benutzer und Verzeichnisse (auf dem Server, als mike)

```bash
sudo useradd --create-home --shell /bin/sh deploy
sudo usermod -aG docker deploy

sudo install -d -o root   -g deploy -m 750 /srv/sonjaczak
sudo install -d -o deploy -g deploy -m 750 /srv/sonjaczak/release
```

`--shell /bin/sh` ist nötig: Auch der erzwungene Befehl wird über die Login-Shell gestartet.

### S2. Deploy-Skript (vom Mac aus kopieren, dann auf dem Server installieren)

```bash
# Mac, im Projektordner
scp deploy/deploy.sh mike@SERVER_IP:/tmp/deploy.sh

# Server
sudo install -o root -g root -m 755 /tmp/deploy.sh /srv/sonjaczak/deploy.sh && rm /tmp/deploy.sh
```

Das Skript gehört root und ist für `deploy` nicht schreibbar. Ändert sich `deploy/deploy.sh` im Repo, diesen Schritt
wiederholen.

### S3. Secrets (auf dem Server)

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
MAILER_DSN=smtps://anfrage%40sonjaczak.com:<passwort, URL-kodiert>@smtp.ionos.de:465
```

`MAILER_DSN` (seit Schritt 6a): Zugang des IONOS-Postfachs `anfrage@sonjaczak.com`, Server und Port laut IONOS-Hilfe
„Serverinformationen für IMAP, POP3 und SMTP“. Benutzername und Passwort müssen URL-kodiert sein (`@` → `%40`,
Symfony-Mailer-Doku). Ein Passwort ohne Sonderzeichen bzw. nur mit `-` und `_` erspart das Kodieren. Ein `$` darf
nicht roh stehen, weil Compose es als Variable liest (kodiert `%24` ist unproblematisch). Ohne die Zeile bricht der
Deploy ab (`compose.prod.yaml`: Pflichtvariable). Testversand am Server:

```bash
sudo docker exec sonjaczak-php-1 bin/console mailer:test music@sonjaczak.com --from anfrage@sonjaczak.com
```

**Fallstrick:** `POSTGRES_PASSWORD` wird nur beim allerersten Start (leeres Volume) in die Datenbank übernommen.
Später den Wert in `.env` zu ändern, ändert das Passwort in der Datenbank nicht.

Die Werte zusätzlich im Passwort-Manager ablegen.

### S4. Zugriff auf ghcr.io

1. Auf GitHub: Settings → Developer settings → Personal access tokens → **Tokens (classic)** → Generate new token.
   Name z. B. `sonjaczak-server-pull`, Ablaufdatum setzen (Erinnerung eintragen!), **nur** Scope `read:packages`.
2. Auf dem Server als `deploy` anmelden:

```bash
sudo -v      # zuerst das sudo-Passwort von mike eingeben, damit der folgende Befehl nicht danach fragt
sudo -u deploy -H docker login ghcr.io -u MikeCzak --password-stdin
# jetzt den Token einfügen, Enter, dann Strg+D → erwartet: "Login Succeeded"
```

`-H` setzt `HOME` auf das Home-Verzeichnis von `deploy` (`man sudo`), damit Docker die Zugangsdaten dort ablegt und
nicht in mikes Home.

Docker speichert den Token in `/home/deploy/.docker/config.json` (unverschlüsselt, nur für deploy lesbar).
Läuft der Token ab, schlägt der Deploy beim `docker pull` fehl → neuen Token anlegen, Schritt wiederholen und die
Erinnerung (S6) auf das neue Ablaufdatum setzen.

### S5. SSH-Key für GitHub Actions

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

### S6. Erinnerung an ablaufende Tokens (auf dem Server)

GitHub schickt selbst eine Mail, bevor ein Token abläuft (GitHub-Changelog „Expiration options for personal access
tokens“, 2021-07-26; wie viele Tage vorher, steht dort nicht). Zusätzlich schickt der Server eine Woche vorher eine
Mail über den Symfony-Mailer, wie beim Testversand in S3.

Tokens (Stand 2026-11-12):

| Token | Scopes | Wo | Läuft ab | Erinnerung |
|---|---|---|---|---|
| gh CLI | `admin:public_key`, `read:org`, `repo` | Mac | 2027-11-02 | 2027-10-26 |
| `sonjaczak-server-pull` | `read:packages` | Server (S4) | 2027-11-03 | 2027-10-27 |

```bash
sudo crontab -e      # crontab von root: docker läuft dort ohne sudo
```

Eintrag (`<adresse>` = wohin die Erinnerung gehen soll):

```
# Token reminders: cron has no year, so the job runs daily at 08:00 and compares the date itself. % must be escaped.
0 8 * * * [ "$(date +\%F)" = "2027-10-26" ] && docker exec sonjaczak-php-1 bin/console mailer:test <adresse> --from anfrage@sonjaczak.com --subject "gh-CLI-Token läuft am 2.11. ab" --body "Neuen Token anlegen: GitHub → Settings → Developer settings → Personal access tokens. Danach gh auth login und S6 anpassen."
0 8 * * * [ "$(date +\%F)" = "2027-10-27" ] && docker exec sonjaczak-php-1 bin/console mailer:test <adresse> --from anfrage@sonjaczak.com --subject "Server-Token (ghcr) läuft am 3.11. ab" --body "Neuen Token anlegen und docs/deployment.md S4 wiederholen, danach S6 anpassen."
```

Erklärung:

- `mailer:test` nimmt Empfänger, `--from`, `--subject` und `--body` (Optionen laut
  `vendor/symfony/mailer/Command/MailerTestCommand.php`). Der Absender muss `anfrage@sonjaczak.com` sein, weil IONOS
  nur Absender des eigenen Postfachs annimmt.
- In einer crontab beendet ein nicht escaptes `%` die Befehlszeile (`man 5 crontab`), daher `\%F`.
- cron würde die Ausgabe eines Jobs per Mail schicken; ohne lokales Mailprogramm verwirft es sie (Hinweis im Journal,
  `journalctl -u cron`). Deshalb den Eintrag einmal testen, indem man das Datum vorübergehend auf heute und die
  Uhrzeit auf die nächste Minute setzt.
- `crontab -l` zeigt die Einträge, nach dem Erneuern eines Tokens das Datum anpassen.

### S7. Admin-Konten anlegen (auf dem Server, einmalig nach dem Deploy von Admin-Plan B1)

Das erste Konto kann nicht über die App entstehen (`docs/planung/rollen.md` 4.1). Der Befehl fragt das Passwort
verdeckt und zweimal ab (mindestens 12 Zeichen, nicht zu leicht zu erraten); `-it` ist nötig, damit die verdeckte
Eingabe funktioniert:

```bash
docker exec -it sonjaczak-php-1 bin/console app:user:create <deine-adresse> --super-admin
docker exec -it sonjaczak-php-1 bin/console app:user:create music@sonjaczak.com
```

Das zweite Konto (Sonja, `ROLE_ADMIN`) kann stattdessen später in der Benutzerverwaltung entstehen
(Admin-Plan B4), dann setzt Sonja ihr Passwort selbst über den Einladungslink. Danach im Browser
`https://sonjaczak.com/admin` öffnen und anmelden. Hintergrund: `docs/erklaerungen/08-login-und-sessions.md`.

### S8. Erinnerung an überfällige Honorarnoten (auf dem Server)

Der Befehl `app:invoices:remind-overdue` (Admin-Plan D4) schickt Sonja eine Mail an `music@sonjaczak.com`
(`app.contact_email`) mit allen Honorarnoten, deren Frist seit dem letzten Lauf überschritten ist; je Frist nur
einmal. Vorschlag für den Eintrag in der crontab von root (wie S6), täglich um 07:30:

```
# Overdue invoices: one mail to Sonja with all newly overdue invoices (docs/erklaerungen/13-honorarnoten.md)
30 7 * * * docker exec sonjaczak-php-1 bin/console app:invoices:remind-overdue --no-interaction > /dev/null
```

- Vorher einmal von Hand ohne Wirkung prüfen: `docker exec sonjaczak-php-1 bin/console app:invoices:remind-overdue --dry-run`
  (zeigt nur die Anzahl, verschickt und ändert nichts).
- Die Ausgabe nennt nur die Anzahl, keine Namen. `> /dev/null` verwirft die Erfolgsmeldung; Fehler (stderr) bleiben
  sichtbar im Journal (`journalctl -u cron`).
- Die Links in der Mail entstehen aus `DEFAULT_URI` (`compose.yaml`: `https://${SERVER_NAME}:443`); lokal mit diesem
  Wert geprüft, der Port fällt im Link weg (`https://sonjaczak.com/admin/honorarnoten/2`).
- cron nimmt die Zeitzone des Servers; vor dem Eintragen mit `timedatectl` nachsehen (bei UTC ist 07:30 in Wien 08:30
  bzw. 09:30). Ob etwas überfällig ist, rechnet PHP ohnehin in `Europe/Vienna`.

### S9. GitHub-Environment und Secrets (Mac, mit gh)

```bash
gh api -X PUT repos/MikeCzak/sonjaczak/environments/production >/dev/null

gh secret set SSH_PRIVATE_KEY --env production < ~/.ssh/sonjaczak_deploy
gh secret set SSH_HOST        --env production --body "SERVER_IP"

# Host-Key des Servers festnageln. Fingerabdruck vergleichen mit: (Server) ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
ssh-keyscan -t ed25519 SERVER_IP 2>/dev/null | tee /dev/stderr | ssh-keygen -lf -
ssh-keyscan -t ed25519 SERVER_IP 2>/dev/null | gh secret set SSH_KNOWN_HOSTS --env production
```

Optional danach den privaten Key vom Mac löschen (`rm ~/.ssh/sonjaczak_deploy`) – er liegt dann nur noch bei
GitHub. Bei Bedarf einfach ein neues Paar erzeugen und S5 und S9 wiederholen.

Im Browser lässt sich unter Repo → Settings → Environments → production zusätzlich „Deployment branches“ auf
`main` beschränken.

### S10. DNS bei IONOS

Bei der Domain `sonjaczak.com` im IONOS-DNS:

- **A-Record** für `@` → IPv4 des VPS (ersetzt den bisherigen Eintrag `217.160.0.99`)
- **AAAA-Record** für `@` → IPv6 des VPS (ersetzt `2001:8d8:100f:f000::200`). Hat der VPS kein IPv6 (unser Fall),
  den alten AAAA-Record **löschen** – sonst landen IPv6-Besucher und ggf. Let's Encrypt beim alten IONOS-Webspace.
- **MX-Records (`mx00/mx01.ionos.de`) und SPF-TXT nicht anfassen** – die sind für Mail.
- **A-Record** für `www` → IPv4 des VPS (seit 2026-11-12). Caddy holt dafür ein eigenes Zertifikat und leitet
  dauerhaft (301) auf die Adresse ohne `www` um, mit Pfad und Query (`CADDY_EXTRA_CONFIG` in `compose.prod.yaml`).

Prüfen (Mac), bis die neuen Adressen erscheinen:

```bash
dig +short sonjaczak.com A
dig +short sonjaczak.com AAAA
```

Caddy holt das Zertifikat beim Start selbst (Let's Encrypt, Ersatz ZeroSSL; Quelle: Caddy-Doku *Automatic HTTPS*).
Voraussetzung: DNS zeigt auf den Server, Ports 80/443 offen. Ein gekauftes Zertifikat ist nicht nötig.

## Erster Deploy

Erst wenn S1–S5, S9 und S10 erledigt sind und `dig` die VPS-Adressen zeigt: pushen und den Lauf beobachten
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
