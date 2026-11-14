# Backups der Datenbank (Entwurf)

Stand: 2026-11-14, **Entwurf zur Freigabe**. Teil von `umsetzung-admin.md` F. Echte Daten erst, wenn das hier läuft
und eine Rücksicherung einmal geklappt hat. Offene Entscheidungen: Abschnitt „Zu entscheiden“; die Frage dazu steht
im Projektstand unter „Jetzt“.

## Was gesichert werden muss

- **Nur die Datenbank.** Alles, was Sonja einträgt, liegt dort: Schüler:innen, Kurse, Honorarnoten (später auch die
  PDFs, `umsetzung-admin.md` D2), Zahlungen, Einstellungen mit IBAN. Hochgeladene Dateien gibt es nicht.
- **Nicht nötig:** das Image (liegt in ghcr.io, jeder Commit lässt sich neu deployen), die Zertifikate (Caddy holt sie
  neu), die Secrets in `/srv/sonjaczak/.env` (liegen im Passwort-Manager, `deployment.md` S3).
- **Ohne Inhalt gesichert** werden `sessions` (Anmeldungen) und `reset_password_request` (Links für „Passwort
  vergessen“): Nach einer Rücksicherung meldet man sich einfach neu an, und Anmeldedaten gehören nicht in Kopien. Die
  Tabellen selbst bleiben im Dump (`pg_dump --exclude-table-data`: „useful when you need the definition of a
  particular table even though you do not need the data in it“, postgresql.org/docs/18/app-pgdump.html).

## Ablauf

```
Mac (launchd, täglich)                         Server
ssh -i ~/.ssh/sonjaczak_backup backup@…  ──►   erzwungener Befehl: sudo /srv/sonjaczak/backup.sh
                                                  docker exec sonjaczak-database-1 pg_dump … -Fc
                                                  | age -r <öffentlicher Schlüssel>   (verschlüsselt)
~/Backups/sonjaczak/2026-11-14.dump.age   ◄──    Ausgabe über die SSH-Verbindung
```

1. **Der Mac holt, der Server schickt nichts von sich aus.** Der Server braucht so keinen Zugang zum Mac, und ein
   Angreifer auf dem Server kommt nicht an die Sicherungen.
2. **Verschlüsselt wird schon am Server**, mit dem **öffentlichen** Schlüssel von age. Den privaten Schlüssel, der
   allein entschlüsseln kann, kennt der Server nie. Der Dump liegt nirgends unverschlüsselt auf einer Platte: Er
   fließt direkt von `pg_dump` in `age` und von dort durch die SSH-Verbindung in eine Datei am Mac.
3. **Format:** `pg_dump -Fc` („custom-format archive suitable for input into pg_restore“, „compressed by default“,
   selbe Quelle). `pg_dump` läuft im Datenbank-Container, hat also dieselbe Version wie die Datenbank (lokal geprüft:
   `pg_dump (PostgreSQL) 18.6`).

### Server

- **Eigener Benutzer `backup`**, nicht in der Gruppe `docker` (die ist faktisch root, `deployment.md` „Ablauf“).
- **`/srv/sonjaczak/backup.sh`**, gehört root, im Repo unter `deploy/backup.sh` (wie `deploy.sh`). Nimmt keine
  Argumente, schreibt nur den verschlüsselten Dump auf die Standardausgabe. Mit `bash` und `set -o pipefail`, damit
  ein Fehler von `pg_dump` den ganzen Befehl scheitern lässt (sonst käme eine gültige, aber leere verschlüsselte Datei
  heraus).
- **sudo nur für dieses Skript:** `backup ALL=(root) NOPASSWD: /srv/sonjaczak/backup.sh` in `/etc/sudoers.d/`. Der
  Benutzer darf also genau einen Befehl als root ausführen, ohne Docker-Rechte.
- **SSH-Key nur für diesen Befehl:** `restrict,command="sudo /srv/sonjaczak/backup.sh"` in `authorized_keys`, wie beim
  Deploy-Key (`deployment.md` S5). Wer den Schlüssel stiehlt, bekommt verschlüsselte Dumps, sonst nichts.
- **age am Server:** Paket `age` aus Ubuntu (24.04 „noble“, Bereich universe, Version 1.1.1, laut
  packages.ubuntu.com/noble/age mit Sicherheitsupdates). Der öffentliche Schlüssel liegt in
  `/srv/sonjaczak/backup-recipient.txt` (nicht geheim).

### Mac

- **age** über Homebrew (`brew info age`: stable 1.3.2; aktuellstes Release auf GitHub ebenfalls v1.3.2). Dass die
  ältere Version am Server und die neuere am Mac zusammenpassen, zeigt der Test der Rücksicherung.
- **Schlüsselpaar** einmalig mit `age-keygen`. Der **private Schlüssel** kommt in den Passwort-Manager (und nach Wunsch
  ausgedruckt an einen sicheren Ort), **nicht** als Datei auf den Mac. Grund: Lägen Sicherungen und Schlüssel auf
  derselben Platte, schützte die Verschlüsselung die Kopien (Time Machine, alte Platte) nicht mehr.
- **Skript `bin/backup-pull`** (im Repo, ohne Geheimnisse, wie `bin/build-images`): holt den Dump in eine temporäre
  Datei, prüft, dass sie mit der Kopfzeile von age beginnt und nicht leer ist, benennt sie dann nach dem Datum um und
  löscht alte Sicherungen nach der Aufbewahrungsregel. Bei einem Fehler: macOS-Mitteilung (`osascript … display
  notification`) und Eintrag im Log.
- **launchd** statt cron: Ein `LaunchAgent` startet das Skript täglich. Laut `man launchd.plist`: „Unlike cron which
  skips job invocations when the computer is asleep, launchd will start the job the next time the computer wakes up.“
  Mehrere verpasste Termine werden zu einem zusammengefasst. Ist der Mac ausgeschaltet, gibt es keine Sicherung;
  deshalb meldet das Skript auch, wenn die neueste Sicherung älter als 3 Tage ist.
- **Ablage:** `~/Backups/sonjaczak/`, **nicht** im Projektordner (der liegt auf dem Schreibtisch, und der wird bei
  manchen Macs mit iCloud synchronisiert). Time Machine sichert den Ordner mit, die Dateien sind ja verschlüsselt.

### Aufbewahrung

Vorschlag: die Sicherungen der letzten **14 Tage** und je Monat die vom **1.** für **12 Monate**. Ein Dump ist bei
sechs Schüler:innen winzig, Platz spielt keine Rolle.

**Zum Abwägen (DSGVO):** Was in der App gelöscht wird, steckt noch so lange in den Sicherungen, wie die aufbewahrt
werden, hier also bis zu 12 Monate. Das gehört in die Datenschutz-Information (`umsetzung-admin.md` F). Für die
Aufbewahrungsfrist nach § 132 BAO (7 Jahre) braucht es keine alten Sicherungen: Die Honorarnoten bleiben ja in der
Datenbank, und jede neue Sicherung enthält sie.

## Rücksicherung

**Test (einmal vor den echten Daten, danach z. B. jährlich):** in eine **temporäre Datenbank am Server**, damit
echte Daten nicht auf dem Mac landen und nirgends unverschlüsselt auf einer Platte liegen. Der Mac entschlüsselt (er
allein hat den privaten Schlüssel) und schickt den Datenstrom per SSH direkt an `pg_restore`:

```bash
# Mac (Prinzip, noch nicht getestet)
age -d -i <schlüsseldatei> ~/Backups/sonjaczak/2026-11-14.dump.age \
  | ssh mike@SERVER_IP 'sudo docker exec -i sonjaczak-database-1 pg_restore -U app -d app_restore_test'
```

Offen für die Ausarbeitung: `sudo` fragt am Server nach mikes Passwort, kann das aber nicht, solange die Eingabe für
die Daten gebraucht wird. Das löse ich in S11 (z. B. vorher in derselben Verbindung `sudo -v`).

Davor die leere Datenbank `app_restore_test` anlegen, danach Zeilen zählen und mit `app` vergleichen, dann
`DROP DATABASE app_restore_test`. Die genauen Befehle kommen in `deployment.md` (neuer Schritt S11), sobald der Plan
freigegeben ist. Wie der private Schlüssel kurzzeitig als Datei bereitsteht (und danach gelöscht wird), regle ich dort.

**Ernstfall** (Server weg): neuen Server nach `deployment.md` einrichten, deployen (legt die leere Datenbank mit
Migrationen an), dann dieselbe Pipe mit `pg_restore --clean -d app`. Auch das wird beim Test einmal durchgespielt,
nur in die Testdatenbank.

## Zu entscheiden

| # | Frage | Empfehlung | Alternative |
|---|---|---|---|
| 1 | Verschlüsselung | **age**: ein Befehl, Schlüssel ist eine Zeile, kein Schlüsselbund | GPG: verbreiteter, aber viel mehr Optionen und Fallstricke; am Mac nicht vorinstalliert |
| 2 | Wo verschlüsseln? | **Am Server** mit dem öffentlichen Schlüssel (dafür `apt install age` am Server) | Am Mac: nichts zu installieren am Server, aber der Dump kommt unverschlüsselt am Mac an |
| 3 | Privater Schlüssel | **Passwort-Manager**, zusätzlich ausgedruckt an einem sicheren Ort | Als Datei am Mac: bequemer, schützt aber die Kopien nicht |
| 4 | Aufbewahrung | **14 Tage täglich + 12 Monate monatlich** | Kürzer (weniger Daten in Kopien) oder länger |
| 5 | Zeitpunkt | **Täglich 12:00** (Mac meist an; sonst beim Aufwachen) | Andere Uhrzeit; mehrmals täglich ist bei dieser Nutzung nicht nötig |
| 6 | Test der Rücksicherung | **In eine temporäre Datenbank am Server** | In einem Wegwerf-Container am Mac: einfacher, aber echte Daten kurz am Mac |
| 7 | Sessions und Passwort-Links | **Ohne Inhalt sichern** | Mitsichern: nach einer Rücksicherung blieben alle angemeldet |

## Umsetzung nach Freigabe

1. Repo: `deploy/backup.sh`, `bin/backup-pull`, Vorlage für den LaunchAgent, Anleitung `deployment.md` S11 (Server) und
   `entwicklung.md` (Mac). Skript am Mac lokal gegen den Dev-Stack getestet.
2. Mac (Mike): age installieren, Schlüssel erzeugen und ablegen, SSH-Key erzeugen.
3. Server (Mike, nach `deployment.md` S11): Benutzer, sudo-Regel, Skript, age, Schlüssel eintragen.
4. Erster Lauf von Hand, dann LaunchAgent laden.
5. Test der Rücksicherung, Ergebnis im Projektstand. Danach dürfen echte Daten eingetragen werden.
