# Phase 2: Anforderungen

Stand: 2026-11-04 – erste Fragenrunde gestellt, Antworten offen.

## Ablauf der Planung

Jeder Schritt endet mit einem Dokument unter `docs/planung/`:

1. **Anforderungen** (diese Datei): Was die App können soll, aus Sicht von Besucher:innen, Sonja und Mike.
   Abgrenzung: was ins erste Release kommt, was später.
2. **Datenmodell**: ER-Diagramm als Mermaid (`datenmodell.md`), jede Relation mit Begründung und Doctrine-Fallstricken.
3. **Rollenkonzept**: Rollen, Rechte, wo Voter nötig sind (`rollen.md`).
4. **Seitenstruktur**: Routen, Schema der Übersetzungsschlüssel, Entscheidung CSS-Framework.
5. **Umsetzungsreihenfolge** in kleinen Schritten.

## Erste Fragenrunde

Antworten in Stichworten direkt unter die Fragen; „weiß ich noch nicht“ ist auch eine Antwort.

### A. Benutzer und Login

1. Meldet sich nur Sonja an, oder auch Mike als technischer Admin? Sollen Schüler:innen später einen eigenen Zugang
   bekommen (z. B. eigene Termine und Honorarnoten sehen)? - Sonja, Mike und Schüler:innen, wir brauchen also voraussichtlich die Rollen `admin`, `super_admin` und `student`. Für Kunden, die Sonja als Sängerin buchen wollen, ist voraussichtlich kein User-Account nötig. Falls doch, gehe ich davon aus, dass es kein Problem ist, eine neue Rolle `client` im Nachhinein zu konfigurieren.

### B. Unterricht, Semester, Termine (Kern des Datenmodells)

Voraussichtlich benötigte Entities: Course -1:n- CourseParticipation -1:n- lesson -1:1- student
Mein ungefährer Plan für die Entities wäre wie folgt. Bitte mit mir ausarbeiten, damit eine reibungslose Konfiguration durch Sonja im Admin-Bereich möglich und ein Bezahlungssystem abbildbar ist - die Bezahlung passiert außerhalb der app, aber die Honorarnotenerstellung, Bezahlungsstatus, Preiskorrektur wegen Rückerstattung (bei längerer Krankheit oder triftigen Gründen), etc sollten abbildbar sein:


|Entity | Properties |Zweck|
|-|-|-|
|Course|string room, float price, string semester|Abbildung der Eckdaten eines Kurses; semester zB "WS 2026"|
|CourseParticipation|Course course, float payment, string enum paymentStrategy, string timeSlot|Abbildung der Teilnahme eines Schülers an einem Kurs, wo auch die Bezahlung abgebildet wird; paymentStragey zB ["total", "installments", "individual_lessons"], timeSlot zB "Freitag 15:00", alternativ auf `int weekday` und `dateTime time` aufteilen? Die Gesamtheit dieser timeslots (aller CourseParticipations des Kurses) kann für die Anzeige eines Stundenplans verwendet werden |
|Lesson|CourseParticipation courseParticipation, dateTime start, int durationMin, Student student, string contentSummary, User cancelledBy (nullable)|Abbildung einer Einheit|
- 

2. Was ist ein „Semester“ bei Sonja? Ein Zeitraum mit festen Daten (z. B. Sept.–Jan.)? - Vergiss die Bezeichnung Semester. Wir legen Kurs als Entity an, dieser kann dann Einheiten und Teilnehmer haben. Aus dem Datum der Einheiten ergeben sich also Start und Ende eines Kurses (das ist vermutlich meistens ein Sommer- oder Wintersemester, kann variieren). Genaues Datenmodell klären wir aber später.
3. Bucht man einen **festen wöchentlichen Platz** für das ganze Semester (jeden Dienstag 16:00), **einzelne Stunden**
   oder beides? - Fester wöchentlicher Platz, wobei hier spontane Änderungen (Absage, doch Doppel- statt Einzeleinheit, weil Ausfall eines anderen Schülers, etc.) möglich sein sollten. Auch Schnupperstunden kann es geben, also muss `lesson.course` nullable sein.
4. Gibt es verschiedene Längen (30/45/60 min) mit unterschiedlichen Preisen? Paketpreise (z. B. 10er-Block)? - Aktuell: Semesterpreis ist zu bezahlen, wobei die Abrechnung entweder auf einmal oder auf zwei Raten passieren kann. Ich würde das aber möglichst flexibel halten, weil sich das ändern kann. Grundsätzlich ist auch eine Einzelabrechnung oder 10er-Block denkbar, wenn auch im Moment nicht angedacht. Vielleicht mit einer billingStrategy abbilden. Eine Einheit dauert 50 min, aber auch hier sind Abweichungen denkbar, 50 min als default für die Einheit festlegen. Einstellung im Settings-Admin-Bereich möglich.  
5. Was passiert, wenn jemand auf der Website einen freien Termin wählt: **Anfrage**, die Sonja bestätigt, oder sofort
   **verbindliche Buchung**? Ist der Termin blockiert, solange die Anfrage offen ist? - Anfrage wird geschickt, keine Buchung und keine Reservierung. Sonja kann je nach Eingangsdatum oder aus persönlicher Präferenz priorisieren und zuweisen, also es sollte nur Sonja die Zuweisung Student -> Einheit möglich sein. Sonja erstellt im admin-Bereich den tatsächlichen Stundenplan, der dann für einen Kurs gilt, indem die Kursteilnahme eines Schülers bzw den timeslot setzt. Hilf mir noch mit dem Mapping student-lesson-courseParticipation 
6. Absagen und Nachholtermine: Gibt es Regeln, und haben sie Auswirkungen auf die Abrechnung? - Stunden, die von Schülern abgesagt werden, verfallen. Stunden, die von Sonja abgesagt werden, werden nachgeholt, sofern die Gesamtanzahl der Stunden einen bestimmten Schwellwert unterschreiten würde. Dieser Schwellwert soll von Sonja im Adminbereich konfiguriert werden können.
7. Unterrichtet Sonja an mehreren Orten? - Nein, sie unterrichtet immer im selben Schulgebäude, lediglich der Raum kann von Semester zu Semester (Kurs zu Kurs) wechseln.

### C. Schüler:innen und Honorarnoten

8. Gibt es Minderjährige, bei denen ein Elternteil Rechnungsempfänger ist? - ja
9. Wie oft werden Honorarnoten erstellt: pro Semester, pro Monat, pro Stunde? - pro Semester, Zahlungsart wird dort vermerkt (siehe B)
10. Was muss auf einer Honorarnote stehen? Bitte eine bisherige Honorarnote als Muster. Steuerlicher Status
    (Kleinunternehmerin ohne USt?) mit Sonja bzw. Steuerberatung klären, nicht annehmen. Wie werden sie nummeriert? - Name und Adresse beider Vertragspartner, Datum, HN-Nummer, Betrag, Zahlungsart (bar/Überweisung, Zahlungsmodalitäten), Bankverbindung, HN werden nummeriert, Kleinunternehmerregelung ohne USt, Muster siehe claude_references/HN16_GU_WiSe_2026_Springer.pdf; Dateiname der HN = "HN" + fortlaufende Nummer + "_GU_" (steht für "Gesangsunterricht") + ["WiSe", "SoSe"] (Winter- oder Sommersemester) + "_" + Jahr + "_" + Nachnahme SchülerIn; Beispiel: HN16_GU_WiSe_2026_Springer.pdf ist Honorarnote 16 des Jahres 2026, betrifft Gesangsunterricht im Wintersemester des Schülers Springer
11. Soll die App Honorarnoten als **PDF** erzeugen? Soll sie festhalten, ob bezahlt wurde? - ja, beides. Sonja soll eine eingegangene Zahlung möglichst einfach mit einem Klick (wenn es sich um den Gesamtbetrag des Kurses handelt, oder ein Feld ausfüllen können, wie hoch die Teilzahlung war) eintragen können.

### D. Booking

12. Wer pflegt die Repertoireliste (vermutlich Sonja im Admin)? Welche Angaben pro Song: Titel, Interpret, Kategorie
    (z. B. „Einzug“, „Ringtausch“)? - Titel, Interpret, Kategorie, Tonart
13. Wie entsteht die Preisschätzung? Grundpreis pro Anlass + pro Song? Anfahrt? - Ja genau, Grundpreis + Aufpreis pro Song, falls dieser NICHT im Repertoir ist + Anfahrt

### E. Inhalte und Sprachen

14. Soll Sonja Texte, Preise und Bilder selbst im Admin ändern können (kleines CMS), oder pflegt Mike sie in
    Templates/Übersetzungsdateien? Großer Unterschied fürs Datenmodell. - ein kleines CMS wäre super, ist das viel Aufwand?
15. Welche Sprachen außer Deutsch, und wann? Nur die Oberfläche oder auch Inhalte (z. B. Repertoireliste)? - Wir brauchen vorerst nur Deutsch

### F. Erstes Release

16. Vorschlag: zuerst öffentliche Seiten (Home, Booking solo, Band, Lessons-Infos), einfache Kontaktformulare
    (Mail an Sonja), **Impressum und Datenschutzerklärung** (in Österreich für eine geschäftliche Website nötig; genauer
    Inhalt wird gegen Primärquellen geprüft). Danach Admin-Bereich mit Semestern, Terminen und Honorarnoten.
    Passt die Reihenfolge, oder hat etwas Vorrang (z. B. Honorarnoten)? - Unbedingt zuerst einmal die Präsenz mit Bildern und Infos zu ihren Angeboten, also ja, zuerst die öffentlichen Seiten

## Antworten

Antworten stehen jeweils durch einen Bindestrich getrennt hinter den Fragen
