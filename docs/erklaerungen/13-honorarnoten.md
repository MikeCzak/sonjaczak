# Honorarnoten, Zahlungen, Gutschriften, Erinnerungen und Einnahmen (D1, D3–D5)

Plan: `docs/planung/datenmodell.md` 3.7 und 3.8. Seiten: `/admin/honorarnoten` (Liste je Jahr),
`/admin/honorarnoten/{id}`, erstellen auf der Seite einer Teilnahme (`/admin/teilnahmen/{id}/honorarnote`).

## Dateien

| Datei | Inhalt |
|---|---|
| `src/Entity/Invoice.php`, `src/Entity/Payment.php`, `src/Entity/Embeddable/Recipient.php`, `src/Entity/Enum/PaymentMethod.php` | Entities |
| `src/Migrations/Version20261113095543.php` | Tabellen `invoice` und `payment` |
| `src/Invoice/InvoiceIssuer.php`, `src/Invoice/InvoiceDraft.php` | Erstellen mit Nummernvergabe |
| `src/Controller/Admin/InvoiceController.php`, `src/Form/Admin/InvoiceType.php`, `templates/admin/invoice/` | Oberfläche |
| `tests/Admin/InvoiceTest.php`, `tests/Factory/InvoiceFactory.php` | Tests |

## Unveränderlich: Konstruktor statt Setter

`Invoice` und `Payment` bekommen alle Werte im Konstruktor und haben **keine Setter**. Die Doctrine-Attribute
stehen direkt an den Konstruktor-Parametern (Constructor Property Promotion); Doctrine liest sie dort genauso wie an
normalen Eigenschaften. Beim Laden ruft Doctrine den Konstruktor nicht auf, sondern setzt die Werte direkt.

Weil ein Formular ohne Setter nichts in die Entity schreiben kann, füllt das Formular eine eigene Klasse
`InvoiceDraft` (Datum, Fälligkeiten, Leistungstext). `InvoiceIssuer` baut daraus die Honorarnote.

Ganz „unveränderlich“ ist das nur im PHP-Code: In der Datenbank könnte jemand die Zeile ändern. Ob das für die
Aufbewahrungspflichten reicht, gehört zur Prüfung der BAO in Abschnitt F; eine technisch erzwungene
Unveränderbarkeit (Trigger, Signatur) wäre ein eigenes Thema.

## Empfänger als Kopie: verschachteltes Embeddable

`Recipient` (Name + `Address`) ist ein Embeddable **in** einem Embeddable. Doctrine legt die Spalten mit beiden
Präfixen an: `recipient_name`, `recipient_address_street`, `recipient_address_postal_code` usw. Der Konstruktor und
`getAddress()` arbeiten mit `clone`: Würde `Recipient` dasselbe `Address`-Objekt wie die Schüler:in halten, würde eine
spätere Adressänderung über Doctrines Änderungserkennung auch die Honorarnote ändern. Der Test
`InvoiceTest::testRecipientIsACopy` prüft genau das.

An wen? `Enrollment::isBillToGuardian()` → Elternteil (dessen Namensfeld, z. B. „Anna und Peter Beispiel“), sonst
die Schüler:in.

## Nummer je Jahr: Advisory Lock statt Wiederholung

Die nächste Nummer ist `MAX(number) + 1` des Ausstellungsjahres (`InvoiceRepository::nextNumber()`). Zwei
gleichzeitige Anfragen würden dasselbe Maximum lesen.

- **Geplant war** (`datenmodell.md` 3.7): Der Unique-Constraint auf `(year, number)` lässt die zweite scheitern, die
  App versucht es erneut. **Fallstrick:** Nach einem fehlgeschlagenen `flush()` ist Doctrines EntityManager
  geschlossen. Ein erneuter Versuch bräuchte einen neuen EntityManager, und alle bereits geladenen Objekte
  (Teilnahme, Schüler:in) gehörten dann nicht mehr dazu. Das wird schnell unübersichtlich.
- **Umgesetzt:** `InvoiceIssuer::issue()` öffnet eine Transaktion (`wrapInTransaction()`) und nimmt als Erstes
  `SELECT pg_advisory_xact_lock(3700001)`. Eine zweite Anfrage wartet an dieser Stelle, bis die erste ihre Transaktion
  abgeschlossen hat, und liest erst dann das neue Maximum. Die Sperre gilt „until the current transaction ends“,
  also bis Commit oder Rollback; manuell freigeben lässt sie sich nicht (Quelle: postgresql.org/docs/18/functions-admin.html,
  „Advisory Lock Functions“).
  Der Unique-Constraint bleibt als Sicherheitsnetz.
- **Alternative:** `LOCK TABLE invoice IN EXCLUSIVE MODE` sperrt die ganze Tabelle (auch für andere Schreibzugriffe).
  Der Advisory Lock sperrt nur das, was ihn ebenfalls anfordert, also nur die Nummernvergabe.
- **Verträgt sich das mit den Sessions?** Der Session-Handler nutzt ebenfalls Advisory Locks auf derselben Verbindung
  (`docs/erklaerungen/08`). Seine Schlüssel entstehen aus den ersten 8 Zeichen der Session-ID
  (`PdoSessionHandler::convertStringToInt()`). Weil das druckbare Zeichen sind, liegen sie über 2³² und können 3700001
  nicht treffen. (Auf 32-Bit-Systemen nimmt der Handler die Form mit zwei Schlüsseln; laut Doku überschneiden sich die
  beiden Schlüsselräume nicht.)
- Ehrlich gesagt ist ein gleichzeitiges Erstellen bei einer Benutzerin selten. Teuer ist die Absicherung nicht (eine
  Abfrage), und doppelte Nummern wären bei Belegen ärgerlich.

## Was geprüft wird, bevor eine Honorarnote entsteht

`InvoiceIssuer::problems()` liefert Übersetzungsschlüssel, die Seite zeigt sie mit Links zum Beheben:
schon eine Honorarnote vorhanden (eine je Teilnahme; Gutschriften kommen in D3), Absenderdaten in den Einstellungen
unvollständig (Name, Adresse, IBAN), kein Preis, Elternteil gewünscht aber nicht eingetragen, Adresse unvollständig.
Der Controller prüft das auch beim Absenden, ein POST an der Seite vorbei erzeugt nichts (Test).

## Raten

Ohne „2. Rate bis“ ist der ganze Betrag bis „Zahlbar bis“ fällig. Mit Datum wird der Betrag geteilt, nicht
gespeichert: erste Rate `intdiv(Betrag + 1, 2)`, zweite der Rest. Bei 495,01 € also 247,51 € + 247,50 €.

## Löschen

- Eine Teilnahme mit Honorarnote ist nicht löschbar (`EnrollmentVoter`, Grund `enrollment.delete.has_invoices`).
  Damit sind auch Schüler:in und Kurs geschützt (beide sind mit Teilnahmen schon nicht löschbar).
- Honorarnoten selbst haben keinen Löschknopf und keine Bearbeiten-Seite.
- `Enrollment::$invoices` ist die inverse Seite **ohne** `cascade`: anders als die Einheiten gehören Honorarnoten
  nicht so zur Teilnahme, dass sie mit ihr verschwinden dürften.

## Zahlungen und Gutschriften (D3)

Dateien: `src/Invoice/PaymentDraft.php`, `src/Invoice/CreditDraft.php`, `src/Form/Admin/PaymentType.php`,
`src/Form/Admin/CreditType.php`, `src/Security/Voter/InvoiceVoter.php`, `src/Entity/Enum/InvoiceStatus.php`,
Migration `Version20261113100141` (Spalte `invoice.credits_id`), `tests/Admin/PaymentTest.php`.

- **Zahlung erfassen:** Auf der Seite der Honorarnote steht ein Formular, vorbelegt mit dem offenen Betrag, heute und
  „Überweisung“. „Ganz bezahlt, heute“ ist damit ein Klick; Betrag, Datum und Zahlungsart lassen sich ändern (z. B. die
  erste Rate oder eine Zahlung vom 30.12., die erst im Jänner eingetragen wird). Nicht mehr als offen, kein Datum in
  der Zukunft (das Datum entscheidet über das Jahr der Einnahme).
- **Status berechnet** (`Invoice::getStatus()`): offen, teilweise bezahlt, bezahlt, Rückzahlung offen. Nicht
  gespeichert, damit er nie veraltet (`datenmodell.md` 3.8). Die Liste lädt dafür die Zahlungen je Honorarnote nach;
  bei einigen Dutzend Honorarnoten im Jahr ist das unerheblich.
- **Gutschrift** (`InvoiceIssuer::credit()`): eigene Nummer aus derselben Nummernvergabe (gleicher Lock), negativer
  Betrag, Empfänger von der ursprünglichen Honorarnote, Verweis `credits`. Höchstens der noch nicht gutgeschriebene
  Betrag; zu einer Gutschrift gibt es keine weitere (`InvoiceVoter::CREDIT`, Gründe `credit.denied.*`). Fällig am
  Ausstellungstag, ohne Raten (die Spalte `due_on` ist Pflicht und hat bei einer Gutschrift keine eigene Bedeutung).
- **Ein Konto je Honorarnote mit ihren Gutschriften:** zu zahlen = Betrag + Gutschriften (negativ), bezahlt = Summe
  aller Zahlungen der Gruppe. Damit gehen beide Fälle auf:
  - Gutschrift **vor** der Zahlung (z. B. Krankheit vor Semesterbeginn): Es ist einfach weniger zu zahlen.
  - Gutschrift **nach** der Zahlung: „Rückzahlung offen“. Die Rückzahlung wird als negative Zahlung an der Gutschrift
    erfasst (`datenmodell.md` 3.7), so stimmt die Summe der Einnahmen eines Jahres nach `paidOn`.
- **Zahlungen löschen** geht (Knopf in der Liste, ohne Rückfrage): Eine Zahlung ist Sonjas eigene Aufzeichnung, kein
  Beleg; ein Tippfehler soll sich beheben lassen.
- `Payment` und `Invoice` halten die inverse Seite selbst aktuell (`new Payment(...)` fügt sich in
  `$invoice->getPayments()` ein, eine Gutschrift in `$original->getCreditNotes()`). So stimmt der Status auch im selben
  Request, ohne Neuladen.

## Erinnerung bei überschrittener Frist (D4)

Dateien: `src/Command/RemindOverdueInvoicesCommand.php`, `templates/email/invoice/overdue.txt.twig`, Migration
`Version20261113100635` (Spalte `invoice.reminded_for`), `tests/Command/RemindOverdueInvoicesCommandTest.php`.

- **Fristen** (`Invoice::getMissedDeadline()`): mit Raten bis `dueOn` mindestens die erste Rate (höchstens das nach
  Gutschriften Geschuldete), bis zum zweiten Datum alles; ohne Raten alles bis `dueOn`. Überfällig erst **nach** dem
  Tag der Frist. Gutschriften haben keine Frist.
- **Eine Mail je Frist:** `remindedFor` speichert die Frist der letzten Erinnerung. Erinnert wird nur, wenn die
  verpasste Frist später liegt. `remindedFor` ist die einzige Spalte der Honorarnote, die sich ändert
  (`markReminded()`); sie gehört nicht zum Beleg, sondern zur Buchhaltung der App.
- **Erst senden, dann merken:** Scheitert der Versand, bleibt `remindedFor` unverändert und der nächste Lauf versucht
  es wieder.
- **Uhr:** Der Befehl fragt `Psr\Clock\ClockInterface` nach „heute“ statt `new \DateTimeImmutable()`. In Tests setzt
  `ClockSensitiveTrait::mockTime()` die Uhr fest (`symfony/clock`, jetzt direkt in `composer.json`, Version 8.1.9
  unverändert). Der Dienst `clock` fragt die globale Uhr, die `mockTime()` ersetzt
  (`vendor/symfony/clock/Clock.php`, `now()`).
- **Fallstrick PHPStan:** Eine Hilfsmethode im Test, die bei jedem Aufruf etwas anderes liefert, braucht
  `@phpstan-impure`; sonst nimmt PHPStan an, dass sie dasselbe liefert, und meldet `assertSame(1, …)` nach
  `assertSame(0, …)` als „always false“.
- **Datenschutz:** Die Mail an Sonja enthält Nummer, Name und Betrag (sie braucht sie). Die Ausgabe des Befehls (Cron,
  Log) nennt nur die Anzahl.
- **Am Server:** Cronjob in `docs/deployment.md` B8 vorbereitet, **nicht eingerichtet** (Änderung am Server, wartet
  auf Freigabe).

## Export der Einnahmen (D5)

Dateien: `src/Invoice/IncomeCsv.php`, `src/Controller/Admin/IncomeController.php`, `templates/admin/income/`,
`PaymentRepository::findByYear()`, `tests/Admin/IncomeTest.php`. Seite: `/admin/einnahmen`, Download
`/admin/einnahmen/2026.csv`.

- **Jahr nach Zahlungsdatum:** § 19 Abs. 1 EStG: „Einnahmen sind in jenem Kalenderjahr bezogen, in dem sie dem
  Steuerpflichtigen zugeflossen sind.“ (Quelle: RIS, EStG 1988 § 19). Eine Rate vom 30.12.2026 zählt also zu 2026,
  die vom 04.01.2027 zu 2027, auch wenn beide zur Honorarnote 16/2026 gehören. **Achtung:** § 19 Abs. 1 Z 1 enthält
  eine Ausnahme für „regelmäßig wiederkehrende Einnahmen“ kurz vor Beginn oder nach Ende des Kalenderjahres, zu dem
  sie wirtschaftlich gehören. Ob Semesterbeiträge darunter fallen, kann ich nicht beurteilen; das bitte Sonjas
  Steuerberatung fragen. Der Export liefert das Zahlungsdatum, die Zuordnung bleibt damit nachvollziehbar.
- **Format für Excel:** Semikolon als Trennzeichen, Dezimalkomma ohne Tausenderpunkt und ohne €-Zeichen (Excel
  erkennt die Zahl), Datum `TT.MM.JJJJ`, UTF-8 mit BOM (das unsichtbare Zeichen `U+FEFF` am Anfang, an dem Excel
  UTF-8 erkennt; sonst stehen statt Umlauten Zeichensalat), Zeilenende `\r\n`. Rückzahlungen erscheinen negativ.
  Mit Sonjas Excel noch nicht geprüft.
- **`fputcsv(..., escape: '')`:** PHP würde sonst Backslashes als eigenes Escape-Zeichen behandeln, das Excel nicht
  kennt; der bisherige Standardwert ist seit PHP 8.4 als veraltet markiert.
- **CSV-Injection:** Ein Text, der mit `=`, `+`, `-` oder `@` beginnt, wäre in Excel eine Formel (z. B. ein
  `HYPERLINK`). Textzellen bekommen dann ein `'` davor, das Excel ausblendet. Beträge sind davon ausgenommen (eine
  Rückzahlung beginnt mit `-` und soll eine Zahl bleiben). Test `IncomeTest::testFormulasAreNeutralised`.
- **Fallstrick Doctrine:** Bei einer skalaren Abfrage (`getSingleColumnResult()`) wandelt Doctrine Datumswerte nicht
  in Objekte um; `PaymentRepository::years()` bekommt den String `2026-12-30` der Datenbank. Der erste Testlauf
  scheiterte genau daran (TypeError, Fehlerseite 500).
- Die Datei wird nicht gespeichert, sondern bei jedem Download erzeugt, mit `Cache-Control: private, no-store`.

## Zum Durchsehen

1. **Advisory Lock statt Wiederholung** bei der Nummernvergabe (siehe oben): Abweichung vom Datenmodell, bitte
   bestätigen.
2. **Fälligkeiten ohne Vorschlag:** „Zahlbar bis“ ist leer und muss eingetragen werden. Im Muster liegen zwischen
   Ausstellung und Zahlungsziel 6 Tage; eine feste Frist als Vorschlag (z. B. 14 Tage) wäre eine Einstellung, wenn
   Sonja das möchte.
3. **Betrag nicht änderbar** beim Erstellen: Er kommt aus der Teilnahme (dort für Ermäßigungen änderbar). So kann
   der Betrag der Honorarnote nicht versehentlich vom Preis der Teilnahme abweichen.
4. **Leistungstext** „Gesangsunterricht im Wintersemester 2026/27“ ist vorbelegt und änderbar; das PDF (D2) setzt
   „Für den“ davor wie im Muster.
5. **Gutschrift und Zahlung als ein Konto** (D3, siehe oben): eine Auslegung von `datenmodell.md` 3.7, bitte
   bestätigen. Offen ist auch, ob Sonjas Steuerberatung Gutschriften so haben möchte (Antwort 8 im Datenmodell).
6. **Zahlungen ohne Rückfrage löschbar** (D3).
7. **Empfänger der Erinnerung** ist `music@sonjaczak.com` (dieselbe Adresse wie die Anfragen). Eine eigene Einstellung
   dafür wäre leicht ergänzt.
8. **Ausnahme für regelmäßig wiederkehrende Einnahmen** (§ 19 Abs. 1 Z 1 EStG) bei Zahlungen um den Jahreswechsel:
   mit der Steuerberatung klären; der Export ordnet streng nach Zahlungsdatum zu.
9. **CSV mit Sonjas Excel prüfen** (Umlaute, Beträge als Zahl, Datum).
