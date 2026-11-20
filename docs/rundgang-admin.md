# Rundgang durch den Admin-Bereich (lokal)

Zum Ausprobieren aller Funktionen mit erfundenen Daten, bevor Mike sie Sonja in Produktion zeigt. Dauer etwa
30 Minuten. Stand: 2026-11-13 (Schritte C1–C6, D1, D3–D5, E), PDF D2 ergänzt 2026-11-17; noch ohne Kursraster C4.

## Vorbereitung

```bash
docker compose up --wait
docker compose exec php bin/console foundry:load-fixtures main   # löscht die lokale DB und füllt sie neu
```

- Anmelden: https://localhost/admin mit `sonja@example.com` (Admin, sieht, was Sonja sieht) oder `mike@example.com`
  (Super-Admin, zusätzlich „Benutzer“). Passwort: `UserFactory::PASSWORD` in `tests/Factory/UserFactory.php`.
- Mails (Passwort vergessen, Erinnerungen) landen in Mailpit: http://127.0.0.1:8025
- Was die Fixtures anlegen: 20 Schüler:innen (8 minderjährig mit Elternteil), ein Wintersemester 2026/27 mit Ferien
  und sechs Teilnahmen am Freitag (Einheiten vor heute gehalten, mit Song als Inhalt; bei der ersten eine Absage durch
  die Schülerin und eine durch Sonja), zwei Schnupperstunden, Absenderdaten „Erika Musterfrau“, zwei Honorarnoten
  (1/2026 mit Raten und bezahlter erster Rate, 2/2026 offen und überfällig).
- Neu anfangen: den `foundry:load-fixtures`-Befehl wiederholen.

## 1. Übersicht (`/admin`)

Nächste 7 Tage, offene Honorarnoten (2/2026 als überfällig), „4 Teilnahmen haben noch keine Honorarnote“, Einnahmen
des Jahres, darunter der laufende Kurs mit der zuletzt gehaltenen Einheit je Person.

## 2. Schüler:innen und Elternteile

1. „Schüler:innen“: Suche nach einem Namensteil. Eine minderjährige Person öffnen: Elternteil, Adresse, Kurse und
   Schnupperstunden.
2. Neue Person anlegen, mit und ohne Elternteil. Löschen geht nur ohne Teilnahmen (sonst steht ein Hinweis da).
3. „Elternteile“: Ein Elternteil hat zwei Kinder („Geschwister“).

## 3. Kurs und Ferien

1. „Kurse“ → Wintersemester 2026/27: Ferien (aus den Fixtures), Teilnahmen mit „x von 15“ und Nachholhinweis.
2. Einen Ferientag ergänzen, der auf einen Freitag mit Einheiten fällt: Ein Popup listet die Einheiten, die gelöscht
   werden (auch gehaltene). „Abbrechen“ speichert nichts; „OK“ löscht sie, am Ende kommen neue dazu (Meldung „Einheiten:
   x entfernt, x angelegt“). Den Ferientag wieder löschen: Die Einheiten an dem Tag kommen zurück, am Ende fallen sie weg.
3. Neuen Kurs anlegen (z. B. Sommersemester 2027): Die Ferien werden aus der OpenHolidays API vorgeschlagen (braucht
   Internet).
4. „Raster“ (Knopf auf der Kursseite): eine Zeile je Unterrichtstag (Freitag), links die Kalenderwoche über alle
   Tage der Woche (C4f). Seit C4g ist jede Zeile eine Zeitachse: oben die Stunden, gestrichelte Stundenlinien, die
   Einheiten stehen minutengenau an ihrer Uhrzeit (am schmalen Bildschirm waagrecht scrollen, KW und Tag bleiben stehen).
   In der Box Beginn, Ende und Zählung („13:40-14:30 · 5/15“), der Name (Link zur Teilnahme) und Abzeichen; „1 Nachholstunde
   fällig“ steht bei Valentins nächster Einheit. Die Schnupperstunde am Mittwoch, 18.11., 17:00 steht gestrichelt und
   blau in einer eigenen Zeile (Link zur Schnupperstunde). Ferientage gelb; sind alle Unterrichtstage einer Woche frei
   (Herbstferien, KW 52 und 53), ist auch die KW gelb und nennt die Ferien. KW 01 ist nicht gelb: Die Ferien enden am
   Mittwoch, 6.1., am Freitag ist Unterricht. Absagen stehen in der niedrigen roten Zeile unter dem Tag, an ihrer Stelle
   oben ein gestrichelter Platzhalter „frei“. Aktuelle Woche und heutiger Tag mit Strich links. Gehaltene Einheiten grau
   mit ✓, geplante fett. Eine Nachholstunde an einem anderen Tag bekommt eine eigene Zeile mit ihrem Datum.
   Verschiebt man eine Einheit auf einen Ferientag, erscheint ein gelber Hinweis; sie bleibt trotzdem dort.
   Überschneidung prüfen: eine Schnupperstunde zur Zeit einer Einheit anlegen → beide Boxen untereinander, rot umrandet.
5. Im Raster bearbeiten (braucht JavaScript): Klick auf eine zukünftige Einheit (nicht auf den Namen) → der Editor
   erscheint unter der Box, Escape oder Klick daneben schließt ihn. „abgesagt (ich)“, Notiz, Speichern.
   Die Einheit wandert in die rote Zeile, „1 Nachholstunde fällig“ erscheint bei der nächsten Einheit (Klick darauf:
   „Nachholstunde eintragen“). Im Platzhalter
   „frei“ „← + Doppelstunde …“ klicken: Die Nachbarin bekommt eine zweite Einheit, ihre letzte am
   Kursende fällt weg. Dann die abgesagte Einheit öffnen → „Absage aufheben“: Ein Popup nennt die Doppelstunde, nach
   „OK“ ist sie weg und die Nachbarin hat ihre letzte Einheit wieder. Nur die Notiz ändern: Die Seite lädt nicht neu.
   „Löschen“ fragt nach, danach hängt am Kursende eine Einheit an.
   Ziehen (C4h, mit Maus oder Touchpad): eine geplante Einheit waagrecht ziehen. Die Box zeigt die neue Zeit
   („13:00–13:50“) in 5-Minuten-Schritten; über einer anderen Einheit oder Schnupperstunde wird sie rot und springt
   beim Loslassen zurück. Auf freier Zeit loslassen: Nur die Zeile ändert sich, die Seite lädt nicht neu (am alten
   Platz erscheint „frei“, wenn er ganz frei wurde). Escape während des Ziehens bricht ab. Gehaltene und abgesagte
   Einheiten lassen sich nicht ziehen.
6. Im Raster „Ferien …“ unter einer Kalenderwoche: Das Ferienformular auf der Kursseite ist mit Montag bis Sonntag
   vorbelegt; nach dem Speichern (mit Popup) geht es zurück ins Raster, die Woche ist gelb.
   Feiertage an Tagen ohne Unterricht (Mariä Empfängnis, Dienstag) erscheinen im Raster nicht.

## 4. Teilnahme und Einheiten

1. Im Kurs die erste Teilnahme öffnen: Liste der Einheiten mit Status, „1 Nachholstunde fällig“ (wegen der Absage
   durch Sonja).
2. Eine Einheit anklicken: Absage (Schüler:in, ich oder keine) und Inhalt eintragen. „Gehalten“ wird nicht mehr
   eingetragen, es ergibt sich aus der Zeit. Das ist der Ablauf, den das Kursraster
   (C4) später ersetzt.
3. „Nachholstunde eintragen“: Termin wählen und mit der abgesagten Einheit verknüpfen.
4. „Anzahl der Einheiten“ (C7): auf 12 senken, Grund eintragen → Liste der drei Einheiten, die gehen; erst „Einheiten
   löschen und speichern“ löscht sie. Oben steht dann „12 von 12 (Kurs: 15)“, auch im Raster. „Teilnahme beenden“
   setzt die Zahl auf die schon begonnenen Einheiten; bei einer Teilnahme mit Honorarnote folgt der Hinweis auf die
   Gutschrift. Weniger als begonnen geht nicht (Fehlermeldung).

## 5. Schnupperstunde übernehmen

1. „Schnupperstunden“: zwei Einträge. Eine neue anlegen (Dauer kommt aus den Einstellungen).
2. Im Kurs „Teilnahme anlegen“ und eine Person mit Schnupperstunde wählen: Die Schnupperstunde ist vorausgewählt und
   wird zur ersten, gehaltenen Einheit („aus Schnupperstunde“); sie zählt mit (1 + 14).

## 6. Einstellungen (`/admin/einstellungen`)

1. Design der Website auf „Bühne (dunkel)“ stellen und https://localhost/ in einem anderen Tab öffnen; danach zurück.
2. Absenderdaten ansehen, IBAN falsch eingeben (Fehlermeldung), eine Unterschrift als PNG hochladen (irgendein PNG zum
   Testen), Vorschau, wieder entfernen.

## 7. Honorarnoten, Zahlungen, Gutschriften

1. Eine Teilnahme ohne Honorarnote öffnen → „Honorarnote erstellen“: Empfänger:in und Betrag werden angezeigt, Daten
   eintragen (mit 2. Rate). Danach ist nichts mehr änderbar; die Nummer ist die nächste des Jahres.
2. Bei einer minderjährigen Person „Honorarnote an den Elternteil“ ab- und anwählen und sehen, an wen sie geht.
3. Fehlende Daten: in einer Teilnahme den Preis auf 0 setzen oder die Adresse der Person leeren → die Seite nennt, was
   fehlt, mit Links.
4. Honorarnote öffnen: Formular „Zahlung erfassen“ ist mit Restbetrag und heute vorbelegt. Erst eine Teilzahlung
   (Status „teilweise bezahlt“), dann den Rest mit einem Klick („bezahlt“). Eine Zahlung löschen.
5. „Gutschrift erstellen“ (z. B. 66 €): eigene Nummer, negativer Betrag. Bei bezahlter Honorarnote steht dann
   „Rückzahlung offen“ → Rückzahlung erfassen.
6. „Honorarnoten“: Liste je Jahr mit Status und Link „PDF“.
7. PDF (D2, ergänzt 2026-11-17): In der Honorarnote „PDF herunterladen“ und mit dem Muster HN16 vergleichen
   (Dateiname `HN1_GU_WiSe_2026_<Nachname>.pdf`). Die Gutschrift aus Schritt 5 als PDF ansehen (Name endet auf
   `_Gutschrift`). Im Kurs: Spalte „Honorarnote“ mit Nummer und PDF oder „erstellen“, darunter „Alle Honorarnoten als
   ZIP“ und „4 Teilnahmen haben noch keine Honorarnote.“ ZIP öffnen. Die PDFs der Fixtures entstehen beim Laden mit den
   Absenderdaten „Erika Musterfrau“; Fixtures nach dem Update neu laden, sonst fehlen sie (404).

8. Datenschutz-Information (F, 2026-11-17): https://localhost/datenschutz/unterricht lesen, „Als PDF öffnen“. Im Admin
   unter „Schüler:innen“ oben „Datenschutz-Information (PDF)“. In der Website-Datenschutzerklärung steht der Link
   darauf, der Verwaltungsbereich wird dort nicht erwähnt.

## 7a. Löschfristen (F3a)

Eine Schüler:in öffnen: Unten steht „Gespeichert bis …“ (Ende des Semesters nach der letzten Einheit, also bei einem
Kurs im Wintersemester 2026/27 der 31.08.2027), bei einer Person mit Honorarnote auch „Honorarnoten und Zahlungen
bleiben bis 31.12.2033“. Das Dashboard zeigt „Löschung in den nächsten 30 Tagen“ erst, wenn jemand dran ist (mit
den Fixtures nicht der Fall).

Löschen (F3b, am Server später täglich per Cron, F3c):

```bash
docker compose exec php bin/console app:privacy:purge --dry-run   # nur zählen
```

Mit den Fixtures ist nichts dran. Was nach Ablauf passiert (Person gelöscht, Teilnahme mit Honorarnote bleibt als
„Person gelöscht“ schreibgeschützt), zeigen `tests/Privacy/PersonalDataPurgerTest.php` und
`tests/Admin/PersonDeletedTest.php`. Ohne `--dry-run` löscht der Befehl wirklich, auch lokal.

## 8. Einnahmen und Erinnerung

1. „Einnahmen“: Zahlungen nach Zahlungsdatum, „Als CSV herunterladen“ und in Excel oder Numbers öffnen.
2. Erinnerung an überfällige Honorarnoten (am Server später täglich per Cron):

   ```bash
   docker compose exec php bin/console app:invoices:remind-overdue --dry-run   # nur zählen
   docker compose exec php bin/console app:invoices:remind-overdue             # Mail in Mailpit
   ```

   Ein zweiter Aufruf schickt nichts mehr (eine Mail je Frist).

## 9. Konten (als `mike@example.com`)

1. „Benutzer“: Konto anlegen → Einladungslink in Mailpit → Passwort setzen. Rolle ändern, deaktivieren (Login danach
   nicht mehr möglich). Das eigene Konto lässt sich nicht ändern.
2. Abmelden → „Passwort vergessen“ → Link in Mailpit. „Mein Konto“: Passwort ändern.
3. Mit `sonja@example.com` ist „Benutzer“ nicht sichtbar, `/admin/benutzer` ergibt 403.
4. **Passkey** (Chrome oder Safari auf dem Mac, `https://localhost`): „Mein Konto“ → Name eingeben → „Passkey
   hinzufügen“ → Touch ID. Ein zweites Mal am selben Gerät lehnt der Browser ab. Abmelden, `/admin/kurse` aufrufen →
   „Mit Passkey anmelden“ → Touch ID → man landet auf „Kurse“, in „Mein Konto“ steht „zuletzt genutzt“. Ohne Touch ID:
   Chrome-DevTools → ⋮ → More tools → WebAuthn → „Enable virtual authenticator environment“, Authenticator mit
   „Supports resident keys“ und „Supports user verification“ anlegen. Lokale Passkeys gelten nur für `localhost` und
   können danach in den Passwort-Einstellungen von macOS bzw. Chrome gelöscht werden.

## In Produktion zeigen: was vorher passieren muss

1. **Konten anlegen** (`deployment.md` S7). Erledigt 2026-11-17 (Mike Super-Admin, Sonja Admin).
2. **Einstellungen:** Sonja trägt ihre Absenderdaten ein (oder Mike mit ihr). Eine Unterschrift ist nicht nötig
   (Entscheidung 2026-11-14). Erledigt 2026-11-17.
3. **Achtung, Produktion ist echt:** Honorarnoten lassen sich nicht löschen und verbrauchen Nummern (1/2026, 2/2026 …).
   Beim Zeigen also keine Honorarnote zum Ausprobieren erstellen. Schüler:innen, Kurse und Teilnahmen lassen sich
   wieder löschen.
4. **Passkeys:** Sonja richtet je einen auf dem MacBook Air und dem Android-Handy ein (mit Mike, `umsetzung-admin.md`
   B5d). Auf dem Handy direkt auf `sonjaczak.com` anmelden, nicht per QR-Code vom MacBook.
5. **Echte Daten** (Sonjas Schüler:innen) erst nach `umsetzung-admin.md` F (Backups, Datenschutz-Information) und
   nachdem Sonja den Text der Datenschutz-Information gelesen hat; sie gibt ihn bei der Anmeldung weiter.
