# Sperren (Locks): Honorarnummern und Sessions

Anlass: Mikes Frage (2026-11-14), wie bei nur einer Benutzerin zwei gleichzeitige Anfragen entstehen sollen, und was
Session- und Advisory Locks überhaupt sind. Diese Erklärung fängt bei null an. Die kürzeren Fassungen stehen in
`08-login-und-sessions.md` (Sessions) und `13-honorarnoten.md` (Nummern).

## 1. Das Grundproblem: zwei schauen gleichzeitig ins selbe Heft

Stell dir ein Heft vor, in dem die letzte Honorarnummer steht. Wer eine neue Honorarnote schreibt, macht drei Dinge:

1. ins Heft schauen: „letzte Nummer ist 4“
2. die Honorarnote mit 5 schreiben
3. ins Heft eintragen: „letzte Nummer ist 5“

Allein ist das kein Problem. Wenn aber zwei Personen gleichzeitig Schritt 1 machen, sehen beide die 4, und beide
schreiben eine Honorarnote mit der Nummer 5. Keine hat einen Fehler gemacht, sie haben nur gleichzeitig gearbeitet.
Das nennt man in der Informatik eine **Race Condition** (Wettlaufsituation): Das Ergebnis hängt davon ab, wer wann
schneller ist.

In unserer App ist das „Heft“ die Datenbank. Schritt 1 ist die Abfrage `MAX(number)` der Honorarnoten des Jahres
(`InvoiceRepository::nextNumber()`), Schritt 3 das Speichern.

## 2. Woher kommen zwei gleichzeitige Anfragen, wenn nur Sonja arbeitet?

Eine **Anfrage** (Request) ist genau ein Gespräch zwischen Browser und Server: Der Browser schickt „erstelle eine
Honorarnote für Teilnahme 12“, der Server arbeitet, schickt eine Antwort. Das dauert bei uns grob eine Zehntelsekunde.
„Gleichzeitig“ heißt also: zwei Anfragen innerhalb dieser Zehntelsekunde.

Der Server kann tatsächlich zwei Anfragen gleichzeitig bearbeiten: FrankenPHP startet zwei PHP-Worker (2 je CPU,
`compose.prod.yaml`), also zwei „Arbeiter“, die unabhängig voneinander je eine Anfrage erledigen. Keiner weiß vom
anderen.

Wie Sonja allein zwei Anfragen auslöst:

- **Doppelklick** auf „Honorarnote erstellen“. Viele Menschen doppelklicken aus Gewohnheit auf Knöpfe. Der Browser
  schickt dann zwei Anfragen kurz hintereinander, und der zweite Worker beginnt, bevor der erste fertig ist.
- **Zwei Tabs** oder Mac und Handy gleichzeitig.
- **Langsames Netz:** Die Seite reagiert nicht sofort, Sonja klickt noch einmal.

Ein Mensch kann gar nicht „gleichzeitig“ arbeiten, aber der Browser kann es für ihn tun. Deshalb schützt man solche
Stellen, auch bei nur einer Benutzerin. Selten ist es trotzdem. Entscheidend ist, was im seltenen Fall passiert.

## 3. Was ohne Schutz passieren würde

Zwei Dinge können schiefgehen:

| Was | Was die Datenbank selbst verhindert | Folge ohne weiteren Schutz |
|---|---|---|
| Zwei Honorarnoten mit derselben Nummer | Der **Unique-Constraint** auf `(year, number)`: Die Datenbank weigert sich, die zweite „5/2026“ zu speichern | Die zweite Anfrage bricht mit Fehlerseite (500) ab. Keine doppelte Nummer, aber ein Fehler für Sonja |
| Zwei Honorarnoten für **dieselbe Teilnahme** (Nummern 5 und 6) | Nichts: Es gibt keinen Constraint „eine Honorarnote je Teilnahme“, das prüft nur die App (`InvoiceIssuer::problems()`) | Zwei echte Honorarnoten, die sich nicht löschen lassen (Belege sind unveränderlich) |

Die zweite Zeile ist die **Lücke, die Mikes Frage aufgedeckt hat** (2026-11-14): Beide Anfragen prüfen „gibt es schon
eine Honorarnote?“, beide sehen „nein“, weil die andere noch nicht gespeichert hat. Die Sperre aus Abschnitt 5 regelt
nur die Nummer, nicht diese Prüfung. Vorschlag zur Behebung: Projektstand, „Jetzt“.

## 4. Transaktion: alles oder nichts

Bevor wir zur Sperre kommen, noch ein Begriff. Eine **Transaktion** fasst mehrere Datenbank-Schritte zusammen:

- Entweder werden **alle** gespeichert (`COMMIT`), oder **keiner** (`ROLLBACK`). Halb gespeicherte Honorarnoten gibt es
  nicht.
- Solange die Transaktion läuft, sehen **andere Anfragen die Änderungen noch nicht**. Erst nach dem `COMMIT`.

Der zweite Punkt erklärt, warum die zweite Anfrage im Beispiel „noch keine Honorarnote“ sieht: Die erste hat sie zwar
schon angelegt, aber noch nicht committet.

## 5. Sperre (Lock): das „Besetzt“-Schild

Eine **Sperre** ist wie das Schild an einer Toilettentür: Wer hineingeht, dreht auf „besetzt“. Wer danach kommt,
wartet vor der Tür, bis wieder „frei“ ist. Nie sind zwei gleichzeitig drin.

Übertragen auf die Nummern: Wer eine Nummer vergeben will, dreht zuerst das Schild „Nummernvergabe“ auf „besetzt“,
schaut ins Heft, schreibt, trägt ein, dreht auf „frei“. Die zweite Anfrage wartet am Schild, ein paar Millisekunden,
und schaut erst danach ins Heft. Dann steht dort schon die 5, sie nimmt die 6.

### Advisory Lock: ein Schild, dessen Bedeutung wir festlegen

PostgreSQL kennt viele Arten von Sperren. Die meisten hängen an Tabellen oder Zeilen. Ein **Advisory Lock** hängt an
nichts, er ist einfach ein **nummeriertes Schild**, das PostgreSQL für uns verwaltet. Was die Nummer bedeutet,
bestimmt die App. Laut PostgreSQL-Doku: „locks that have application-defined meanings. These are called advisory
locks, because the system does not enforce their use — it is up to the application to use them correctly“
(postgresql.org/docs/18/explicit-locking.html, 13.3.5). „Advisory“ heißt also „empfehlend“: PostgreSQL schützt
damit keine Daten, es merkt sich nur, wer gerade welches Schild hält. Wirksam ist das nur, weil **jede** Stelle, die
Nummern vergibt, vorher dasselbe Schild nimmt.

Unser Schild hat die Nummer `3700001` (`InvoiceIssuer::NUMBER_LOCK`, frei gewählt). Der Ablauf
(`InvoiceIssuer::withNextNumber()`):

```
Anfrage A                                   Anfrage B
Transaktion beginnen                        Transaktion beginnen
Schild 3700001 nehmen        ✓
höchste Nummer lesen: 4                     Schild 3700001 nehmen   … wartet …
Honorarnote 5 anlegen                                               … wartet …
COMMIT (Schild wird frei)                                           ✓ jetzt
                                            höchste Nummer lesen: 5
                                            Honorarnote 6 anlegen
                                            COMMIT (Schild wird frei)
```

Es gibt zwei Sorten dieser Schilder:

- **Für eine Transaktion** (`pg_advisory_xact_lock`, nutzen wir für die Nummern): „automatically released at the end
  of the transaction, and there is no explicit unlock operation“ (selbe Quelle). Man kann also nicht vergessen, es
  zurückzugeben.
- **Für die ganze Verbindung** (`pg_advisory_lock`, nutzt der Session-Handler, Abschnitt 6): „held until explicitly
  released or the session ends“. Hier muss jemand ausdrücklich „frei“ sagen, oder die Verbindung endet.

**Alternative:** `LOCK TABLE invoice` würde die ganze Tabelle sperren, auch für Zahlungen und Erinnerungen, die mit
Nummern nichts zu tun haben. Das Schild sperrt nur, wer dasselbe Schild will.

## 6. Sessions: warum auch dort ein Schild hängt

### Was eine Session ist

HTTP hat kein Gedächtnis: Jede Anfrage kommt für den Server wie von einer fremden Person. Damit Sonja nicht bei jedem
Klick neu einloggen muss, bekommt ihr Browser beim Login ein **Cookie** mit einer langen Zufallszahl, der
**Session-ID**. Zu dieser ID merkt sich der Server einen kleinen Zettel, die **Session**: „das ist Sonja, angemeldet
seit 9:12“, dazu Kleinigkeiten wie die grüne Meldung „Honorarnote erstellt“ für die nächste Seite. Bei uns liegt
dieser Zettel in der Tabelle `sessions` in PostgreSQL (`08-login-und-sessions.md`).

Bei **jeder** Anfrage passiert:

1. Zettel aus der Datenbank lesen
2. Seite erzeugen, dabei vielleicht etwas auf den Zettel schreiben (z. B. eine Meldung)
3. Zettel zurück in die Datenbank schreiben

### Das Problem ist dasselbe wie beim Heft

Kommen zwei Anfragen von Sonja gleichzeitig (Doppelklick, zwei Tabs, später auch das Kursraster, das im Hintergrund
mit `fetch` Anfragen schickt), lesen beide denselben Zettel, ändern ihn unterschiedlich, und wer zuletzt schreibt,
überschreibt die Änderung der anderen. Dann fehlt z. B. eine Meldung, oder im ungünstigen Fall eine Änderung am Login-Zustand.
Deshalb sperrt Symfonys Session-Handler den Zettel vom Lesen (Schritt 1) bis zum Zurückschreiben (Schritt 3). Die
zweite Anfrage wartet so lange.

### Zwei Arten, den Zettel zu sperren

`PdoSessionHandler` bietet dafür Modi an (`vendor/symfony/http-foundation/Session/Storage/Handler/PdoSessionHandler.php`):

- **`LOCK_TRANSACTIONAL`** (Standard): öffnet eine Transaktion und liest die Zeile mit `SELECT … FOR UPDATE`, einer
  echten Zeilensperre. Die Transaktion bleibt offen, bis die Session geschrieben ist, also praktisch die ganze
  Anfrage lang.
- **`LOCK_ADVISORY`** (unsere Wahl): nimmt ein Schild wie in Abschnitt 5, mit einer Nummer, die aus der Session-ID
  berechnet wird. Keine Transaktion.

### Warum bei uns `LOCK_ADVISORY`

Der Session-Handler und Doctrine benutzen **dieselbe Verbindung** zur Datenbank (`config/services.yaml`,
`app.session_pdo`; der Grund war ein Problem in den Tests, siehe `08-login-und-sessions.md`). Eine Verbindung ist wie
**ein** Telefonat mit der Datenbank: Darin kann immer nur eine Transaktion offen sein.

Mit `LOCK_TRANSACTIONAL` hätte der Session-Handler gleich zu Beginn jeder Anfrage eine Transaktion geöffnet und bis
zum Ende offen gelassen. Unsere eigenen Transaktionen (etwa beim Erstellen einer Honorarnote) lägen dann darin, und
das Speichern wäre erst wirklich passiert, wenn der Session-Handler am Ende sein `COMMIT` schickt. Die Symfony-Doku im
Code warnt genau davor: „you have to be careful when you use same database connection that you also use for your
application logic“ (Kommentar zu `LOCK_TRANSACTIONAL`). Mit `LOCK_ADVISORY` gibt es dieses Problem nicht, der Kommentar
dort: „The advantage is it does not require a transaction.“

## 7. Kommen sich die beiden Schilder in die Quere?

Beide Sorten Schilder liegen in derselben „Schildersammlung“ von PostgreSQL. Ein Konflikt entstünde nur, wenn eine
Session zufällig dieselbe Nummer 3700001 bekäme wie die Nummernvergabe. Das kann nicht passieren: Die Session-Nummern
entstehen aus den ersten 8 Zeichen der Session-ID (`PdoSessionHandler::convertStringToInt()`), und weil das Buchstaben
und Ziffern sind, werden diese Zahlen riesig (größer als 2³², also über vier Milliarden). 3700001 liegt weit darunter.

## 8. Und der Deploy?

Mit Deploys hat das alles nichts zu tun. Die Schilder gelten nur für die Dauer **einer** Anfrage, also Millisekunden,
nicht für die Dauer eines Logins. Sonjas Anmeldung bleibt über einen Deploy hinweg erhalten, weil der Session-Zettel
in der Datenbank liegt und nicht im PHP-Container (lokal über einen Container-Neustart geprüft, B2 in `verlauf.md`). Und wird der Container mitten in
einer Anfrage neu gestartet, endet seine Verbindung, und PostgreSQL gibt alle Schilder dieser Verbindung von selbst
frei („until … the session ends“, Abschnitt 5). Schlimmstenfalls bekommt Sonja für diesen einen Klick eine
Fehlerseite.

## Zusammenfassung

| Begriff | Kurz |
|---|---|
| Race Condition | Zwei Vorgänge gleichzeitig, das Ergebnis hängt davon ab, wer schneller ist |
| Anfrage (Request) | Ein Gespräch Browser ↔ Server; zwei Worker können zwei gleichzeitig bearbeiten |
| Transaktion | Alles oder nichts; andere sehen Änderungen erst nach `COMMIT` |
| Sperre (Lock) | „Besetzt“-Schild, wer danach kommt, wartet |
| Advisory Lock | Nummeriertes Schild bei PostgreSQL, Bedeutung legt die App fest |
| Unique-Constraint | Die Datenbank weigert sich, doppelte Werte zu speichern; Sicherheitsnetz, aber mit Fehlerseite |
| Session | Zettel zur Session-ID im Cookie: wer angemeldet ist, Meldungen für die nächste Seite |
