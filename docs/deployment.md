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

**Nummerierung:** Bis 2026-11-13 hießen die Schritte B1–B8, C und D. S11 (Backups) kam 2026-11-14 dazu. Weil `umsetzung-admin.md` dieselben Buchstaben nutzt,
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

### S7. Admin-Konten anlegen (auf dem Server, einmalig nach dem Deploy von `umsetzung-admin.md` B1)

Das erste Konto kann nicht über die App entstehen (`docs/planung/rollen.md` 4.1). Der Befehl fragt das Passwort
verdeckt und zweimal ab (mindestens 12 Zeichen, nicht zu leicht zu erraten); `-it` ist nötig, damit die verdeckte
Eingabe funktioniert:

```bash
docker exec -it sonjaczak-php-1 bin/console app:user:create <deine-adresse> --super-admin
docker exec -it sonjaczak-php-1 bin/console app:user:create music@sonjaczak.com
```

Das zweite Konto (Sonja, `ROLE_ADMIN`) kann stattdessen später in der Benutzerverwaltung entstehen
(`umsetzung-admin.md` B4), dann setzt Sonja ihr Passwort selbst über den Einladungslink. Danach im Browser
`https://sonjaczak.com/admin` öffnen und anmelden. Hintergrund: `docs/erklaerungen/08-login-und-sessions.md`.

### S8. Erinnerung an überfällige Honorarnoten (auf dem Server)

Der Befehl `app:invoices:remind-overdue` (`umsetzung-admin.md` D4) schickt Sonja eine Mail an `music@sonjaczak.com`
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
- cron nimmt die Zeitzone des Servers (bei UTC ist 07:30 in Wien 08:30 bzw. 09:30). Ob etwas überfällig ist,
  rechnet PHP ohnehin in `Europe/Vienna`. Eine Prüfung mit `timedatectl` ist deshalb nicht nötig (Entscheidung
  2026-11-14: eine Stunde früher oder später ist egal).

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

### S11. Backups der Datenbank (auf dem Server, als mike)

Plan und Begründungen: `docs/planung/backups.md`. Vorher am Mac die Schritte 1–2 aus `docs/entwicklung.md`,
„Backups“ (age-Schlüssel und SSH-Key erzeugen).

```bash
# Mac, im Projektordner: Skripte und öffentlichen SSH-Key hochladen
scp deploy/backup.sh deploy/restore-test.sh ~/.ssh/sonjaczak_backup.pub mike@SERVER_IP:/tmp/

# Server
sudo apt install age
age --version                                                   # erwartet: v1.1.1 (Ubuntu 24.04)

sudo install -o root -g root -m 755 /tmp/backup.sh /usr/local/sbin/sonjaczak-backup
sudo install -o root -g root -m 755 /tmp/restore-test.sh /usr/local/sbin/sonjaczak-restore-test
rm /tmp/backup.sh /tmp/restore-test.sh

# Öffentlicher age-Schlüssel (beginnt mit age1…, nicht geheim)
echo 'age1…' | sudo tee /srv/sonjaczak/backup-recipient.txt

# Benutzer ohne Docker-Rechte; '*' statt gesperrtem Passwort, sonst lehnt sshd den Key ab (siehe S5)
sudo useradd --create-home --shell /bin/sh dbbackup
sudo usermod -p '*' dbbackup
sudo install -d -o dbbackup -g dbbackup -m 700 /home/dbbackup/.ssh
echo "restrict,command=\"sudo /usr/local/sbin/sonjaczak-backup\" $(cat /tmp/sonjaczak_backup.pub)" \
  | sudo tee /home/dbbackup/.ssh/authorized_keys >/dev/null
sudo chown dbbackup:dbbackup /home/dbbackup/.ssh/authorized_keys
sudo chmod 600 /home/dbbackup/.ssh/authorized_keys
rm /tmp/sonjaczak_backup.pub

# sudo-Regeln: je genau ein Skript, ohne Argumente ("") und ohne Passwort
sudo visudo -f /etc/sudoers.d/sonjaczak-backup
```

Inhalt von `/etc/sudoers.d/sonjaczak-backup`:

```
dbbackup ALL=(root) NOPASSWD: /usr/local/sbin/sonjaczak-backup ""
mike ALL=(root) NOPASSWD: /usr/local/sbin/sonjaczak-restore-test ""
```

- `visudo -f` prüft die Syntax vor dem Speichern. Ein Fehler in einer sudoers-Datei kann sudo ganz sperren.
- **Fallstrick nano:** visudo öffnet eine Kopie `sonjaczak-backup.tmp`. Beim Speichern den Dateinamen **nicht** ändern
  (Strg+O, Enter, Strg+X). visudo prüft danach die `.tmp`-Datei und legt sie selbst unter dem richtigen Namen ab.
  Speichert man direkt unter dem Zielnamen, meldet visudo „unchanged“ und die Prüfung entfällt.
- Die zweite Zeile braucht der Test der Rücksicherung: Dort ist die Eingabe der Datenstrom, `sudo` könnte nicht nach
  dem Passwort fragen.
- `/usr/local/sbin` statt `/srv/sonjaczak`, weil `/srv/sonjaczak` nur für root und `deploy` lesbar ist.

Test vom Mac (erwartet: `age-encryption.org/v1`):

```bash
ssh -T -i ~/.ssh/sonjaczak_backup dbbackup@sonjaczak.com | head -c 21; echo
```

Beim ersten Mal fragt ssh nach dem Host-Key. Fingerabdruck vergleichen wie in S9
(`ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub` am Server). Danach weiter in `docs/entwicklung.md`, „Backups“.

**Test der Rücksicherung** (vom Mac; der private Schlüssel kommt aus dem Passwort-Manager in die Zwischenablage):

```bash
age -d -i <(pbpaste) ~/Backups/sonjaczak/$(date +%F).dump.age \
  | ssh mike@sonjaczak.com sudo /usr/local/sbin/sonjaczak-restore-test
pbcopy < /dev/null      # Zwischenablage leeren
```

Erwartet: je Tabelle „live / restored“ mit `ok`; `sessions` und `reset_password_request` sind absichtlich leer. Am
Ende wird `app_restore_test` wieder gelöscht. Lokal mit dem Dev-Stack geprüft (2026-11-14).

**Skripte aktualisieren:** Ändern sich `deploy/backup.sh` oder `deploy/restore-test.sh`, die `scp`- und
`install`-Zeilen wiederholen.

### S12. Automatische Löschung (auf dem Server)

Der Befehl `app:privacy:purge` (`umsetzung-admin.md` F3) löscht Personendaten und Honorarnoten, deren Frist abgelaufen
ist (Fristen: `/datenschutz/unterricht`, `App\Privacy\RetentionPolicy`). Eintrag in der crontab von root (wie S6, S8),
täglich um 03:30 (Mike, 2026-11-17):

```
# Retention: deletes personal data and invoices past their period (docs/planung/umsetzung-admin.md F3)
30 3 * * * docker exec sonjaczak-php-1 bin/console app:privacy:purge --no-interaction > /dev/null
```

Reihenfolge:

1. Erst pushen und den Deploy abwarten (`gh run watch`): Der Befehl und die Migrationen aus F3a/F3b müssen in
   Produktion sein. Prüfen: `docker exec sonjaczak-php-1 bin/console list app` zeigt `app:privacy:purge`.
2. Einmal ohne Wirkung: `docker exec sonjaczak-php-1 bin/console app:privacy:purge --dry-run` (zählt nur; vor den
   echten Daten erwartet: überall 0).
3. `sudo crontab -e`, Zeile ergänzen, mit `sudo crontab -l` kontrollieren.

Hinweise:

- **Uhrzeit:** cron nimmt die Zeitzone des Servers, ebenso der Neustart nach Updates um 04:00 (Server-Konfiguration
  im Projektstand). 03:30 liegt also immer eine halbe Stunde davor; ein Lauf dauert bei wenigen Schüler:innen
  Sekunden. Welcher Tag „heute“ ist, rechnet PHP in `Europe/Vienna` (`frankenphp/conf.d/10-app.ini`); um 03:30 UTC
  ist in Wien schon derselbe Tag. Das Backup vom Mac (12:00) liegt weit weg.
- **Ausgabe:** nur Anzahlen, keine Namen. `> /dev/null` verwirft die Erfolgsmeldung; Fehler (stderr) stehen im
  Journal (`journalctl -u cron`). Schlägt ein Lauf fehl, ist nichts gelöscht (eine Transaktion), der nächste Tag
  holt es nach.
- **Abschalten** (z. B. bei einem laufenden Verfahren beim Finanzamt, `umsetzung-admin.md` F3 Frage 5): Zeile mit
  `#` auskommentieren.
- **Backups:** Gelöschtes bleibt in den Sicherungen bis zu 13 Monate (Monatskopie bis 366 Tage, danach bis 10 Tage in
  den Snapshots der Storage Box, S13; so in der Datenschutz-Information).
- **Rollback:** Gibt es schon Teilnahmen ohne Person, kann eine Version vor F3b sie nicht anzeigen
  (`enrollment.student` wäre leer). Vor einem Rollback hinter F3b den Cronjob auskommentieren und prüfen.

### S13. Backups in die Hetzner Storage Box (Hetzner Console, Server, Mac)

Plan und Begründungen: `docs/planung/backups-ohne-mac.md` (Option A). Setzt S11 voraus: `sonjaczak-backup` und
`/srv/sonjaczak/backup-recipient.txt` bleiben, das neue Skript ruft sie auf. Hetzner-Doku gelesen 2026-11-18
(docs.hetzner.com/storage/storage-box/general, …/access/access-ssh-rsync-borg, …/ssh-keys/add-ssh-keys,
…/snapshots).

Was wir benutzen: nur **SFTP auf Port 22**. Der Port erlaubt laut Hetzner „only SCP and SFTP, no interactive access“
und ist „always active and cannot be disabled“. Port 22 verlangt den öffentlichen Key im **RFC4716-Format** (Tabelle
auf der general-Seite).

- **External Reachability: an.** „To connect from outside the Hetzner network … you have to enable External
  Reachability“; der Server steht bei IONOS, der Mac zu Hause. Unterkonten haben einen eigenen Schalter (in der
  Console gesehen, 2026-11-19): bei beiden an.
- **SSH support: aus.** „The option 'SSH Support' only enables port 23“ (eingeschränkte Shell, rsync, Borg). Der
  Hinweis in „Storage Box SSH Keys“, SSH support einzuschalten, gilt für das Hochladen des Keys „via SSH“ (Optionen 1
  und 2, Port 23); wir nehmen Option 3 über Port 22. Klappt die nicht (gegen eine echte Box ungetestet): SSH support
  vorübergehend an, Key mit Option 1 (`install-ssh-key`) hinterlegen, wieder aus.

**1. Hetzner Console** (Sonja bestellt, Mike richtet ein, `backups-ohne-mac.md` Frage 2):

1. BX11 in Falkenstein bestellen, AVV unter accounts.hetzner.com/account/dpa abschließen.
2. Beim Anlegen nur ein **Passwort** für das Hauptkonto (lang, zufällig, in den Passwort-Manager), **keinen SSH-Key**.
   Das Hauptkonto sieht alle Verzeichnisse, auch die der Unterkonten („complete access to all directories“); der
   Server-Key darf deshalb nie dorthin. Ein Key beim Anlegen schaltet das Passwort ohnehin nicht ab („it does not
   disable password authentication“), und später lässt er sich in der Console nicht mehr ergänzen (beides
   `getting-started/creating-a-storage-box`, gelesen 2026-11-19). Das Hauptkonto wird für nichts automatisiert benutzt.
3. Unterkonto **Upload** anlegen, **Basisverzeichnis `backups`**, kein Unterverzeichnis (das Skript legt die Dateien
   direkt dort ab), Schreibrecht. Einen Namen vergibt man nicht: Hetzner nummeriert `uXXXXX-sub1`, `-sub2`, …; Upload
   zuerst anlegen, dann ist es `-sub1`. Benutzername und Host aus der Console abschreiben (laut Doku Host
   `uXXXXX-subX.your-storagebox.de`), Zuordnung im Passwort-Manager notieren. External Reachability an, weitere
   Protokolle (SSH support, Samba, WebDAV) aus. Vorübergehendes Passwort für **dieses Unterkonto**
   vergeben (nur für das Hinterlegen des Keys in Teil 2). Wie das Formular für Unterkonten aussieht, beschreibt die
   Doku nicht; bietet es ein Feld für einen SSH-Key, Anleitung anpassen.
4. Unterkonto **Lesen** für den Mac, **dasselbe Basisverzeichnis `backups`, read-only** („Then the sub-account users cannot upload
   or delete files“).
5. Snapshots: automatisch **täglich 13:00**, 10 Plätze (nach dem Hochladen um 12:00).

**2. Server: Key, Fingerabdruck, Skript** (als mike):

```bash
# Mac, im Projektordner
scp deploy/backup-push.sh mike@SERVER_IP:/tmp/

# Server: Werte des Upload-Unterkontos aus der Console einsetzen (gelten nur in dieser Shell-Sitzung)
UPLOAD_USER=u123456-sub1
UPLOAD_HOST=u123456-sub1.your-storagebox.de

sudo install -o root -g root -m 755 /tmp/backup-push.sh /usr/local/sbin/sonjaczak-backup-push
rm /tmp/backup-push.sh

# Schlüsselpaar nur für die Storage Box, ohne Passphrase (cron kann keine eingeben). Privat: storagebox_ed25519,
# öffentlich: storagebox_ed25519.pub (OpenSSH-Format, damit meldet sich sftp an)
sudo ssh-keygen -t ed25519 -N "" -C "sonjaczak-storagebox-upload" -f /root/.ssh/storagebox_ed25519
# Kein neuer Key: derselbe öffentliche Teil im RFC4716-Format, das Port 22 der Storage Box verlangt. Nur zum Hochladen.
sudo sh -c 'ssh-keygen -e -f /root/.ssh/storagebox_ed25519.pub > /root/.ssh/storagebox_ed25519_rfc.pub'

# Host-Key der Storage Box abfragen, Fingerabdruck berechnen und MIT DEM AUGE VERGLEICHEN.
# ssh-keyscan glaubt jedem, der antwortet; erst der Vergleich mit dem veröffentlichten Wert macht es sicher.
# Jeder Host-Server hat laut Hetzner nur EINEN der veröffentlichten Keys („only have one of the fingerprints listed
# below“). Unsere Box (u724152) hat nur RSA (2026-11-19, „SSH-2.0-mod_sftp“); mit -t ed25519 bliebe die Datei leer
# und ssh-keygen meldet „not a public key file“. Bei einer anderen Box erst ohne -t nachsehen: ssh-keyscan "$UPLOAD_HOST"
ssh-keyscan -t rsa "$UPLOAD_HOST" 2>/dev/null > /tmp/sb_hostkey
ssh-keygen -lf /tmp/sb_hostkey
# erwartet laut general-Seite: 2048 SHA256:EMlfI8GsRIfpVkoW1H2u0zYVpFGKkIMKHFZIRkf2ioI … (RSA)
# (die anderen veröffentlichten: ED25519 SHA256:XqONwb1S0zuj5A1CDxpOSuD2hnAArV1A3wKY7Z3sdgM,
#  ECDSA SHA256:oDHZqKXnoMtgvPBjjC57pcuFez28roaEuFcfwyg8O5c)
# Nur wenn er stimmt: speichern. Ab dann verweigern sftp und das Skript jede Box mit anderem Host-Key.
sudo install -o root -g root -m 600 /tmp/sb_hostkey /root/.ssh/known_hosts_storagebox && rm /tmp/sb_hostkey

# Key beim Upload-Unterkonto hinterlegen (Option 3 der Hetzner-Doku, Port 22), interaktiv, fragt einmal nach dem
# Passwort des Unterkontos. NICHT als `echo "mkdir .ssh" | sudo sftp …`: Pipe + sudo + Passwortabfrage endete am
# 2026-11-19 mit „Killed“ (kein OOM, Ursache nicht geklärt; vermutlich sudo mit use_pty, nicht nachgelesen).
sudo sftp -o UserKnownHostsFile=/root/.ssh/known_hosts_storagebox "$UPLOAD_USER@$UPLOAD_HOST"
#   sftp> mkdir .ssh
#   sftp> put /root/.ssh/storagebox_ed25519_rfc.pub .ssh/authorized_keys
#   sftp> ls -l .ssh
#   sftp> bye

# Test mit Key statt Passwort (darf nicht mehr nach dem Passwort fragen): listet .ssh
echo "ls -a" | sudo sftp -b - -i /root/.ssh/storagebox_ed25519 -o BatchMode=yes \
  -o UserKnownHostsFile=/root/.ssh/known_hosts_storagebox "$UPLOAD_USER@$UPLOAD_HOST"

# Einstellungen, nur root lesbar (MAIL_TO = Mikes Adresse, nicht ins Repo)
sudo install -o root -g root -m 600 /dev/null /srv/sonjaczak/backup-push.env
sudo nano /srv/sonjaczak/backup-push.env
```

Inhalt von `/srv/sonjaczak/backup-push.env`:

```
STORAGE_USER=uXXXXX-subX
STORAGE_HOST=uXXXXX-subX.your-storagebox.de
MAIL_TO=<adresse>
```

Danach in der Console das vorübergehende Passwort des Upload-Unterkontos durch ein langes zufälliges ersetzen
(Passwort-Manager); ab jetzt nur noch der Key.

**3. Erster Lauf von Hand, dann Cronjob:**

```bash
sudo /usr/local/sbin/sonjaczak-backup-push            # erwartet: "Uploaded JJJJ-MM-TT.dump.age (… bytes)"
sudo /usr/local/sbin/sonjaczak-backup-push status     # erwartet: Mail „Sicherungen vorhanden: neueste vom …“
sudo crontab -e
```

```
# Backup to the Hetzner Storage Box (docs/deployment.md S13); errors go out by mail, details in journalctl -u cron
0 12 * * * /usr/local/sbin/sonjaczak-backup-push > /dev/null
# Monthly status mail: newest backup and count, so a cron job that never runs is noticed
0 13 1 * * /usr/local/sbin/sonjaczak-backup-push status
```

Erwartete Zeile im Journal beim ersten Lauf eines Tages: `remote delete …: No such file or directory` (das Skript
ersetzt eine ältere Datei desselben Tages, beim ersten Lauf gibt es keine).

**Was das Skript tut** (`deploy/backup-push.sh`): ruft `sonjaczak-backup` auf, schreibt in ein temporäres Verzeichnis
unter `/var/tmp` (verschlüsselt, wird immer gelöscht), prüft Exit-Code und age-Kopfzeile, lädt unter einem
temporären Namen hoch und benennt um, vergleicht die Größe, rotiert danach wie `bin/backup-pull` (14 Tage, 1. des
Monats 12 Monate). Jeder Fehler schickt eine Mail über `mailer:test` (wie S6). Fällt der PHP-Container aus, geht auch
die Mail nicht; dann fällt es spätestens bei der Monatsmail auf. Lokal getestet gegen einen SFTP-Container
(2026-11-18, `verlauf.md`).

**4. Mac: Lesen und Rücksicherung** (Lese-Unterkonto **nur mit Passwort, ohne Key**)

Beide Unterkonten haben dasselbe Basisverzeichnis `backups` und damit **dieselbe** `.ssh/authorized_keys` (dort liegt
der Server-Key). Ein Mac-Key darin gälte auch für das Upload-Unterkonto, mit Schreibrecht. Der Mac liest nur von Hand,
also reicht das Passwort aus dem Passwort-Manager.

Beim ersten Verbinden fragt ssh nach dem Host-Key: Fingerabdruck mit dem RSA-Wert oben vergleichen.

Rücksicherung testen (privater age-Schlüssel aus dem Passwort-Manager in der Zwischenablage; sftp fragt nach dem
Passwort des Lese-Unterkontos, das also erst danach in die Zwischenablage):

**Alles am Mac** (Prompt `…@mikebookair`), nie in einer SSH-Sitzung am Server: Der private age-Schlüssel darf nie auf
den Server, sonst kann, wer den Server übernimmt, alle Sicherungen lesen.

```bash
sftp READ_USER@READ_HOST:$(date +%F).dump.age /tmp/sb.dump.age
ls -l /tmp/sb.dump.age
# jetzt den age-Schlüssel aus dem Passwort-Manager kopieren
age -d -i <(pbpaste) /tmp/sb.dump.age | ssh mike@sonjaczak.com sudo /usr/local/sbin/sonjaczak-restore-test
```

Erst nach der Tabelle mit `ok` aufräumen (nicht in einem Zug mit dem Test, sonst ist die Datei bei einem Fehler weg):

```bash
rm /tmp/sb.dump.age; pbcopy < /dev/null
```

**5. Test gegen Löschen** (`backups-ohne-mac.md`, Umsetzung 5; erst nach dem ersten Snapshot):

```bash
sudo sftp -i /root/.ssh/storagebox_ed25519 -o UserKnownHostsFile=/root/.ssh/known_hosts_storagebox UPLOAD_USER@UPLOAD_HOST
# sftp> rm JJJJ-MM-TT.dump.age        → soll klappen (danach Skript erneut laufen lassen)
# sftp> ls /.zfs/snapshot  bzw.  ls .zfs/snapshot
# sftp> rmdir / rm in einem Snapshot  → muss scheitern
```

Erwartet: Das Upload-Unterkonto kann keine Snapshots löschen; die gelöschte Datei liegt noch im Snapshot (mit dem
Lese-Unterkonto oder in der Console prüfen). Sieht das Unterkonto `.zfs` gar nicht, in der Console nachsehen, ob der
Snapshot die Datei enthält. Lässt sich ein Snapshot löschen: zurück zu Option B.

**Skript aktualisieren:** Ändert sich `deploy/backup-push.sh`, die `scp`- und `install`-Zeile wiederholen.

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
