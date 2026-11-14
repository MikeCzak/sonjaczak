# Teilnahmen, Einheiten und Schnupperstunden (C3, C5)

Plan: `docs/planung/datenmodell.md` 3.4 und 3.5. Hier: Umsetzung, Regeln im Code, Fallstricke und was du prüfen
solltest.

## Dateien

| Datei | Inhalt |
|---|---|
| `src/Entity/Enrollment.php`, `src/Entity/Lesson.php` | Teilnahme (Wochentermin, Preis, Abrechnung) und Einheit |
| `src/Entity/Enum/Billing.php`, `LessonStatus.php` | `semester`; `planned`, `held`, `cancelled_by_student`, `cancelled_by_teacher` |
| `src/Migrations/Version20261113092945.php` | Tabellen `enrollment` und `lesson` |
| `src/Lesson/LessonPlanner.php`, `BreakResult.php` | Einheiten erzeugen, Ferien nachträglich anwenden |
| `src/Lesson/MakeUpRule.php` | Wann eine Nachholstunde fällig ist |
| `src/Controller/Admin/EnrollmentController.php`, `LessonController.php` | Teilnahme anlegen/bearbeiten/löschen, Einheit markieren, Nachholstunde |
| `src/Security/Voter/EnrollmentVoter.php`, `CourseVoter.php` | Löschen erlaubt? |
| `tests/Lesson/*`, `tests/Admin/EnrollmentTest.php`, `tests/Factory/EnrollmentFactory.php` | Tests |

## Datenbank

```sql
CREATE TABLE enrollment (…, weekday SMALLINT, start_time TIME(0) WITHOUT TIME ZONE, price_cents INT,
    billing VARCHAR(20), bill_to_guardian BOOLEAN, course_id INT NOT NULL, student_id INT NOT NULL)
CREATE UNIQUE INDEX uniq_enrollment_course_student ON enrollment (course_id, student_id)
CREATE TABLE lesson (…, starts_at TIMESTAMP(0) WITHOUT TIME ZONE, duration_minutes SMALLINT, status VARCHAR(30),
    note TEXT, from_trial BOOLEAN DEFAULT false, enrollment_id INT NOT NULL, make_up_for_id INT NULL)
ALTER TABLE lesson ADD … FOREIGN KEY (make_up_for_id) REFERENCES lesson (id) ON DELETE SET NULL
```

- **Unique über zwei Spalten:** `#[ORM\UniqueConstraint(fields: ['course', 'student'])]` legt den Index an,
  `#[UniqueEntity(fields: ['course', 'student'])]` prüft vorher im Validator und zeigt eine lesbare Meldung. Beides
  braucht es: der Validator allein hat ein Zeitfenster (zwei gleichzeitige Anfragen), die Datenbank allein liefert nur
  eine Exception.
- **Zeiten ohne Zeitzone:** `TIMESTAMP WITHOUT TIME ZONE` mit PHP-Zeitzone `Europe/Vienna` speichert die Uhrzeit, wie
  sie an der Wand steht. Eine Einheit um 15:00 bleibt um 15:00, auch über die Umstellung auf Sommerzeit. Mit
  `TIMESTAMPTZ` wäre ein UTC-Zeitpunkt gespeichert; für Termine vor Ort ist die Wanduhr das Richtige.
- **Selbstreferenz `makeUpFor`** (Einheit → Einheit): eine normale `ManyToOne` auf dieselbe Klasse. `ON DELETE SET
  NULL`: Wird die abgesagte Einheit gelöscht (z. B. mit der ganzen Teilnahme), verliert die Nachholstunde nur den
  Verweis. Ohne das müsste Doctrine beim Löschen einer Teilnahme die Reihenfolge innerhalb derselben Tabelle genau
  treffen. Das ist **keine** Kaskade: es wird nichts mitgelöscht. Getestet in
  `EnrollmentTest::testCourseWithEnrollmentsCannotBeDeletedButEnrollmentCan`.
- **Einheiten gehören zur Teilnahme** (`cascade: ['persist', 'remove']`, `orphanRemoval`), wie Ferien zum Kurs
  (`10-datenbank-kurse.md`). Teilnahmen selbst werden **nicht** mit dem Kurs gelöscht: `CourseVoter::DELETE` verbietet
  das Löschen eines Kurses mit Teilnahmen, der Fremdschlüssel ebenfalls.
- **Inverse Side `Course::$enrollments`** mit `OrderBy(['weekday', 'startTime'])`: die Kursseite listet die Teilnahmen
  nach Termin, ohne eigene Abfrage.

## Einheiten erzeugen (`LessonPlanner`)

`fillUp()`: ab `course.startsOn` der erste passende Wochentag, dann wöchentlich; Ferientage werden übersprungen,
höchstens bis `course.endsOn`, bis die Teilnahme `plannedLessons` **reguläre** Einheiten hat. Regulär heißt: jeder
Status, nur Nachholstunden zählen nicht. Wochen, die schon eine reguläre Einheit haben, werden übersprungen; ein zweiter
Aufruf füllt also nur Lücken.

**Warum nicht nach der Nachholregel zählen?** Zuerst habe ich das so gebaut, und der Test hat es aufgedeckt: Dann füllt
der Planer jede Absage durch Sonja hinten wieder auf, und die Nachholregel mit Schwellwert wäre wirkungslos. Ob für
Sonjas Absage nachgeholt wird, entscheidet jetzt nur `MakeUpRule`.

`applyBreaks()` (nach neuen Ferien, auch nach „Ferien vorschlagen lassen“): geplante Einheiten in den Ferien werden
gelöscht, gehaltene oder abgesagte bleiben und erscheinen als Hinweis („bitte prüfen“). Danach `fillUp()`: für jede
gelöschte Einheit kommt eine am Ende dazu, solange das Kursende es zulässt.

Werden Ferien **gelöscht**, passiert nichts: die Teilnahme hat ihre 15 Einheiten schon.

## Nachholregel (`MakeUpRule`)

Gezählt werden alle Einheiten außer `cancelled_by_teacher` (also auch geplante, gehaltene, vom Schüler abgesagte und
Nachholstunden). Fällig sind `plannedLessons − Schwellwert − gezählt` Nachholstunden, mindestens 0. Anzeige: „14 von 15“
und „1 Nachholstunde fällig“ auf Kurs- und Teilnahmeseite. Eine Nachholstunde wird zu einer bestimmten abgesagten
Einheit eingetragen (`makeUpFor`); jede abgesagte Einheit kann nur einmal nachgeholt werden.

## Oberfläche ohne JavaScript

- **Teilnahme anlegen in zwei Schritten:** Erst Schüler:in wählen (GET-Formular, Auswahl landet in der URL), dann das
  Formular. Nur so lässt sich „Honorarnote an den Elternteil“ ohne JavaScript passend vorbelegen (minderjährig bei
  Kursbeginn und Elternteil eingetragen). Bereits eingetragene Schüler:innen werden nicht angeboten
  (`StudentRepository::notEnrolledIn()`, Unterabfrage in DQL).
- **Einheit markieren** über eine eigene Seite (`/admin/einheiten/{id}`). Das ist der Zwischenstand bis zum Kursraster
  (C4), das genau das in der Zelle erledigen soll.
- **Fallstricke in den Tests:** `$client->submitForm()` schickt **immer POST**, auch bei `<form method="get">`, solange man die
  Methode nicht als drittes Argument übergibt (`vendor/symfony/browser-kit/AbstractBrowser.php`, `submitForm()`).
  Und `assertCheckboxNotChecked()` besteht auch, wenn es die Checkbox gar nicht gibt; deshalb prüft der Test vorher, dass
  sie existiert.

## Schnupperstunden (C5)

Dateien: `src/Entity/TrialLesson.php`, `src/Lesson/TrialTakeover.php`, `src/Controller/Admin/TrialLessonController.php`,
`templates/admin/trial/`, Migration `Version20261113094318`, `tests/Admin/TrialLessonTest.php`.

- **Eigene Entity** (`datenmodell.md` 3.6): Schüler:in, Beginn, Dauer, Notiz. Kein Kurs, kein Preis.
- **Übernahme:** Beim Anlegen einer Teilnahme bietet das Formular die Schnupperstunden dieser Person an (alle
  vorausgewählt, Feld `trials`, nicht gemappt). `TrialTakeover::enroll()` legt je Schnupperstunde eine Einheit mit
  Status `held` und `fromTrial = true` an, löscht die Schnupperstunde und erzeugt dann die übrigen Einheiten. Die
  übernommene zählt mit: 1 + 14.
- **Transaktion:** `wrapInTransaction()` beginnt eine Transaktion, ruft die Funktion auf, macht ein `flush()` und
  `commit()`; bei einer Exception `rollBack()` und der EntityManager wird geschlossen
  (`vendor/doctrine/orm/src/EntityManager.php`, `wrapInTransaction()`). Ehrlich gesagt wäre ein einzelnes `flush()`
  schon atomar (Doctrine schreibt einen Flush immer in einer Transaktion). Die ausdrückliche Klammer macht die Absicht
  sichtbar und bleibt richtig, falls später ein Schritt mit eigenem Flush dazukommt.
- **Löschen einer Person:** Schnupperstunden gehen mit (`cascade: ['remove']` an `Student::$trialLessons`): Wer nach
  dem Schnuppern nicht bleibt, wird ganz gelöscht (`datenmodell.md` 5). Personen **mit Teilnahmen** lassen sich nicht
  löschen (`StudentVoter`); ohne diese Regel hätte der Fremdschlüssel `enrollment.student_id` das Löschen mit einem
  Datenbankfehler (Fehlerseite 500) abgebrochen. Das war eine Lücke seit C1, jetzt geschlossen.
