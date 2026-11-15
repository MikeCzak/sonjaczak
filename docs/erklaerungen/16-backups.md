# Backups der Datenbank

Anlass: Einrichtung am 2026-11-15. Mike wollte nicht nur die Schritte erklärt haben, sondern auch das Konzept, ob das
gängige Praxis ist und welche Alternativen es gibt. Plan und Entscheidungen: `planung/backups.md`. Anleitungen:
`deployment.md` S11 (Server) und `entwicklung.md`, „Backups“ (Mac).

**Zu den Quellen:** Optionen und Verhalten sind geprüft gegen die PostgreSQL-18-Doku (postgresql.org/docs/18, abgerufen
2026-11-15) und die man-Seiten `sshd`, `sudoers`, `visudo` und `launchd.plist` auf dem Mac. Die man-Seiten stammen von
macOS, nicht vom Ubuntu-Server; die zitierten Regeln sind in OpenSSH und sudo seit Langem gleich, auf dem Server aber nicht
eigens nachgelesen. Abschnitt 6 („Wie machen das Firmen?“) ist Einschätzung, keine geprüfte Statistik.

## 1. Konzepte

### Was ein Backup leisten muss

Ein Backup ist eine Kopie, aus der man den Zustand von früher **wiederherstellen** kann. Entscheidend ist die
Wiederherstellung, nicht die Kopie. Deshalb gehört der Test der Rücksicherung zum Backup dazu (Abschnitt 1, „Erst die
Rücksicherung zählt“).

Wogegen es schützen soll, bestimmt, wie es aussehen muss:

| Gefahr | Beispiel | Was dagegen hilft |
|---|---|---|
| Server weg | Platte kaputt, VPS gekündigt, IONOS-Fehler | Kopie **an einem anderen Ort** |
| Eigener Fehler | Falsche Migration, versehentlich gelöscht | **Ältere** Stände, nicht nur der neueste |
| Angriff auf den Server | Jemand übernimmt den VPS und löscht alles | Der Server darf die Kopien **nicht erreichen** |
| Kopie gerät in falsche Hände | Mac gestohlen, alte Time-Machine-Platte | **Verschlüsselung**, Schlüssel woanders |

Eine Replikation (zweite Datenbank, die laufend mitschreibt) ist übrigens **kein** Backup: Ein `DELETE` wird sofort
mitrepliziert. Sie schützt nur gegen „Server weg“.

### Zwei Kennzahlen: RPO und RTO

- **RPO (Recovery Point Objective):** Wie viele Daten dürfen höchstens verloren gehen? Bei einer Sicherung täglich um
  12:00 bis zu 24 Stunden (oder mehr, wenn der Mac aus war). Für Sonja heißt das: im schlimmsten Fall die Einträge eines
  Tages nachtragen.
- **RTO (Recovery Time Objective):** Wie lange darf die Wiederherstellung dauern? Bei uns: neuen Server einrichten und
  Dump einspielen, grob ein paar Stunden. Ein Ausfall der Verwaltung schadet niemandem sofort.

Firmen legen diese beiden Werte zuerst fest und wählen dann die Technik. Je kleiner RPO und RTO, desto teurer und
aufwendiger. Wir haben sie nie ausdrücklich festgelegt, die Entscheidungen im Plan ergeben aber genau diese Werte.

### Die 3-2-1-Regel

Verbreitete Faustregel: **3** Kopien der Daten, auf **2** verschiedenen Medien, **1** davon an einem anderen Ort.
Bei uns:

1. Live-Datenbank auf dem Server (Rechenzentrum von IONOS)
2. Sicherungen auf dem Mac (anderer Ort, anderes Gerät)
3. Time Machine des Mac, falls eingerichtet (anderes Medium). Der Ordner `~/Backups/sonjaczak/` liegt bewusst außerhalb
   des Schreibtischs, damit er normal mitgesichert wird.

Ohne Time Machine sind es zwei Kopien. Für diesen Umfang ist das vertretbar, aber es ist die schwächste Stelle
(Abschnitt 5).

### Logisches gegen physisches Backup

Die PostgreSQL-Doku nennt „three fundamentally different approaches“ (Kapitel 25, „Backup and Restore“):

1. **SQL dump** (`pg_dump`): Die Datenbank wird als Inhalt exportiert, also Tabellen, Zeilen, Indizes als
   Beschreibung. Das ist ein **logisches** Backup. Unseres.
2. **File system level backup:** die Dateien im Datenverzeichnis kopieren (**physisch**). Geht nur bei gestopptem Server
   oder mit einem Snapshot des Dateisystems.
3. **Continuous archiving:** ein physisches Grund-Backup plus laufend das Transaktionsprotokoll (WAL). Damit kann man
   zu **jedem beliebigen Zeitpunkt** zurück („Point-in-Time Recovery“, PITR, Abschnitt 25.3).

Was für `pg_dump` spricht, laut Doku (Abschnitt 25.1):

- „Dumps created by pg_dump are internally consistent, meaning, the dump represents a snapshot of the database at the
  time pg_dump began running. pg_dump does not block other operations on the database while it is working.“ Sonja kann
  also weiterarbeiten, während gesichert wird, und der Dump ist trotzdem in sich stimmig.
- „pg_dump's output can generally be re-loaded into newer versions of PostgreSQL, whereas file-level backups and
  continuous archiving are both extremely server-version-specific.“ Für uns wichtig beim späteren Upgrade auf
  PostgreSQL 19 oder 20.

**Ehrlich dazu:** Die Doku von `pg_dump` sagt auch: „except in simple cases, pg_dump is generally not the right choice
for taking regular backups of production databases“ (postgresql.org/docs/18/app-pgdump.html). Gemeint sind große
Datenbanken (ein Dump dauert Stunden) und Systeme, bei denen 24 Stunden Datenverlust nicht tragbar sind. Unsere
Datenbank ist rund 34 KB groß, der Dump dauert unter einer Sekunde, und ein Tag Verlust ist verkraftbar. Das ist genau
der „simple case“.

### Asymmetrische Verschlüsselung mit age

age ist ein kleines Verschlüsselungswerkzeug mit **Schlüsselpaar**:

- Der **öffentliche** Schlüssel (`age1…`) kann nur **verschlüsseln**. Er liegt am Server und ist nicht geheim.
- Der **private** Schlüssel (`AGE-SECRET-KEY-1…`) kann **entschlüsseln**. Er liegt nur im Passwort-Manager.

Das ist der Kern des Aufbaus: Der Server kann jeden Tag eine Sicherung erzeugen, aber keine einzige lesen, auch keine
alte. Ein Angreifer auf dem Server findet dort nichts, womit er die Kopien am Mac öffnen könnte. Bei einer
symmetrischen Verschlüsselung (ein Passwort für beides) müsste das Passwort am Server liegen, und damit wäre der Schutz
weg.

Jede verschlüsselte Datei beginnt mit der Kopfzeile `age-encryption.org/v1`. Die prüfen das Mac-Skript und dein
SSH-Test: Steht sie da, kam eine age-Datei an.

### Pull statt Push

Zwei Möglichkeiten, die Kopie vom Server auf den Mac zu bringen:

- **Push:** Der Server schickt die Sicherung an ein Ziel. Dann braucht der Server Zugangsdaten zum Ziel. Wer den Server
  übernimmt, kann damit auch die Sicherungen löschen.
- **Pull:** Das Ziel holt die Sicherung ab. Der Server hat keinen Zugang zum Mac. **Unser Weg.**

Pull passt außerdem zum Mac: Er hat keine feste Adresse und ist nicht immer an, der Server dagegen schon.

### Geringste Rechte (Least Privilege)

Der Mac braucht genau eine Fähigkeit am Server: „gib mir einen verschlüsselten Dump“. Alles darüber hinaus wäre unnötiges
Risiko, denn der SSH-Key liegt ohne Passphrase am Mac. Deshalb drei Sperren hintereinander:

1. **SSH:** Der Key darf nur einen festen Befehl starten (`command=…`) und sonst nichts (`restrict`).
2. **Eigener Benutzer `dbbackup`:** nicht in der Gruppe `docker`. Die wäre faktisch root, weil man mit Docker beliebige
   Dateien des Hosts einbinden kann.
3. **sudo:** `dbbackup` darf genau ein Skript als root starten, ohne Argumente.

Wer den Key stiehlt, bekommt verschlüsselte Dumps und sonst nichts.

### Aufbewahrung (Rotation)

Nur die neueste Sicherung zu behalten hilft nicht gegen einen Fehler, den man erst nach einer Woche bemerkt. Alle zu
behalten sammelt unnötig personenbezogene Daten (DSGVO: Was in der App gelöscht ist, steckt noch in den Kopien). Üblich
ist ein Staffelmodell, oft „Großvater-Vater-Sohn“ genannt: viele junge Stände, wenige alte. Bei uns: 14 Tage täglich,
dazu der 1. jedes Monats für 12 Monate.

### Erst die Rücksicherung zählt

Ein Backup, dessen Rücksicherung nie getestet wurde, ist eine Vermutung. Typische Fehler, die man erst beim Einspielen
bemerkt: leere oder abgeschnittene Dateien, fehlender Schlüssel, falsches Format, fehlende Tabellen. Unser erster
Versuch heute zeigt das im Kleinen: Ohne Datei lief `pg_restore` gegen einen leeren Datenstrom und brach ab. Genau dafür
ist der Test da.

## 2. Was wir gemacht haben, Schritt für Schritt

### Mac: Schlüssel

```bash
brew install age
age-keygen | pbcopy
pbcopy < /dev/null
```

- `age-keygen` schreibt den privaten Schlüssel auf stdout und den öffentlichen auf stderr. Mit `| pbcopy` landet nur der
  private in der Zwischenablage, der öffentliche erscheint im Terminal. So berührt der private Schlüssel nie die Platte.
- `pbcopy < /dev/null` überschreibt die Zwischenablage mit nichts. Sonst läge der Schlüssel dort, bis du etwas anderes
  kopierst, und manche Programme (Zwischenablage-Verlauf, Universal Clipboard) merken sich Inhalte.

```bash
ssh-keygen -t ed25519 -N "" -C "sonjaczak-backup" -f ~/.ssh/sonjaczak_backup
```

- Eigener Key nur für diesen Zweck. Dein normaler Key bleibt, wie er ist (mit voller Shell als `mike`).
- `-N ""` heißt ohne Passphrase. launchd startet das Skript ohne dich und könnte keine eingeben. Der Schaden bei Diebstahl
  ist durch die drei Sperren oben klein.
- `-C` ist nur ein Kommentar, damit man den Key in `authorized_keys` erkennt.

### Server: S11

- **`apt install age`:** Version 1.1.1 aus Ubuntu, am Mac 1.3.2. Das Dateiformat ist dasselbe; dass es passt, hat der
  Rücksicherungstest gezeigt.
- **Skripte nach `/usr/local/sbin`, Eigentümer root, `install -m 755`:** `dbbackup` darf sie ausführen, aber nicht
  ändern. Könnte er das Skript ändern, wäre die sudo-Regel wertlos: Er schriebe einfach etwas anderes hinein und
  startete es als root.
- **`backup-recipient.txt`:** der öffentliche Schlüssel. Nicht geheim, aber root gehört die Datei, damit niemand ihn
  gegen einen eigenen austauschen kann (dann wären die Sicherungen für den Angreifer lesbar, für dich nicht).
- **`useradd … dbbackup` und `usermod -p '*'`:** Ein neuer Benutzer hat ein gesperrtes Passwort (`!`). OpenSSH lehnt
  bei manchen Einstellungen auch Key-Logins für gesperrte Konten ab. `*` heißt „kein gültiges Passwort, aber nicht
  gesperrt“: Anmelden geht nur mit dem Key. Dieselbe Falle gab es schon bei `deploy` (`deployment.md` S5).
- **`authorized_keys` mit `restrict,command="…"`:** Laut `man sshd` schaltet `restrict` „port, agent and X11
  forwarding“ ab, „as well as disabling PTY allocation and execution of ~/.ssh/rc“, und gilt auch für künftige
  Einschränkungen. `command=` erzwingt den Befehl: Was der Mac verlangt, wird ignoriert, es läuft immer
  `sudo /usr/local/sbin/sonjaczak-backup`.
  - Daher die Meldung „PTY allocation request failed“ bei deinem Test: ssh wollte ein Terminal, `restrict` verbietet es.
    Mit `ssh -T` fragt ssh gar nicht erst (inzwischen in Anleitung und Skript).
- **sudoers-Regel:** `dbbackup ALL=(root) NOPASSWD: /usr/local/sbin/sonjaczak-backup ""`. Laut `man sudoers`: „If the
  command line arguments consist of "", the command may only be run with no arguments.“ Ohne `""` dürfte er beliebige
  Argumente mitgeben. Die zweite Zeile erlaubt `mike` das Rücksicherungs-Skript ohne Passwort, weil dort die
  Standardeingabe schon der Datenstrom ist und sudo nicht nach dem Passwort fragen könnte.
- **`visudo -f`:** Laut `man visudo` bearbeitest du eine Kopie: „The temporary file used is the specified sudoers file
  with ".tmp" appended to it.“ Nach dem Schließen des Editors prüft visudo die Syntax und legt die Datei erst dann unter
  dem richtigen Namen ab. Deshalb muss beim Speichern die `.tmp`-Endung bleiben. Speichert man unter dem Zielnamen,
  bleibt die `.tmp`-Datei unverändert, visudo meldet „unchanged“, und die Prüfung entfällt.
  - Warum stört die `.tmp`-Datei sudo nicht, solange sie existiert? `man sudoers` zu `@includedir`: sudo liest jede Datei
    in `/etc/sudoers.d`, „skipping file names that end in ‘~’ or contain a ‘.’ character“. Deshalb heißt unsere Datei
    auch `sonjaczak-backup` und nicht `sonjaczak-backup.conf`: Die würde stillschweigend ignoriert.
  - Warum die Vorsicht: Ein Syntaxfehler in einer sudoers-Datei kann sudo komplett sperren, und `mike` hat kein
    Root-Passwort. Daher der Tipp mit einer zweiten Sitzung, in der schon `sudo -i` läuft.

### Was das Server-Skript tut (`deploy/backup.sh`)

```bash
set -euo pipefail
docker exec "$CONTAINER" sh -c 'exec pg_dump … --format=custom \
    --exclude-table-data=sessions --exclude-table-data=reset_password_request' \
    | age --encrypt --recipients-file "$RECIPIENTS"
```

- **`pg_dump` im Datenbank-Container:** gleiche Version wie der Server (18). Ein `pg_dump` außerhalb müsste man passend
  installieren.
- **`--format=custom`:** komprimiert und nur mit `pg_restore` lesbar. Vorteil gegenüber reinem SQL: Man kann beim
  Einspielen einzelne Tabellen auswählen.
- **`--exclude-table-data`:** Die Tabellen bleiben im Dump, aber leer. Anmeldungen und Passwort-Links gehören nicht in
  Kopien, und nach einer Rücksicherung meldet man sich einfach neu an.
- **`| age`:** Der Dump fließt direkt in die Verschlüsselung und von dort über die SSH-Verbindung zum Mac. Er liegt am
  Server nie unverschlüsselt auf der Platte.
- **`pipefail`:** Ohne die Option zählt in einer Pipe nur der Exit-Code des **letzten** Befehls. Scheitert `pg_dump`,
  würde `age` trotzdem eine gültige, aber leere verschlüsselte Datei liefern, und das Skript meldete Erfolg. Beim Testen
  vor der Einrichtung nachgestellt: 200 Byte age-Datei bei kaputtem `pg_dump`.

### Mac: Skript und LaunchAgent

- **`install -m 755 bin/backup-pull ~/.local/bin/…`:** Kopie statt Start aus dem Projektordner. Der liegt auf dem
  Schreibtisch, und macOS kann Hintergrundprogrammen den Zugriff darauf verweigern (Datenschutz-Einstellungen).
- **`bin/backup-pull`** holt die Sicherung zuerst in eine versteckte Datei `.incoming.<PID>`, prüft dann Exit-Code und
  Kopfzeile, benennt sie erst dann nach dem Datum um. So ersetzt ein kaputter Lauf nie eine gute Sicherung desselben
  Tages. Danach Rotation, und zwar **nur** nach einem Erfolg: Fällt der Server eine Woche aus, werden keine alten
  Sicherungen gelöscht, während keine neuen dazukommen.
- **Warnungen:** Bei einem Fehler oder wenn die neueste Sicherung älter als 3 Tage ist, kommt eine macOS-Mitteilung.
  Ein Backup, das still aufhört, ist der häufigste Fehler überhaupt.
- **`sed "s#__HOME__#$HOME#g" … > ~/Library/LaunchAgents/…plist`:** launchd versteht in plists kein `~` und keine
  Variablen, also wird der Pfad beim Installieren eingesetzt.
- **launchd statt cron:** `man launchd.plist`: „Unlike cron which skips job invocations when the computer is asleep,
  launchd will start the job the next time the computer wakes up.“ Bei einem Laptop entscheidend.
- **`launchctl bootstrap gui/$(id -u) …`:** lädt den Agenten in deine Benutzersitzung (`gui/<UID>`). `kickstart` startet
  ihn sofort einmal, aber **im Hintergrund**: Der Befehl wartet nicht. Deshalb war das Log beim `tail` direkt danach noch
  nicht da und eine Sekunde später schon. `launchctl print gui/$(id -u)/com.sonjaczak.backup` zeigt `runs` und
  `last exit code`.

### Test der Rücksicherung

```bash
age -d -i <(pbpaste) ~/Backups/sonjaczak/$(date +%F).dump.age \
  | ssh mike@sonjaczak.com sudo /usr/local/sbin/sonjaczak-restore-test
```

- **`<(pbpaste)`** ist eine Process Substitution der Shell: Sie sieht für `age` aus wie eine Datei, ist aber nur ein
  Datenstrom aus der Zwischenablage. Der private Schlüssel wird nie gespeichert.
- **Entschlüsselt am Mac, eingespielt am Server:** Die echten Daten liegen so nie unverschlüsselt am Mac. Sie fließen
  entschlüsselt durch die (verschlüsselte) SSH-Verbindung direkt in `pg_restore`.
- **Temporäre Datenbank `app_restore_test`:** Die Live-Datenbank wird nur gelesen (zum Vergleich der Zeilenzahlen). Ein
  `trap … EXIT` löscht die Testdatenbank auch dann, wenn etwas schiefgeht, wie bei deinem ersten Versuch.
- **Vergleich je Tabelle:** prüft, dass wirklich alles angekommen ist, nicht nur, dass `pg_restore` ohne Fehler endete.

## 3. Alternativen

### Zum Gesamtaufbau

| Alternative | Wie | Warum nicht bei uns |
|---|---|---|
| **Snapshots des VPS** beim Hoster | Ganzes Laufwerk sichern, Klick im Kundenportal | Liegt beim selben Anbieter (kein anderer Ort); die Datenbank kann mitten im Schreiben erwischt werden; Verfügbarkeit und Preis bei IONOS nicht geprüft. Gut als **Ergänzung** für schnelles Wiederherstellen des ganzen Servers. |
| **Push in einen Cloud-Speicher** (S3, Backblaze B2, Hetzner Storage Box) per cron am Server | Server lädt den verschlüsselten Dump hoch | Läuft ohne Mac und ist die üblichere Lösung. Kostet ein paar Cent bis Euro im Monat, braucht ein weiteres Konto und Zugangsdaten am Server. Mit „append-only“-Rechten (Schreiben ja, Löschen nein) gut absicherbar. **Realistische nächste Stufe**, falls der Mac als einziger Ort stört. |
| **restic oder BorgBackup** | Werkzeuge mit Verschlüsselung, Deduplizierung und Rotation eingebaut | Mehr Leistung, als ein 34-KB-Dump braucht; ein weiteres Werkzeug zum Verstehen. Sinnvoll, sobald Dateien dazukommen (z. B. Fotos). |
| **WAL-Archivierung mit pgBackRest, Barman oder WAL-G** (PITR) | Laufende Sicherung des Transaktionsprotokolls | RPO von Minuten statt einem Tag, aber eigener Dienst, mehr RAM und Platte am Server, deutlich mehr Komplexität. Für 850 MiB RAM und eine Benutzerin unverhältnismäßig. |
| **Verwaltete Datenbank** (z. B. AWS RDS, DigitalOcean Managed PostgreSQL) | Anbieter sichert automatisch mit PITR | Kostet im Monat ein Mehrfaches des VPS; Daten bei einem weiteren Anbieter (DSGVO-Vertrag). |
| **Replikat** | Zweite Datenbank läuft mit | Kein Backup (siehe Abschnitt 1), nur Hochverfügbarkeit. |

### Zu einzelnen Bausteinen

- **GPG statt age:** verbreiteter, aber mit Schlüsselbund, vielen Optionen und alten Algorithmen als Standard leichter
  falsch zu bedienen. age macht eine Sache mit einem Befehl.
- **Verschlüsseln am Mac statt am Server:** nichts zu installieren am Server, aber der Dump käme unverschlüsselt am Mac
  an und läge dort mindestens kurz auf der Platte.
- **Privater Schlüssel als Datei am Mac:** bequemer (kein Passwort-Manager beim Test), aber dann liegen Sicherungen und
  Schlüssel zusammen in Time Machine und auf einem gestohlenen Mac.
- **Normaler Benutzer mit voller Shell statt `dbbackup` mit erzwungenem Befehl:** einfacher einzurichten, aber ein Key
  ohne Passphrase mit Shell-Zugang wäre ein offenes Tor.
- **cron statt launchd am Mac:** verpasst Termine im Ruhezustand.
- **Rücksicherungstest am Mac** in einem Wegwerf-Container: einfacher, aber echte Daten lägen entschlüsselt am Mac.

## 4. Sicherheit und Best Practices

Was der Aufbau abdeckt:

- **Server übernommen:** Angreifer kann die Live-Daten lesen (das kann kein Backup verhindern), aber keine Sicherung
  löschen oder lesen.
- **Mac gestohlen:** Sicherungen sind verschlüsselt; der Backup-Key kann nur verschlüsselte Dumps abholen. Den Key am
  Server aus `/home/dbbackup/.ssh/authorized_keys` löschen.
- **Fehlschlag bleibt nicht unbemerkt:** Prüfung der Kopfzeile, Exit-Code, Mitteilung nach 3 Tagen ohne Sicherung.
- **Wenig Rechte überall:** `dbbackup` ohne Docker, ein Skript per sudo, Skripte gehören root.

## 5. Grenzen (ehrlich)

- **Der private Schlüssel ist der eine Punkt, an dem alles hängt.** Ist er weg, sind alle Sicherungen wertlos. Deshalb
  im Plan: Passwort-Manager **und** ausgedruckt an einem sicheren Ort.
- **Nur ein Ort außerhalb des Servers**, und der ist ein Laptop. Brennt die Wohnung ab und es gibt kein Time-Machine-Ziel
  woanders, sind Server und Mac die einzigen Kopien. Eine zweite Kopie außer Haus (Cloud-Speicher oder externe Platte
  an einem anderen Ort) wäre die nächste Verbesserung.
- **RPO bis zu einem Tag, länger im Urlaub mit zugeklapptem Mac.** Die 3-Tage-Warnung meldet das, verhindert es nicht.
- **Ernstfall nicht durchgespielt:** Der Test spielt in eine leere Datenbank ein. Den ganzen Ablauf auf einem neuen
  Server haben wir noch nicht geprobt (offener Punkt im Projektstand).
- **Wiederholung des Tests:** einmal getestet heißt nicht für immer. Nach größeren Änderungen (PostgreSQL-Upgrade,
  neue Tabellen mit Dateien) und sonst etwa jährlich wiederholen.

## 6. Wie machen das Firmen?

Einschätzung, keine geprüfte Statistik. Die Prinzipien (Kopie woanders, Verschlüsselung, ältere Stände,
Rücksicherung testen, geringste Rechte) sind überall dieselben. Was sich mit der Größe ändert, sind Werkzeuge,
Automatisierung und die Zielwerte für RPO und RTO.

- **Einzelpersonen und sehr kleine Firmen:** genau solche Lösungen. `pg_dump` oder `mysqldump` per cron, oft ohne
  Verschlüsselung und fast immer **ohne** Rücksicherungstest. Häufig nur Snapshots beim Hoster. Unser Aufbau ist in
  diesem Bereich eher gründlicher als üblich. Ungewöhnlich ist nur der Mac als Ziel; die meisten würden in einen
  Cloud-Speicher pushen, damit es ohne eigenes Gerät läuft.
- **Kleine bis mittlere Firmen** (eigene IT oder Agentur): Meistens eine verwaltete Datenbank mit automatischen
  Backups und PITR, oder bei eigenen Servern pgBackRest bzw. Barman plus nächtlichen Dumps. Ziel ist ein Objektspeicher in
  einer anderen Region, oft mit Versionierung oder „Object Lock“ (Unveränderbarkeit für eine Frist, gegen Ransomware).
  Überwachung über das normale Monitoring statt über Mitteilungen. Rücksicherungstests eher selten und manuell.
- **Große Firmen:** Backup ist eine eigene Disziplin mit festgelegten RPO/RTO je System, oft vorgegeben durch Normen
  und Prüfungen (z. B. ISO 27001). PITR ist Standard, dazu Replikate in anderen Rechenzentren (für Verfügbarkeit,
  zusätzlich zum Backup), unveränderbare Kopien, Schlüsselverwaltung in einem KMS oder HSM statt im Passwort-Manager,
  regelmäßige und oft automatisierte Rücksicherungstests sowie Notfallübungen. Dafür gibt es eigene Produkte (Veeam,
  Commvault, Cloud-eigene Backup-Dienste).

Für Sonja gilt: Ein Tag Datenverlust ist verkraftbar, die Datenbank ist winzig, und der Server hat kaum Reserven. Die
Lösung ist deshalb bewusst einfach gehalten und folgt trotzdem denselben Prinzipien wie die großen.
