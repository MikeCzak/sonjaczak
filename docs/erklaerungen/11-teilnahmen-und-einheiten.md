# Teilnahmen, Einheiten und Schnupperstunden (C3, C5)

Plan: `docs/planung/datenmodell.md` 3.4 und 3.5. Hier: Umsetzung, Regeln im Code, Fallstricke und was du prüfen
solltest.

## Dateien

| Datei | Inhalt |
|---|---|
| `src/Entity/Enrollment.php`, `src/Entity/Lesson.php` | Teilnahme (Wochentermin, Preis, Abrechnung) und Einheit |
| `src/Entity/Enum/Billing.php`, `CancelledBy.php`, `LessonState.php` | `semester`; `student`, `teacher` (gespeichert, seit 2026-11-16); `planned`, `held`, `cancelled_by_*` (nur angezeigt, abgeleitet) |
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
der Planer jede Absage durch Sonja hinten wieder auf, und die Nachholregel wäre wirkungslos. Ob für
Sonjas Absage nachgeholt wird, entscheidet jetzt nur `MakeUpRule`.

`applyBreaks()` (nach neuen Ferien, auch nach „Ferien vorschlagen lassen“): zukünftige, nicht abgesagte Einheiten in
den Ferien werden gelöscht, vergangene oder abgesagte bleiben und erscheinen als Hinweis („bitte prüfen“). Seit
2026-11-16 entscheidet dafür die Zeit (`$now` als Argument), weil „gehalten“ nicht mehr gespeichert ist. Zwischenstand:
geplant ist, dass alle Einheiten im Zeitraum nach einer Rückfrage gelöscht werden (`umsetzung-admin.md` C4, Frage 6). Danach `fillUp()`: für jede
gelöschte Einheit kommt eine am Ende dazu, solange das Kursende es zulässt.

Werden Ferien **gelöscht**, passiert nichts: die Teilnahme hat ihre 15 Einheiten schon.

## Nachholregel (`MakeUpRule`)

Gezählt werden alle Einheiten außer `cancelled_by_teacher` (also auch geplante, gehaltene, vom Schüler abgesagte und
Nachholstunden). Fällig sind `plannedLessons − gezählt` Nachholstunden, mindestens 0. Anzeige: „14 von 15“
und „1 Nachholstunde fällig“ auf Kurs- und Teilnahmeseite. Eine Nachholstunde wird zu einer bestimmten abgesagten
Einheit eingetragen (`makeUpFor`); jede abgesagte Einheit kann nur einmal nachgeholt werden.

**Geändert 2026-11-15:** Bis dahin gab es eine Einstellung „Schwellwert“, und fällig waren
`plannedLessons − Schwellwert − gezählt`. Mike hat geklärt: Die Mindestzahl, die Sonja schuldet, ist **immer** die Zahl
der geplanten Einheiten des Kurses. Mehr Einheiten zu halten ist erlaubt, weniger nicht. Ein Abstand davon ergibt also
keinen Sinn. Statt des Schwellwerts gibt es in den Einstellungen „Einheiten pro Semester“ als Vorschlag für neue Kurse
(vorher fest 15 in `Course`).

**Fallstrick bei der Migration** (`Version20261115201517`): `doctrine:migrations:diff` schlug nur
`RENAME COLUMN make_up_threshold TO planned_lessons` vor. Der Vergleich sieht nur das Schema: Eine Spalte
verschwindet, eine mit gleichem Typ kommt dazu, also hält er das für eine Umbenennung. Damit wäre der alte Wert
übernommen worden (Schwellwert 0 → „0 Einheiten pro Semester“, ungültig). Die Bedeutung kennt nur der Mensch. Deshalb
setzt die Migration den Wert danach ausdrücklich auf 15. Allgemein: Den erzeugten Diff immer lesen, bevor er läuft,
besonders bei Umbenennungen und bei `NOT NULL`-Spalten in Tabellen mit Daten.

## Status: nur die Absage wird gespeichert (2026-11-16)

Bis 2026-11-16 hatte `Lesson` eine Spalte `status` mit `planned`, `held`, `cancelled_by_student`,
`cancelled_by_teacher`. Mikes Einwand: Dass eine Einheit **existiert**, heißt schon „geplant“, und „gehalten“ folgt aus
der Zeit. Gespeichert wird seitdem nur, **wer abgesagt hat** (`cancelledBy`, nullable Enum `CancelledBy`).

- **Grundsatz:** Einen Fakt, der sich aus anderen ableiten lässt, nicht zusätzlich speichern. Sonst können beide
  auseinanderlaufen (eine Stunde „geplant“ im Juli des Vorjahres) und jemand muss sie abgleichen (Sonja per Klick oder
  ein Cronjob).
- **Abgeleitet:** `Lesson::state($now)` liefert `LessonState` (`planned`, `held`, `cancelled_by_student`,
  `cancelled_by_teacher`) nur für die Anzeige. „Gehalten“ heißt: nicht abgesagt und das **Ende** (Beginn + Dauer)
  vorbei; eine laufende Stunde ist noch nicht gehalten. In Twig: `lesson_state(lesson)` (`App\Twig\LessonStateExtension`,
  Attribut `#[AsTwigFunction]`, mit `ClockInterface`, damit Tests die Zeit festlegen können).
- **NULL statt eigenem Wert:** „Nicht abgesagt“ ist das Fehlen einer Absage, also `NULL`. Die Alternative wäre ein
  Wert `none` in einer nicht-nullbaren Spalte. **Fallstrick:** In SQL und DQL ist `cancelled_by = NULL` nie wahr (ein
  Vergleich mit `NULL` ergibt „unbekannt“), richtig ist `IS NULL` (`LessonRepository::findPlannedBetween()`).
- **Migration** (`Version20261116110522`): `doctrine:migrations:diff` schlug `ADD cancelled_by` und `DROP status` vor,
  weil Name, Typ und Länge verschieden sind. Das hätte alle Absagen gelöscht. Von Hand: neue Spalte, Absagen kopieren,
  alte löschen. Beim Rückweg wird aus „gehalten“ „geplant“ (nicht mehr unterscheidbar), und die Spalte bekommt zuerst
  einen `DEFAULT`, weil `ADD … NOT NULL` auf einer Tabelle mit Zeilen scheitert. Lokal hin und zurück geprüft
  (88 Einheiten: 22 gehalten + 66 geplant → 88 `NULL`, die zwei Absagen blieben).
- **Vergleich zur Migration davor** (Schwellwert, Abschnitt „Nachholregel“): Dort hielt Doctrine zwei verschiedene
  Spalten für eine Umbenennung, hier eine Umbenennung für zwei verschiedene Spalten. Der Diff vergleicht nur das Schema,
  die Bedeutung der Daten kennt er nicht.
- **Tests, die am Datum hingen:** Ein Test trug Ferien am 16.10.2026 ein und erwartete, dass die Einheit verschwindet.
  Seit heute liegt der Tag in der Vergangenheit, und vergangene Einheiten bleiben. Der Test legt die Zeit jetzt mit
  `mockTime()` fest (`ClockSensitiveTrait`).

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
  `fromTrial = true` an (seit 2026-11-16 ohne Status: sie liegt in der Vergangenheit und gilt damit als gehalten), löscht die Schnupperstunde und erzeugt dann die übrigen Einheiten. Die
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
