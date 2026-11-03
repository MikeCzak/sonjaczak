# 06 – stdin, EOF und warum `docker login --password-stdin` Strg+D braucht

Anlass: In [../deployment.md](../deployment.md) (B4) musste der Token eingefügt, Enter gedrückt **und dann Strg+D**
gedrückt werden.

## Konzepte

### Die drei Standard-Datenströme

Jedes Programm unter Unix startet mit drei geöffneten Kanälen:

| Nr. | Name | Richtung | Normalerweise verbunden mit |
|---|---|---|---|
| 0 | **stdin** (Standardeingabe) | ins Programm | Tastatur (Terminal) |
| 1 | **stdout** (Standardausgabe) | aus dem Programm | Bildschirm |
| 2 | **stderr** (Fehlerausgabe) | aus dem Programm | Bildschirm |

Die Shell kann diese Kanäle **umleiten**, ohne dass das Programm davon weiß:

```bash
befehl < datei        # stdin kommt aus einer Datei
befehl > datei        # stdout geht in eine Datei
befehl 2> datei       # stderr geht in eine Datei
a | b                 # stdout von a wird stdin von b (Pipe)
echo "x" >&2          # in deploy.sh: Meldung auf stderr statt stdout
```

Daher kommen auch Zahlen wie `9>` in `deploy.sh`: Dateideskriptor 9 ist einfach ein weiterer, selbst geöffneter Kanal.

### Das Ende der Eingabe: EOF

Ein Programm, das stdin liest, liest so lange, bis das Betriebssystem ihm sagt: **Es kommt nichts mehr** – das ist
**EOF** (End of File). EOF ist **kein Zeichen in den Daten**, sondern ein Zustand: Die Lese-Funktion liefert „0 Bytes
gelesen“.

- Bei einer **Datei** kommt EOF, wenn das Dateiende erreicht ist.
- Bei einer **Pipe** kommt EOF, wenn das schreibende Programm fertig ist und seinen Ausgang schließt.
- Beim **Terminal** gibt es kein natürliches Ende – du könntest ja immer weitertippen. Deshalb gibt es eine Taste
  dafür: **Strg+D**. Der Terminal-Treiber übersetzt Strg+D am Zeilenanfang in „EOF für den Leser“.

### Warum Enter allein nicht reicht

`docker login --password-stdin` liest **alles** von stdin bis EOF und nimmt das als Passwort (den Zeilenumbruch am Ende
entfernt es; nicht im Docker-Code geprüft). Es weiß nicht, dass dein Token nur eine Zeile lang ist – ein Passwort könnte theoretisch auch
mehrere Zeilen haben. Enter schickt also nur „Zeile fertig“; erst Strg+D sagt „Eingabe fertig“.

Andere Programme lesen dagegen **zeilenweise** (z. B. die Passwortabfrage von `sudo` oder `read` in der Shell) – dort
reicht Enter. Welches Verhalten ein Programm hat, entscheidet der Programmierer.

### Warum Strg+D am Zeilenanfang stehen sollte

Der Terminal-Treiber sammelt Eingaben zeilenweise. Strg+D bedeutet genau genommen: „Gib das Gesammelte jetzt sofort
weiter.“ Ist nichts gesammelt (Zeilenanfang, direkt nach Enter), bekommt das Programm 0 Bytes → EOF. Mitten in der
Zeile schickt das erste Strg+D nur den bisherigen Text weiter, erst ein zweites erzeugt EOF. Deshalb: Enter, dann
Strg+D.

Gleicher Mechanismus, den du schon kennst: Strg+D in einer Shell oder in `psql` beendet sie – die Shell liest EOF auf
stdin und beendet sich.

### Warum überhaupt über stdin und nicht als Argument?

```bash
docker login ghcr.io -u MikeCzak -p ghp_xxx          # schlecht
echo ghp_xxx | docker login ... --password-stdin     # auch nicht ideal
docker login ... --password-stdin                    # dann einfügen + Strg+D – so haben wir es gemacht
```

- **Argumente** sind für andere Benutzer auf demselben System sichtbar (`ps aux` zeigt die komplette Befehlszeile
  aller Prozesse) und landen in der **Shell-History** (`~/.bash_history`). Docker warnt deshalb bei `-p`.
- `echo token | …` vermeidet `ps` (bei `echo` als Shell-Builtin), landet aber in der History.
- **stdin vom Terminal** landet nirgends – weder in `ps` noch in der History. Die sicherste Variante für eine einmalige
  manuelle Eingabe.
- In Skripten/CI liest man aus einer Datei oder Variablen: `docker login … --password-stdin < tokendatei` oder
  `printf '%s' "$TOKEN" | docker login …`. Die `docker/login-action` in unserer CI macht intern Ähnliches.

### Was beim ersten Versuch passiert ist

```
$ sudo -u deploy -H docker login ghcr.io -u MikeCzak --password-stdin
[sudo] password for mike: <Token eingefügt>
Sorry, try again.
```
Zwei Programme wollten nacheinander von **derselben Tastatur** lesen: zuerst `sudo` (dein Passwort, zeilenweise),
danach `docker` (Token, bis EOF). Der Token ging an `sudo`. Mit `sudo -v` vorher ist sudo „aufgewärmt“ (merkt sich die
Anmeldung einige Minuten), und die nächste Eingabe geht direkt an Docker.

## Zum Ausprobieren

```bash
cat              # tippe Zeilen, jede kommt zurück; Strg+D beendet
wc -l            # tippe Zeilen, nach Strg+D erscheint die Anzahl – wc braucht EOF, um zu wissen, dass es fertig ist
printf 'a\nb\n' | wc -l    # 2 – bei der Pipe kommt EOF automatisch
```
