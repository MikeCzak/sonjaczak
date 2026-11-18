# Backups ohne Mikes Mac

Stand: 2026-11-18, **freigegeben (alle Empfehlungen), noch nicht umgesetzt.** Mike bleibt vorerst bei der Sicherung am
Mac; umgesetzt wird später (`projektstand.md`, „Später zu klären“, Technisch). Ergänzt
`planung/backups.md`. Was dort steht, bleibt gleich: Was gesichert wird (nur die Datenbank, Sessions ohne Inhalt), `pg_dump -Fc`,
Verschlüsselung mit age am Server, privater Schlüssel nur im Passwort-Manager, Aufbewahrung 14 Tage + 12 Monate. Neu
ist nur, **wer die Sicherung abholt bzw. wo sie liegt**.

## Ausgangslage

Heute holt Mikes Mac die Sicherung täglich um 12:00 (launchd, `deployment.md` S11). Schwächen (`erklaerungen/16`,
Abschnitt 5):

- Ist der Mac zu oder unterwegs, gibt es keine Sicherung. Die 3-Tage-Warnung meldet das, verhindert es nicht.
- Die Kopien liegen bei Mike zu Hause (Mac, externe Platte von Sonja). Brand oder Diebstahl dort trifft alle Kopien
  außerhalb des Servers.
- Sonjas Daten hängen an Mikes privatem Rechner.

## Ziel

1. Täglich eine Sicherung, egal ob ein Rechner von Mike oder Sonja läuft.
2. Die Kopien liegen in einem Rechenzentrum, nicht beim Server und nicht bei Mike zu Hause.
3. **Wer den Server übernimmt, kann die Sicherungen nicht löschen.** Das ist der Punkt, an dem „der Server schickt“
   heikler ist als „der Mac holt“: Heute hat der Server keinen Zugang zu den Kopien. Schickt er sie selbst, braucht er
   einen Zugang, und mit dem ließen sich Dateien löschen. Jede Option muss das abfangen.
4. Kein zusätzlicher Container, kaum RAM am Server (ein Cronjob mit `pg_dump | age | sftp` für ein paar Sekunden).
5. Jemand merkt es, wenn Sicherungen ausbleiben (heute: Mitteilung am Mac).

## Optionen

### A. Hetzner Storage Box, der Server schickt (Empfehlung)

Ein Speicherplatz bei Hetzner, erreichbar per SFTP mit SSH-Key. Der Server lädt die verschlüsselte Sicherung per
Cronjob hoch. Gegen das Löschen schützen **Snapshots**, die Hetzner nach Zeitplan selbst anlegt.

Laut Hetzner (gelesen 2026-11-18):

- BX11: „Available storage: 1 TB“, „€ 3.20 per month, excl. VAT“, „No minimum contract term“, Kündigung „immediately“.
  Standorte „Germany or Finland“ (Falkenstein, Helsinki). Quelle: hetzner.com/storage/storage-box/bx11/. Bruttopreis
  und eine eventuelle Einrichtungsgebühr standen nicht im abgerufenen Text: bei der Bestellung prüfen.
- Protokolle u. a. SFTP, SCP, rsync, BorgBackup, restic, rclone; Port 22 „only SCP and SFTP, no interactive access“
  (docs.hetzner.com/storage/storage-box/general, …/ssh-keys/add-ssh-keys).
- Unterkonten: „Sub-accounts have access to their own sub-directory only.“ Ein Verzeichnis lässt sich auf read-only
  setzen: „Then the sub-account users cannot upload or delete files.“ (general)
- Snapshots: bei BX11 10 automatische Plätze, Zeitplan z. B. täglich; ist das Limit erreicht, wird der älteste
  automatisch gelöscht. „It is not possible to write to the /.zfs directory or its subfolder.“ Snapshots kosten nichts
  extra, belegen aber Platz (nur für Geändertes). (docs.hetzner.com/storage/storage-box/snapshots)
- AVV nach Art. 28 DSGVO: Muster als PDF, Abschluss im Konto unter accounts.hetzner.com/account/dpa („We do not create
  or sign individual DPAs“). (docs.hetzner.com/general/general-terms-and-conditions/data-privacy-faq/)

**Nicht belegt, vor dem Echtbetrieb prüfen:** ob ein Unterkonto bzw. wer den SSH-Key hat, Snapshots löschen kann. Die
Doku beschreibt Anlegen und Löschen nur in der Hetzner Console. Davon hängt Ziel 3 ab; prüfen wir mit einem Test
(siehe Umsetzung, Schritt 5).

- Pro: billig, kein eigenes Gerät, kein zweiter Server zu warten; SFTP kann der Server ohne neues Paket (`sftp` ist
  Teil von OpenSSH); Rechenzentrum in Deutschland; täglich unabhängig von Mike.
- Contra: Server braucht einen Zugang mit Schreibrecht; Schutz gegen Löschen nur so weit, wie die Snapshots reichen
  (10 Tage bei täglichem Snapshot). Ein Angreifer, der unbemerkt länger als 10 Tage auf dem Server sitzt, könnte alte
  Dateien löschen, ohne dass ein Snapshot sie noch hält. Ein weiterer Anbieter mit Konto und AVV.

### B. Objektspeicher mit Object Lock (IONOS oder Backblaze B2)

Der Server lädt per S3-Protokoll hoch; der Bucket ist mit Object Lock gesperrt, jede Datei bleibt für eine feste Frist
unlöschbar, auch für den, der hochlädt.

- IONOS: Object Lock vorhanden, schaltet die Versionierung mit ein, muss beim Anlegen des Buckets gewählt werden
  (docs.ionos.com, „object lock“, „features benefits“). Preise habe ich nicht gefunden.
- Backblaze B2: Object Lock vorhanden; Preis laut Drittquellen 6,95 $/TB (nicht offiziell geprüft); Vertragspartner ist
  die US-Firma, auch bei Region Amsterdam.
- Pro: echte Unveränderbarkeit, nicht nur 10 Tage. IONOS wäre derselbe Anbieter wie der VPS.
- Contra: ein S3-Werkzeug am Server nötig (`rclone` oder `aws`-CLI); Aufbewahrung per Lifecycle-Regeln und Lock-Frist
  ist mehr Konfiguration und mehr Fallstricke (Löschmarker, Versionen, Frist passt nicht zu „14 Tage + 12 Monate“
  ohne Nachdenken); Kosten nicht belegt.

### C. Eigenes Gerät, das holt (z. B. Raspberry Pi oder NAS bei Sonja)

Wie heute der Mac, nur ein Gerät, das immer läuft. Der Server behält „schickt nichts von sich aus“.

- Pro: Sicherheitsmodell bleibt unverändert (Server hat keinen Zugang zu den Kopien); keine Monatskosten außer Strom.
- Contra: Hardware kaufen, einrichten, Updates; hängt an Sonjas Internet und Strom; liegt wieder in einer Wohnung
  (Brand, Diebstahl); Mike muss im Fehlerfall vor Ort oder per Fernzugang ran.

### D. GitHub Actions holt per Zeitplan

Ein geplanter Workflow holt wie der Mac und legt die Datei irgendwo ab (bräuchte trotzdem A oder B als Ziel).

- Contra: „In a public repository, scheduled workflows are automatically disabled when no repository activity has
  occurred in 60 days“ (docs.github.com, „Disable and enable workflows“). Wenn Mike ein paar Monate nichts pusht,
  hören die Backups still auf. Zusätzlich lägen der Backup-Key und der Speicherzugang in GitHub. **Nicht empfohlen.**

### E. Zweiter kleiner VPS, der holt

Wie C, aber im Rechenzentrum. Pro: Sicherheitsmodell bleibt. Contra: ein zweiter Server zum Absichern und Patchen,
Kosten, für sechs Schüler:innen unverhältnismäßig.

## Empfehlung im Detail (Option A)

```
Server (cron, täglich 12:00)                                     Hetzner Storage Box (BX11, Falkenstein)
sonjaczak-backup  (wie heute: pg_dump | age)                     Unterkonto „sonjaczak-upload“ → /backups
  → sonjaczak-backup-push: Datei in /var/tmp (verschlüsselt)  ──►  2026-11-18.dump.age
     sftp put, danach Rotation (14 Tage + 1. des Monats)          automatischer Snapshot täglich (10 Plätze)
     bei Fehler: Mail an Mike (mailer:test, wie S6)
Mac (Mike, bei Bedarf)  ◄── Unterkonto „sonjaczak-read“, read-only (für Test der Rücksicherung)
```

1. **Das bestehende Skript bleibt.** `sonjaczak-backup` erzeugt weiter den verschlüsselten Dump auf der
   Standardausgabe. Ein neues Skript `sonjaczak-backup-push` (Repo: `deploy/backup-push.sh`) ruft es auf, schreibt in
   eine temporäre Datei, prüft die age-Kopfzeile (wie `bin/backup-pull`), lädt per `sftp -b` hoch und rotiert. Die
   Datei am Server ist verschlüsselt; sie wird nach dem Hochladen gelöscht.
2. **Läuft als root per Cronjob** (wie S8, S12). Der SSH-Key für die Storage Box liegt unter `/root/.ssh/`, ohne
   Passphrase (cron kann keine eingeben). `known_hosts` mit den Fingerabdrücken, die Hetzner veröffentlicht
   (general-Seite), nicht blind übernommen.
3. **Zwei Unterkonten:** eines zum Hochladen (Schreibrecht, nur `/backups`), eines read-only für Mikes Mac. Der
   Hauptzugang wird für nichts automatisiert benutzt; sein Passwort kommt in den Passwort-Manager.
4. **Snapshots:** automatisch täglich (z. B. 13:00, nach dem Hochladen). Die 10 Plätze heißen: Was ein Angreifer
   löscht, ist noch 10 Tage im Snapshot. Die Rotation selbst macht das Skript.
5. **Aufbewahrung wie bisher**, also bleibt der Satz in der Datenschutz-Information („bis zu 12 Monate“) richtig. Ein
   gelöschter Monats-Dump steckt danach höchstens noch 10 Tage in Snapshots (12 Monate + 10 Tage); siehe Frage 4.
6. **Auffallen, wenn es ausbleibt:** Bei einem Fehler schickt das Skript eine Mail an Mike. Ein Cronjob, der gar nicht
   läuft, schickt keine; deshalb zusätzlich am 1. jedes Monats eine kurze Mail „Sicherungen vorhanden: neueste vom …,
   Anzahl …“ (nur Dateinamen, keine Daten). Alternative: ein externer Dienst, der Alarm schlägt, wenn ein Ping
   ausbleibt (z. B. healthchecks.io): ein Anbieter mehr, daher nicht empfohlen.
7. **Test der Rücksicherung** künftig aus der Storage Box statt aus `~/Backups`: Der Mac liest die Datei über das
   read-only-Unterkonto, entschlüsselt und schickt sie wie bisher an `sonjaczak-restore-test`:
   `sftp`-Download in eine Pipe bzw. temporäre Datei → `age -d -i <(pbpaste)` → `ssh … sonjaczak-restore-test`.
8. **Der Mac:** Bleibt der launchd-Job als zweite Kopie, oder wird er abgeschaltet? Siehe Frage 3.

## Fragen an Mike

| # | Frage | Empfehlung | Alternativen |
|---|---|---|---|
| 1 | Welche Option? | **A, Hetzner Storage Box** | B (Object Lock, echte Unveränderbarkeit, mehr Konfiguration, Preis offen), C (Gerät bei Sonja), E (zweiter VPS) |
| 2 | Wer bestellt und schließt den AVV? | **Sonja** (sie ist Verantwortliche, Hetzner verarbeitet in ihrem Auftrag), Mike richtet ein | Mike bestellt; dann wäre Hetzner Unterauftragnehmer von Mike, was die offene Frage zum AVV Sonja–Mike (`projektstand.md`, Rechtliches) noch wichtiger macht |
| 3 | Mac-Sicherung danach? | **Abschalten, sobald die Storage Box 2 Wochen läuft und eine Rücksicherung daraus geklappt hat.** Weniger Kopien echter Daten, weniger Pflege | Weiterlaufen lassen als zweite, unabhängige Kopie (Mac holt, Storage Box bekommt geschickt); robuster, aber genau die Abhängigkeit, die weg soll |
| 4 | Snapshot-Plan | **Täglich, 10 Plätze** (Schutz für 10 Tage) | Wöchentlich: Schutz über 10 Wochen, aber eine gelöschte Datei der letzten Tage wäre ungeschützt; Mischung geht nicht mit 10 automatischen Plätzen |
| 5 | Uhrzeit | **12:00 wie bisher** (cron in Serverzeit, eine Stunde Abweichung egal, wie S8) | Nachts: weniger Last bei Besucher:innen, aber vor dem Löschlauf 03:30 oder danach bewusst wählen |

## Was dafür nötig ist

**Von Mike bzw. Sonja (außerhalb des Repos):**

1. Hetzner-Konto (für Sonja oder Mike, Frage 2), BX11 in Falkenstein bestellen, 3,20 €/Monat netto. Zahlungsdaten und
   ggf. Identitätsprüfung bei Hetzner.
2. AVV im Hetzner-Konto abschließen (accounts.hetzner.com/account/dpa).
3. In der Hetzner Console: zwei Unterkonten anlegen (eines read-only), automatische Snapshots täglich einschalten,
   Hauptpasswort in den Passwort-Manager.
4. Server (nach neuer Anleitung `deployment.md` S11): SSH-Key für root erzeugen, öffentlichen Teil beim Upload-Unterkonto
   eintragen, Fingerabdruck prüfen, Skript installieren, Cronjob eintragen.
5. Mac: SSH-Key für das read-only-Unterkonto (für die Rücksicherung).

**Von Claude (im Repo):** `deploy/backup-push.sh` (Hochladen, Prüfung, Rotation, Mail bei Fehler, Monatsmail), lokal
gegen einen SFTP-Testserver geprüft; Anleitung in `deployment.md` S11 und `entwicklung.md`; dieser Plan,
`backups.md` und `erklaerungen/16` angepasst; ggf. Satz in der Datenschutz-Information (Frage 4, siehe unten).

**Rechtlich:** Hetzner ist ein weiterer Empfänger (Auftragsverarbeiter). Die Datenschutz-Information nennt heute
„außerhalb des Servers“ und einen IT-Dienstleister; ob dort ein Rechenzentrumsbetreiber als Kategorie stehen muss,
gehört zur offenen Frage zum AVV (`projektstand.md`, Rechtliches).

**Aufwand grob:** Claude ein Schritt (Skript und Doku); Mike etwa 1–2 Stunden für Bestellung, Console und Server.

## Umsetzung nach Freigabe

1. Repo: `deploy/backup-push.sh`, Anleitung, lokal gegen einen SFTP-Container getestet (nur zum Testen, nicht am
   Server).
2. Mike/Sonja: Bestellung, AVV, Unterkonten, Snapshots.
3. Server: Key, Skript, erster Lauf von Hand, Cronjob.
4. Rücksicherung aus der Storage Box testen.
5. **Test gegen Löschen:** mit dem Upload-Key eine Datei löschen und versuchen, Snapshots zu löschen (über SFTP im
   `/.zfs`-Verzeichnis); erwartet: Snapshot bleibt, Datei lässt sich daraus wiederholen. Klappt das Löschen, zurück zu
   Option B.
6. Nach 2 Wochen ohne Fehler: Frage 3 umsetzen, Projektstand und `verlauf.md` nachziehen.
