# Dashboard (E)

Plan: `docs/planung/datenmodell.md` 9 und Umsetzungsplan, Abschnitt E. Seite: `/admin` (Startseite nach dem Login).

## Was es zeigt

| Bereich | Quelle |
|---|---|
| Nächste 7 Tage: geplante Einheiten und Schnupperstunden in einer Liste | `LessonRepository::findPlannedBetween()`, `TrialLessonRepository::findBetween()` |
| Offene Honorarnoten (überfällige zuerst, mit Datum der verpassten Frist) und Anzahl der Teilnahmen ohne Honorarnote | `InvoiceRepository::findOriginalsWithPayments()`, `Invoice::getMissedDeadline()` |
| Einnahmen des laufenden Jahres je Kurs, Summe mit Link zum Export | `PaymentRepository::findByYear()` |
| Je laufendem Kurs: zuletzt gehaltene Einheit mit Inhalt, gehaltene und ausgefallene Einheiten, fällige Nachholstunden | `CourseRepository::findRunningOn()` |

## Entscheidungen

- **„Laufend“** = Kursbeginn erreicht und Kursende nicht vorbei. Abgeschlossene Kurse erscheinen nicht mehr, ihre
  offenen Honorarnoten schon.
- **Eine Abfrage für die Kurse:** `findRunningOn()` lädt Kurse, Teilnahmen, Schüler:innen und Einheiten mit
  `addSelect` in einem Schritt (Fetch Join). Ohne das würde Doctrine je Teilnahme die Einheiten einzeln nachladen
  („N+1-Problem“). Bei einer Handvoll Teilnahmen wäre das noch schnell, aber die Abfrage ist nicht aufwendiger.
- **Fallstrick Fetch Join:** Ein Fetch Join darf die verbundene Sammlung nicht filtern (kein `WHERE` auf `l`), sonst
  enthält `$enrollment->getLessons()` danach nur einen Teil der Einheiten, und Doctrine merkt sich diesen Teil für
  den Rest des Requests. Gefiltert wird deshalb in PHP.
- **„Jetzt“ aus der Clock** (`Psr\Clock\ClockInterface`), wie in D4: `DashboardTest` legt Freitag, 13.11.2026,
  10:00 fest und prüft die Liste der nächsten Einheiten wörtlich.
- **Nicht umgesetzt:** eine Übersicht *aller* Schüler:innen (gibt es unter „Schüler:innen“) und Einnahmen früherer
  Jahre (unter „Einnahmen“ je Jahr).

## Zum Durchsehen

1. Zeitraum „nächste 7 Tage“ (Konstante `DashboardController::UPCOMING_DAYS`).
2. Die Seite ist im Browser noch nicht mit Sonja angesehen; Gestaltung beim Demo-Termin nachschärfen (wie das
   Kursraster C4).
