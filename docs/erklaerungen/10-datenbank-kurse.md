# Datenbank: Kurse und Ferien (C2)

Plan: `docs/planung/datenmodell.md` 3.3. Hier: Umsetzung, Fallstricke, Alternativen.

## Dateien

| Datei | Inhalt |
|---|---|
| `src/Entity/Course.php`, `src/Entity/CourseBreak.php` | Kurs und seine Ferien/freien Tage |
| `src/Entity/Enum/Term.php`, `BreakSource.php` | Backed Enums: `winter`/`summer`, `manual`/`imported` |
| `src/Migrations/Version20261113092232.php` | Tabellen `course` und `course_break` |
| `src/Course/HolidayImporter.php` | Ferien-Vorschlag aus der OpenHolidays API |
| `config/packages/framework.yaml` | Scoped HTTP-Client `openholidays.client`; in Tests mit aufgezeichneten Antworten |
| `src/Controller/Admin/CourseController.php`, `src/Form/Admin/CourseType.php`, `CourseBreakType.php` | Oberfläche |
| `src/Security/Voter/CourseVoter.php` | Löschen nur ohne Teilnahmen (Regel kommt mit C3) |
| `tests/Course/HolidayImporterTest.php`, `tests/Admin/CourseTest.php`, `tests/Http/OpenHolidaysResponses.php`, `tests/Fixtures/openholidays/` | Tests |

## Enums in der Datenbank

`#[ORM\Column(length: 10, enumType: Term::class)]` speichert den **Wert** des Backed Enums (`'winter'`) als
`VARCHAR(10)`. Doctrine wandelt beim Laden zurück in `Term::Winter`. Vorteile gegenüber einem freien String: Tippfehler
sind unmöglich, und `match ($term)` prüft PHPStan auf Vollständigkeit.

**Fallstrick:** Den Wert eines Falls später umzubenennen (`'winter'` → `'ws'`) braucht eine Datenmigration
(`UPDATE course SET term = 'ws' …`), sonst lassen sich alte Zeilen nicht mehr laden. Die Namen der Fälle (`Winter`) sind
dagegen frei änderbar. PostgreSQL hätte auch einen eigenen `ENUM`-Typ; den nutzt Doctrine nicht, und Änderungen daran
wären umständlicher als bei `VARCHAR`.

Titel und Kurzform kommen aus dem Enum: `Term::yearLabel()` (`2026/27` bzw. `2027`), `Term::abbreviation()` (`WiSe`,
für Dateinamen der Honorarnoten, nicht übersetzt), Übersetzung `course.term.winter` = „Wintersemester %year%“.

## Geld in Cent

`priceCents` ist ein `INT`: 495,00 € = `49500`. Mit `float` entstehen Rundungsfehler (`0.1 + 0.2 != 0.3`), bei Beträgen
inakzeptabel. Im Formular rechnet `MoneyType` mit `divisor: 100` in beide Richtungen um; Sonja tippt „495,00“.
Alternative wäre `DECIMAL(10,2)`: Doctrine liefert es als String, man rechnet dann mit `bcmath`. Cent als Ganzzahl ist
einfacher, solange keine Bruchteile von Cent vorkommen.

## Course ↔ CourseBreak: hier mit Cascade

```php
#[ORM\OneToMany(targetEntity: CourseBreak::class, mappedBy: 'course', cascade: ['persist', 'remove'], orphanRemoval: true)]
private Collection $breaks;
```

- **`cascade: ['persist']`:** `$course->addBreak($break)` reicht; Doctrine speichert den neuen Eintrag beim `flush()`
  mit, ohne eigenes `persist($break)`. Der Importer kann so Einträge anhängen, ohne den EntityManager zu kennen.
- **`orphanRemoval: true`:** `$course->removeBreak($break)` löscht die Zeile. Ohne diese Option würde Doctrine nur
  `course_id` auf `NULL` setzen wollen, und das verbietet `nullable: false`.
- **`cascade: ['remove']`:** Wird der Kurs gelöscht, gehen seine Ferien mit.
- **Warum hier und sonst nicht?** `datenmodell.md` 5 sagt „keine Kaskaden“, damit keine Belege verschwinden. Ferien
  sind aber kein eigenständiger Datensatz, sondern Teil des Kurses (Komposition), wie die Passwort-Links am Konto. Die
  Kaskade passiert in Doctrine (PHP), nicht in der Datenbank: Der Fremdschlüssel hat weiterhin kein `ON DELETE`. Löscht
  jemand direkt per SQL einen Kurs mit Ferien, verweigert PostgreSQL das.
- **`addBreak()` setzt beide Seiten:** Owning Side ist `CourseBreak::$course`. `addBreak()` hält die Collection und den
  Verweis synchron, damit innerhalb derselben Anfrage beides stimmt.

## Ferien-Vorschlag (OpenHolidays)

Ablauf beim Anlegen eines Kurses (`CourseController::new()`): Kurs speichern vorbereiten (`persist`), Importer holt
`/SchoolHolidays` (mit `subdivisionCode=AT-SM`) und `/PublicHolidays` für den Kurszeitraum, hängt die Einträge an
(`source = imported`), dann ein `flush()` für alles. Auf der Kursseite gibt es „Ferien und Feiertage vorschlagen
lassen“, falls die API beim Anlegen nicht erreichbar war; es kommen nur fehlende Einträge dazu.

- **Feiertage in Ferien** (Christtag in den Weihnachtsferien) werden weggelassen: Eine Zeile reicht für das Raster.
- **Beide Anfragen parallel:** Symfonys HTTP-Client ist asynchron. `request()` startet nur; erst `toArray()` wartet.
  Weil beide `request()` vor dem ersten `toArray()` stehen, laufen sie gleichzeitig.
- **Fallstrick, im Test gefunden:** Schlägt die erste Antwort fehl, wird die zweite nie gelesen. Eine nie gelesene
  Antwort prüft beim Zerstören ihren Statuscode und wirft dann eine Exception, irgendwann später an unerwarteter Stelle
  (`vendor/symfony/http-client/Response/TransportResponseTrait.php`, `doDestruct()`). Der Importer bricht deshalb im
  Fehlerfall beide Antworten mit `cancel()` ab.
- **Scoped Client:** `openholidays.client` in `framework.yaml` bündelt Basis-URL, `Accept`-Header und Timeout (5 s).
  Symfony registriert dafür einen Autowiring-Alias nach dem Namen: Ein Konstruktor-Parameter
  `HttpClientInterface $openholidaysClient` bekommt genau diesen Client
  (`FrameworkExtension::registerHttpClientConfiguration()`, `registerAliasForArgument()`).
- **Tests ohne fremden Dienst:** Unter `when@test` hat der Client eine `mock_response_factory`
  (`tests/Http/OpenHolidaysResponses.php`), die aufgezeichnete Antworten aus `tests/Fixtures/openholidays/` liefert
  (abgerufen am 2026-11-13, Winter 2026/27). Die CI ruft die API nie auf.
- **Fehler** werden geloggt (nur die Fehlermeldung, keine Personendaten), Sonja sieht einen Hinweis und kann die Ferien
  selbst eintragen.

## Formulare

- `CourseBreakType`: „Bis“ leer = einzelner Tag. Ein Listener auf `FormEvents::SUBMIT` setzt dann „Bis“ = „Von“. SUBMIT
  läuft vor der Validierung (die hängt an `POST_SUBMIT`), die Prüfung „Bis nicht vor Von“ sieht also schon den Wert.
- `#[Assert\GreaterThan(propertyPath: 'startsOn')]` vergleicht zwei Felder derselben Entity ohne eigenen Callback.
- **PHPStan:** Die Datumsfelder sind in PHP `?\DateTimeImmutable` (ein neues Formularobjekt hat noch kein Datum), die
  Spalten `NOT NULL`. phpstan-doctrine meldet das als Widerspruch. Mit `allowNullablePropertyForRequiredField: true`
  (`phpstan.dist.neon`, Option laut `vendor/phpstan/phpstan-doctrine/README.md`) erlaubt es das; andere
  Typabweichungen meldet es weiter. `#[Assert\NotNull]` sorgt dafür, dass `null` nie gespeichert wird.
  Alternative: je Entity ein eigenes Formularobjekt (DTO) mit nullable Feldern, das nach der Validierung in die Entity
  übertragen wird. Sauberer getrennt, aber doppelt so viel Code; für ein Admin-Tool mit zwei Nutzer:innen nicht nötig.

## Noch nicht drin

- Ferien bearbeiten (nur löschen und neu eintragen).
- Einheiten erzeugen und beim Eintragen neuer Ferien entfernen: C3.
- `CourseVoter::DELETE` verbietet das Löschen erst, wenn es Teilnahmen gibt (C3).
