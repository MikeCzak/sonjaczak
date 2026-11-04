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
   bekommen (z. B. eigene Termine und Honorarnoten sehen)?

### B. Unterricht, Semester, Termine (Kern des Datenmodells)

2. Was ist ein „Semester“ bei Sonja? Ein Zeitraum mit festen Daten (z. B. Sept.–Jan.)?
3. Bucht man einen **festen wöchentlichen Platz** für das ganze Semester (jeden Dienstag 16:00), **einzelne Stunden**
   oder beides?
4. Gibt es verschiedene Längen (30/45/60 min) mit unterschiedlichen Preisen? Paketpreise (z. B. 10er-Block)?
5. Was passiert, wenn jemand auf der Website einen freien Termin wählt: **Anfrage**, die Sonja bestätigt, oder sofort
   **verbindliche Buchung**? Ist der Termin blockiert, solange die Anfrage offen ist?
6. Absagen und Nachholtermine: Gibt es Regeln, und haben sie Auswirkungen auf die Abrechnung?
7. Unterrichtet Sonja an mehreren Orten?

### C. Schüler:innen und Honorarnoten

8. Gibt es Minderjährige, bei denen ein Elternteil Rechnungsempfänger ist?
9. Wie oft werden Honorarnoten erstellt: pro Semester, pro Monat, pro Stunde?
10. Was muss auf einer Honorarnote stehen? Bitte eine bisherige Honorarnote als Muster. Steuerlicher Status
    (Kleinunternehmerin ohne USt?) mit Sonja bzw. Steuerberatung klären, nicht annehmen. Wie werden sie nummeriert?
11. Soll die App Honorarnoten als **PDF** erzeugen? Soll sie festhalten, ob bezahlt wurde?

### D. Booking

12. Wer pflegt die Repertoireliste (vermutlich Sonja im Admin)? Welche Angaben pro Song: Titel, Interpret, Kategorie
    (z. B. „Einzug“, „Ringtausch“)?
13. Wie entsteht die Preisschätzung? Grundpreis pro Anlass + pro Song? Anfahrt?

### E. Inhalte und Sprachen

14. Soll Sonja Texte, Preise und Bilder selbst im Admin ändern können (kleines CMS), oder pflegt Mike sie in
    Templates/Übersetzungsdateien? Großer Unterschied fürs Datenmodell.
15. Welche Sprachen außer Deutsch, und wann? Nur die Oberfläche oder auch Inhalte (z. B. Repertoireliste)?

### F. Erstes Release

16. Vorschlag: zuerst öffentliche Seiten (Home, Booking solo, Band, Lessons-Infos), einfache Kontaktformulare
    (Mail an Sonja), **Impressum und Datenschutzerklärung** (in Österreich für eine geschäftliche Website nötig; genauer
    Inhalt wird gegen Primärquellen geprüft). Danach Admin-Bereich mit Semestern, Terminen und Honorarnoten.
    Passt die Reihenfolge, oder hat etwas Vorrang (z. B. Honorarnoten)?

## Antworten

(noch offen)
