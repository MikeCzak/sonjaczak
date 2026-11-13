# Zum Durchsehen

Stand: 2026-11-13. Eine Liste für alle Punkte, bei denen Claude etwas ausgelegt oder vorläufig festgelegt hat. Die
Begründung steht jeweils in der verlinkten Erklärung. Erledigte Punkte mit Datum und Ergebnis abhaken, dann bei
Bedarf in die Erklärung übernehmen.

## Mit Sonja klären

- [ ] **Schwellwert der Nachholregel** (C3, `erklaerungen/11`): Sonja beschreibt in `anforderungen.md` (Frage 6) eine
  **Gesamtzahl** („sofern die Gesamtanzahl der Stunden einen bestimmten Schwellwert unterschreiten würde“, z. B. 13),
  das Datenmodell einen **Abstand** (`plannedLessons − Schwellwert`, z. B. 2). Umgesetzt ist der Abstand, der Wert ist
  seit C6 eine Einstellung (Standard 0). Welche Variante, welcher Wert?
- [ ] **Auffüllen nach neuen Ferien** (C3): Für entfallene Einheiten kommen am Ende neue dazu (bis zum Kursende).
- [ ] **Wochentermin fest** (C3): Nach dem Anlegen lassen sich Wochentag und Uhrzeit einer Teilnahme nicht mehr ändern,
  nur einzelne Einheiten verschieben. Ein Umzug aller geplanten Einheiten wäre machbar.
- [ ] **Fälligkeit ohne Vorschlag** (D1, `erklaerungen/13`): „Zahlbar bis“ muss eingetragen werden. Eine feste Frist
  als Vorschlag wäre eine Einstellung.
- [ ] **Betrag beim Erstellen nicht änderbar** (D1): kommt aus der Teilnahme (dort für Ermäßigungen änderbar).
- [ ] **Leistungstext** „Gesangsunterricht im Wintersemester 2026/27“ vorbelegt, änderbar (D1).
- [ ] **Zahlungen ohne Rückfrage löschbar** (D3).
- [ ] **Empfänger der Erinnerung** `music@sonjaczak.com` (D4); eine eigene Einstellung wäre leicht ergänzt.
- [ ] **Dashboard** (E, `erklaerungen/14`): Zeitraum „nächste 7 Tage“, Gestaltung beim Zeigen nachschärfen.
- [ ] **CSV mit Sonjas Excel prüfen** (D5): Umlaute, Beträge als Zahl, Datum.
- [ ] **Ansprache „du“** im Admin-Bereich; bei Konten für Schüler:innen später ggf. anpassen.

## Mit der Steuerberatung klären

- [ ] **Gutschrift und Zahlung als ein Konto** (D3, `erklaerungen/13`): zu zahlen = Betrag minus Gutschriften,
  Rückzahlung als negative Zahlung an der Gutschrift. Passt das so (Antwort 8 im Datenmodell)?
- [ ] **§ 19 Abs. 1 Z 1 EStG** (D5): Ausnahme für regelmäßig wiederkehrende Einnahmen um den Jahreswechsel; der Export
  ordnet streng nach Zahlungsdatum zu.

## Technisch (Mike)

- [ ] **Advisory Lock statt Wiederholung** bei der Nummernvergabe (D1, `erklaerungen/13`): Abweichung vom Datenmodell.
- [ ] **BIC nur per Regex** (C6, `erklaerungen/12`): `Assert\Bic` bräuchte `symfony/intl`.
- [ ] **Schüler:in mit Teilnahmen nicht löschbar** (C5): erst die Teilnahmen löschen; mit Honorarnoten geht auch das
  nicht. Was dann mit alten Daten passiert, regelt die Löschregel (Abschnitt F, zurückgestellt).
