# Projektstand: sonjaczak.com

Stand: 2026-11-23. **Die eine Datei für alles Offene.** Reihenfolge: was jetzt auf Mike wartet, was später zu klären
ist, Termine, Fortschritt, getroffene Entscheidungen, Server. Was wann im Detail passiert ist: `verlauf.md`.

**Verweise:** Schritte des Umsetzungsplans heißen „`umsetzung-admin.md` C4“ (`docs/planung/umsetzung-admin.md`),
Schritte der Server-Einrichtung „`deployment.md` S8“. Ist ein Punkt geklärt, verschwindet er hier und landet unter
„Getroffene Entscheidungen“ (oder im Plan, falls er nur einen Schritt betrifft).

## Jetzt (wartet auf Mike)

**So beantworten:** direkt hier in die Zeile „Antwort“ schreiben (oder im Chat). Claude überträgt die Antwort danach
in Plan bzw. Entscheidungen und entfernt den Punkt. Die Optionen mit Pro und Contra stehen im verlinkten Abschnitt.

**Nächster Schritt: Backups in die Hetzner Storage Box** (`planung/backups-ohne-mac.md`, Anleitung `deployment.md`
S13). Skript im Repo fertig und lokal getestet (2026-11-18). Storage Box von Mike bestellt und verfügbar
(2026-11-19), AVV abgeschlossen, Unterkonten und Snapshots eingerichtet, Upload am Server und Rücksicherung aus der
Storage Box getestet (S13 Teile 1–4, 2026-11-19). Cronjobs eingetragen, Hetzner in der Datenschutz-Information
(2026-11-19). Test gegen Löschen bestanden (Teil 5, 2026-11-21). Offen: nach 2 Wochen ohne Fehler (frühestens
2026-12-03) Mac abschalten. Der Mac sichert bis dahin weiter.

**Wartung W1–W5** (Plan `planung/wartung.md`, freigegeben 2026-11-23): W2, W1 und W3 umgesetzt, danach W4, W5.
Am Server (Mike, ohne Eile): Zeile `CADDY_MERCURE_JWT_SECRET` aus der Umgebungsdatei löschen (wird nicht mehr gelesen).
Von Mike: Dependabot alerts einschalten (GitHub → Settings → Advanced Security bzw. Code security).

Passkeys richtet Mike erst ein, wenn er Sonja die Website zeigt (siehe
„Später zu klären“, „Mit Sonja“). Parallel: Text der Datenschutz-Information mit Sonja durchgehen (F1/F2 live seit 2026-11-17) (siehe „Später zu
klären“, „Mit Sonja“). Danach dürfen echte Daten eingetragen werden (Echtbetrieb, `umsetzung-admin.md` Meilensteine).

## Später zu klären

Jeder Punkt mit dem Tag, an dem er entstand, und dem Schritt, aus dem er stammt. Die Begründung steht in der
verlinkten Erklärung.

### Mit Sonja

- [ ] **AVV mit Hetzner an Sonja schicken** (2026-11-19, S13, Mike): Sonja ist Verantwortliche und braucht eine Kopie
  (auch für den Notfall ohne Mike). Erst wenn Mike ihr die Website zeigt (Überraschung).
- [ ] **Passkeys einrichten, wenn Mike Sonja die Website zeigt** (verschoben 2026-11-18, Mike: bis dahin arbeitet er
  ohne sie; `umsetzung-admin.md` B5d): je ein Passkey auf ihrem MacBook Air und ihrem Android-Handy. Code ist live,
  es fehlt nur das Einrichten. **Nicht vergessen:** Für Sonja ist der Fingerabdruck viel einfacher als z. B. ein
  Einmalcode (OTP).
- [ ] **Ferien löschen gibt auch gelöschte Einheiten zurück** (2026-11-16, `umsetzung-admin.md` C4-0): Wird eine Ferienwoche
  gelöscht, bekommt jede Teilnahme an den frei gewordenen Tagen ihre Einheit wieder, wenn sie in der Woche keine hat.
  Hatte Sonja dort vorher eine Einheit absichtlich gelöscht, kommt auch diese zurück (der Grund wird nicht gespeichert).
  Im Test: Petras gelöschte Einheit kam mit. Selten; sonst im Raster wieder löschen.
- [ ] **Darstellung des Rasters** (2026-11-16, `umsetzung-admin.md` C4b, seit 2026-11-18 je Tag, C4f, als Zeitachse,
  C4g) beim Zeigen mit Sonja prüfen: Eine Zeile je Unterrichtstag, links die KW über alle Tage der Woche. Jede Zeile
  ist eine Zeitachse (3 px pro Minute, am Laptop mit sechs Einheiten etwas breiter als der Bildschirm, waagrecht
  scrollen). In der Box „13:40–14:30 · 5/15“ (Endzeit seit 2026-11-20, Mike), Name (Link zur Teilnahme), Abzeichen, Notiz; was nicht passt, wird
  abgeschnitten und steht im Tooltip. Gehaltene grau mit ✓, geplante fett; Schnupperstunden gestrichelt und blau,
  Überschneidungen rot umrandet und untereinander. Ferientage gelb, ganze Ferienwochen auch bei der KW. Abgesagte in
  einer niedrigen roten Zeile darunter, oben an ihrer Stelle „frei“ mit Doppelstunden-Angebot.
- [ ] **Ferien an Tagen ohne Unterricht unsichtbar** (2026-11-18, `umsetzung-admin.md` C4f, Auslegung): Feiertage oder
  Ferientage, auf die kein Unterrichtstag fällt (Mariä Empfängnis am Dienstag, Weihnachtsferien 4.–6.1.), erscheinen im
  Raster nicht; nur auf der Kursseite.
- [ ] **Ein freier Freitag gilt als freie Woche** (2026-11-18, C4f, Auslegung): Mit nur einem Unterrichtstag steht auch
  ein einzelner Feiertag oder schulautonomer Tag am Freitag bei der KW (die Woche hat dann keinen Unterricht).
- [ ] **Annahmen beim Raster als Zeitachse** (2026-11-18, `umsetzung-admin.md` C4g):
  - „Nachholstunde fällig“ steht bei der nächsten stattfindenden Einheit; liegen alle in der Vergangenheit, bei der
    letzten.
  - „frei“ erscheint nur zwischen der ersten und letzten regulären Einheit einer Teilnahme, nicht an Ferientagen, und
    nicht, wenn dort eine Schnupperstunde liegt. Teilen sich zwei Teilnahmen ein Zeitfenster, gibt es eine Box.
  - Doppelstunde angeboten wird der Einheit davor bzw. danach, deren zweite Einheit in das freie Fenster fiele (bisher:
    die Nachbarspalte).
  - Als Überschneidung gelten nur stattfindende Termine (Einheiten ohne Absage, Schnupperstunden), nicht abgesagte.
  - Die Achse reicht über alle Einheiten des Kurses; eine späte Nachholstunde macht jede Zeile breiter.
  - Schnupperstunden erscheinen, wenn sie im Kurszeitraum liegen, auch an Tagen ohne Unterricht (eigene Zeile).
- [ ] **„Nachholstunde fällig“ als Link** (2026-11-18, Mike): Jedes Abzeichen führt zu „Nachholstunde eintragen“.
  Ist keine Absage durch Sonja mehr offen (fällig, weil nicht alle Einheiten bis zum Kursende passen), geht es zurück
  zur Teilnahme mit einem Hinweis, warum (Auslegung).
- [ ] **Bedienung des Rasters** (2026-11-16, `umsetzung-admin.md` C4d, Editor seit C4g schwebend unter der Box) mit
  Sonja prüfen: Klick auf die Box öffnet den Editor, Klick auf den Namen die Teilnahme; nach Absage, Doppelstunde oder Löschen lädt die Seite neu. Doppelstunden
  werden in jeder leeren Zelle angeboten, deren Nachbar am selben Tag eine Einheit hat, auch in Wochen ohne Absage
  (z. B. bevor eine Teilnahme beginnt); ob sie passt, prüft der Server.
- [ ] **Text der Honorarnote als PDF** (2026-11-17, `umsetzung-admin.md` D2c) beim Zeigen prüfen:
  - „Für den“ steht fest vor dem Leistungstext („Für den Gesangsunterricht im …“, wie im Muster). Ändert Sonja den
    Text, muss er dazu passen.
  - Ohne Raten steht „Zahlbar **bis zum** …“ (im Muster gibt es nur den Fall mit Raten).
  - Gutschrift: Titel „Gutschrift 17/2026“, darunter „zu Honorarnote 16/2026“, Text, Betrag positiv, ohne Bankdaten
    und ohne Zahlungsaufforderung. Mit dem Standardtext „Gutschrift zu Honorarnote 16/2026“ steht der Bezug zweimal.
  - Unter „Mit der Bitte um Überweisung“ steht Sonjas Name rechts, wo im Muster die Unterschrift war.
  - Die Postleitzahl erscheint wie eingetragen; „A-8130“ wie im Muster nur, wenn Sonja „A-8130“ einträgt.
  - Schrift DejaVu Sans (in dompdf enthalten), im Muster vermutlich Verdana; ähnlich, nicht gleich.
  - Die Fehlermeldung, falls das PDF scheitert, sagt „Mike Bescheid geben“.
- [ ] **Dateinamen und Download** (2026-11-17, `umsetzung-admin.md` D2d): Im Nachnamen wird alles außer Buchstaben,
  Ziffern und Bindestrich zum Bindestrich („van der Berg“ → `van-der-Berg`), Umlaute bleiben. Für alte Programme gibt
  es einen Ersatznamen ohne Umlaute (`Muessig`). Das ZIP heißt `Honorarnoten_GU_WiSe_2026.zip` (ohne Wochentage; zwei
  Kurse im selben Semester ergäben denselben Namen). PDF-Link in Liste, Detail und Kursseite, ZIP nur auf der
  Kursseite. Der Download speichert die Datei (kein Öffnen im Browser-Tab).
- [ ] **Text der Datenschutz-Information für den Unterricht** (2026-11-17, `umsetzung-admin.md` F1,
  `/datenschutz/unterricht`, Entwurf, keine Rechtsberatung) mit Sonja durchgehen:
  - Ich-Form (wie die Unterrichtsseite), Ansprache „Sie“ für Eltern und erwachsene Schüler:innen in einem Text.
  - Als freiwillig genannt: Geburtsdatum, E-Mail, Telefon (in der App nicht Pflicht). Pflicht: Name und Adresse.
  - **Notizen ohne Gesundheitsdaten:** Angaben wie „heiser, Stimmbandknötchen“ wären Gesundheitsdaten (Art. 9 DSGVO,
    besonders geschützt, Wortlaut nicht nachgelesen). Der Text nennt nur Unterrichtsinhalte; Sonja sollte sich daran
    halten.
  - Sonja gibt Link oder PDF bei der Anmeldung weiter (Art. 13: „zum Zeitpunkt der Erhebung“), auch an die sechs
    Schüler:innen, deren Daten sie jetzt einträgt.
  - PDF (F2, `/datenschutz/unterricht.pdf`): öffnet sich im Browser (`inline`), Speichern unter
    `Datenschutz-Information_Gesangsunterricht.pdf`. Link im Admin oben in „Schüler:innen“. Der Link „Datenschutzerklärung“
    ist im Ausdruck nur unterstrichen, ohne sichtbare Adresse; unten steht die Adresse der Seite.
- [ ] **Wochentermin fest** (2026-11-13, C3): Nach dem Anlegen lassen sich Wochentag und Uhrzeit einer Teilnahme nicht
  mehr ändern, nur einzelne Einheiten verschieben. Ein Umzug aller geplanten Einheiten wäre machbar.
- [ ] **Fälligkeit ohne Vorschlag** (2026-11-13, D1, `erklaerungen/13`): „Zahlbar bis“ muss eingetragen werden. Eine
  feste Frist als Vorschlag wäre eine Einstellung.
- [ ] **Betrag beim Erstellen nicht änderbar** (2026-11-13, D1): kommt aus der Teilnahme (dort für Ermäßigungen
  änderbar).
- [ ] **Leistungstext** „Gesangsunterricht im Wintersemester 2026/27“ vorbelegt, änderbar (2026-11-13, D1).
- [ ] **Zahlungen ohne Rückfrage löschbar** (2026-11-13, D3).
- [ ] **Empfänger der Erinnerung** `music@sonjaczak.com` (2026-11-13, D4); eine eigene Einstellung wäre leicht ergänzt.
- [ ] **Dashboard** (2026-11-13, E, `erklaerungen/14`): Zeitraum „nächste 7 Tage“, Gestaltung beim Zeigen nachschärfen.
- [ ] **CSV mit Sonjas Excel prüfen** (2026-11-13, D5): Umlaute, Beträge als Zahl, Datum.
- [ ] **Ansprache „du“** im Admin-Bereich (2026-11-13); bei Konten für Schüler:innen später ggf. anpassen.
- [ ] **Entwürfe der Seitentexte** durchsehen (seit 2026-11-11, Release-Schritt 4): Texte mit `# Draft` in
  `translations/content.de.yaml`, auch Impressum und Datenschutzerklärung (Entwürfe, keine Rechtsberatung).
- [ ] **Regeln für den Unterricht und FAQ** (2026-11-11, `planung/inhalte.md` E42, E44): Texte von Sonja; die
  Abschnitte bleiben bis dahin ausgeblendet.
- [ ] **KI-Bilder durch Fotos ersetzen** (2026-11-12): `hero-wedding.jpg`, `hero-lessons.jpg`, sobald Fotos da sind.

### Mit der Steuerberatung

- [ ] **Gutschrift und Zahlung als ein Konto** (2026-11-13, D3, `erklaerungen/13`): zu zahlen = Betrag minus
  Gutschriften, Rückzahlung als negative Zahlung an der Gutschrift. Passt das so (`datenmodell.md` 3.7)?
  Dazu (2026-11-16): Honorarnoten werden nie gelöscht (die App hat keinen Weg dafür), jede Korrektur ist eine
  Gutschrift, auch eine volle aus Kulanz, wenn eine Schüler:in vor Kursbeginn absagt. Reicht das, oder braucht es
  ein Storno mit eigener Kennzeichnung?
- [ ] **Name der Schüler:in im Dateinamen** (2026-11-17, `umsetzung-admin.md` F3a): Der Dateiname der Honorarnote
  (`HN17_GU_WiSe_2026_Springer.pdf`) wird mit dem PDF 7 Jahre aufbewahrt. Geht die Honorarnote an einen Elternteil,
  steht der Name der Schüler:in nur im Dateinamen, nicht im PDF. Mit Mike vertretbar, weil Sonja ihre Belege so
  ablegt; passt das für die Steuerberaterin?
- [ ] **§ 19 Abs. 1 Z 1 EStG** (2026-11-13, D5): Ausnahme für regelmäßig wiederkehrende Einnahmen um den Jahreswechsel;
  der Export ordnet streng nach Zahlungsdatum zu.

- [ ] **Honorarnoten je Teilnahme gelöscht** (2026-11-17, `umsetzung-admin.md` F3b, Auslegung): Die 7 Jahre zählen je
  Teilnahme ab dem Ende des Jahres der letzten Honorarnote oder Gutschrift dazu; beide bleiben gemeinsam, weil die
  Gutschrift auf die Honorarnote verweist. Auch bei einer Schüler:in, die noch Unterricht hat, werden Honorarnoten,
  PDFs und Zahlungen danach gelöscht (die Teilnahme selbst bleibt, solange die Person bleibt). Passt das?

### Rechtliches

- [ ] **Datenschutz-Information für Schüler:innen und Eltern** (Art. 13 DSGVO, `umsetzung-admin.md` F): Entwurf zum Prüfen,
  **vor den echten Daten**.
- [ ] **Minderjährige im Unterricht** (2026-11-12, Release-Schritt 6): Der Vertrag kommt mit den Erziehungsberechtigten
  zustande. Die Datenschutz-Information für den Unterricht spricht die Eltern direkt an (Entscheidung 2026-11-17);
  ob die Datenschutzerklärung der Website dazu mehr sagen soll, mit der Rechtslage klären.

- [ ] **IT-Dienstleister in der Datenschutz-Information** (2026-11-17, `umsetzung-admin.md` F1): Mike wird nicht
  namentlich genannt, der Text spricht von „einem IT-Dienstleister, der die Website technisch betreut und die Daten nur
  in meinem Auftrag verarbeitet“ (Mike hat Zugang als Super-Admin und lädt die Backups). Zu klären: Braucht es dafür
  eine Vereinbarung zur Auftragsverarbeitung zwischen Sonja und Mike (Art. 28 DSGVO, nicht nachgelesen)?
- [ ] **Hetzner-Konto läuft auf Mike** (2026-11-19, `backups-ohne-mac.md` Frage 2, abweichend von der Empfehlung
  „Sonja bestellt“): Mike schließt den AVV mit Hetzner, Hetzner ist damit Unterauftragsverarbeiter von Mike. Gehört in
  die Vereinbarung Sonja–Mike (Punkt „IT-Dienstleister“ darunter), samt Sonjas Zustimmung zu Hetzner (Art. 28 Abs. 2
  DSGVO, nicht nachgelesen). Alternative: Konto später auf Sonja übertragen (siehe auch „Notfallzettel“).
- [ ] **Angaben im AVV mit Hetzner** (2026-11-19, Auslegung, keine Rechtsberatung): Angehakt „Personal master data“,
  „Communication data“, „Contractual master data“, „Contract invoicing and payment data“ (nicht „Log data“: die
  Sicherung enthält keine Protokolle); Betroffene „The Client's customers and interested parties“ (Schüler:innen,
  auch Minderjährige, Eltern, Schnupperstunden), nicht „… and employees“. Keine Option für Sonjas IBAN und die
  Zugangsdaten von Sonja und Mike; Hetzner schließt nur die Standardvorlage. Daten sind mit age verschlüsselt, bleiben
  aber personenbezogen.
- [ ] **Text zu Hetzner in der Datenschutz-Information** (2026-11-19, S13, Auslegung): „Die Sicherungskopien liegen
  verschlüsselt bei der Hetzner Online GmbH … Hetzner kann ihren Inhalt nicht lesen“; Aufbewahrung „in einem
  Rechenzentrum in Deutschland“ und **„bis zu 13 Monate“ statt 12** (Monatskopie bis 366 Tage, danach bis 10 Tage in
  Snapshots). Bis die Mac-Sicherung abgeschaltet ist, liegen Kopien auch bei Mike; beim Abschalten die Kopien am Mac
  löschen. Die Datenschutzerklärung der Website bleibt unverändert (Sicherungen enthalten keine Daten von
  Besucher:innen, Anfragen werden nur gemailt).
- [ ] **Admin-Cookie nicht in der Datenschutzerklärung** (2026-11-17, Auslegung zu Mikes Entscheidung): § 165 Abs. 3
  TKG 2021 (RIS, gelesen 2026-11-17) verlangt, „den Nutzer oder Benutzer“ über Daten, Rechtsgrundlage, Zweck und Dauer
  zu informieren, „in geeigneter Form … spätestens bei Beginn der Rechtsbeziehungen“; eine Einwilligung braucht es
  nicht, wenn die Speicherung „unbedingt erforderlich ist“ für einen ausdrücklich gewünschten Dienst. Benutzer des
  Verwaltungsbereichs sind nur Sonja (selbst Verantwortliche) und Mike, die es wissen. Bekommen später Schüler:innen
  ein Konto (`ROLE_STUDENT`), braucht es den Absatz wieder.
- [ ] **Wortlaut von Art. 13 DSGVO gegen EUR-Lex prüfen** (2026-11-17, Mike im Browser): EUR-Lex blockiert
  automatische Abrufe; die Information folgt dem Wortlaut von dsgvo-gesetz.de.

### Technisch (Mike)

- [ ] **Ziehen nicht in die Vergangenheit** (2026-11-20, `umsetzung-admin.md` C4h, Auslegung): Der neue Beginn einer
  gezogenen Einheit muss in der Zukunft liegen, sonst wäre sie nach dem Ablegen „gehalten“. Weitere Annahmen (C4h):
  Als Überschneidung zählen nur Einheiten **desselben Kurses** (wie bei der Doppelstunde) und alle Schnupperstunden;
  ein zweiter Kurs am selben Tag wird nicht geprüft. Die Einzelseite „Einheit bearbeiten“ prüft weiter nicht auf
  Überschneidung.

- [ ] **Elternteile ohne Kind bleiben** (2026-11-17, `umsetzung-admin.md` F3b, Annahme): Der Löschlauf löscht einen
  Elternteil nur, wenn im selben Lauf alle seine Kinder gelöscht werden. Wer von Hand ein Kind löscht oder einen
  Elternteil ohne Kind anlegt, hinterlässt einen Elternteil, den kein Lauf erfasst (es gibt kein Anlegedatum). Selten;
  Abhilfe wäre `guardian.created_at` und dieselbe Frist.

- [ ] **Nachholstunde wird zur regulären Einheit** (2026-11-18, `umsetzung-admin.md` C3/C4, im Code gefolgert, nicht
  getestet): Wird die abgesagte Einheit gelöscht (im Raster oder durch nachträgliche Ferien), setzt die Datenbank den
  Verweis der Nachholstunde auf `NULL` (`Lesson.php`, `onDelete: 'SET NULL'`). Danach zählt der Planer sie als reguläre
  Einheit: Eine Doppelstunde oder „Anzahl senken“ könnte sie wegnehmen, und beim Auffüllen fehlt dann eine. Selten;
  Abhilfe wäre, die Nachholstunde mitzulöschen oder sie als Nachholstunde zu markieren, auch ohne Verweis.
- [ ] **Advisory Lock statt Wiederholung** bei der Nummernvergabe (2026-11-13, D1, `erklaerungen/13`): Abweichung vom
  Datenmodell.
- [ ] **BIC nur per Regex** (2026-11-13, C6, `erklaerungen/12`): `Assert\Bic` bräuchte `symfony/intl`.
- [ ] **OPcache** (2026-11-03): im Plan `planung/wartung.md` W4, freigegeben.
- [ ] **Gekürzte IPs in den Server-Logs prüfen** (`docker compose logs php`, 2026-11-11, Release-Schritt 4).
- [ ] **Bootstrap-CSS verkleinern** (2026-11-11): im Plan `planung/wartung.md` W5 (243 KB, übertragen 30 KB mit
  Brotli, 2026-11-23), freigegeben.
- [ ] **Alte Images in ghcr.io aufräumen** (derzeit kostenlos, laut GitHub-Doku „currently free“, Änderung mit
  1 Monat Vorlauf).
- [ ] **Backups im Ernstfall:** genaue Befehle für das Einspielen auf einem neuen Server ausarbeiten (2026-11-14,
  `planung/backups.md`, „Rücksicherung“). Der Test in die temporäre Datenbank deckt das Einspielen ab, nicht den
  ganzen Ablauf.
- [ ] **Annahmen beim Skript für die Storage Box** (2026-11-18, `backups-ohne-mac.md` Umsetzung 1,
  `deployment.md` S13):
  - Nur SFTP auf Port 22 mit Key im RFC4716-Format; „SSH support“ (Port 23) bleibt aus, „External Reachability“
    an (korrigiert 2026-11-19 nach Mikes Hinweis). Hinterlegen des Keys über Port 22 (Option 3) gegen eine echte Box
    ungetestet; Ausweg in S13.
  - Snapshots täglich 13:00, eine Stunde nach dem Hochladen.
  - Monatsmail am 1. um 13:00; „veraltet“ heißt dort: neueste Sicherung älter als 1 Tag.
  - Format des Unterkonto-Benutzernamens laut Doku nicht belegt (Beispiel nur für den Host `uXXXXX-subX`): aus der
    Console abschreiben.
  - Ob ein Unterkonto `.zfs` sieht, steht nicht in der Doku. Getestet (S13, Teil 5, 2026-11-21): Das Upload-Unterkonto
    sieht `.zfs` nicht, auch mit „Display snapshot directory“; das Hauptkonto sieht es (Einstellung an lassen).
  - **Nur das Hauptkonto holt Dateien aus Snapshots** (2026-11-21, S13 Teil 5): Das Lese-Unterkonto hat dieselbe
    Basis `backups` und sieht `.zfs` vermutlich auch nicht (nicht getestet). Gelöschte oder ältere Sicherungen gibt es
    also nur mit dem Passwort des Hauptkontos; gehört auf den Notfallzettel.
  - **Upload-Key kann `.ssh/authorized_keys` überschreiben** (2026-11-21, S13 Teil 5): Die Datei liegt im Ordner
    `backups` des Upload-Unterkontos. Ein Angreifer mit dem Key könnte uns aussperren, aber nicht an die Snapshots;
    Abhilfe dann über Console bzw. Hauptkonto. Hinnehmbar.
- [ ] **Backups ohne Mikes Mac** (2026-11-17, Mike; ersetzt „Zweite Kopie außer Haus“ vom 2026-11-15,
  `erklaerungen/16`, Abschnitt 5): Heute holt Mikes Mac die Sicherungen (launchd, `deployment.md` S11). Kopie auf eine
  externe Festplatte von Sonja geplant, aber noch nicht gemacht (Stand 2026-11-18): Die Sicherungen liegen derzeit nur
  am Mac. Umsetzung mit der Hetzner Storage Box begonnen (2026-11-18, „Jetzt“): `planung/backups-ohne-mac.md`. Privater age-Schlüssel ausgedruckt und im Passwort-Manager (2026-11-19). Die Datenschutz-Information sagt nur „außerhalb des Servers“ und „bis zu 12 Monate“;
  bei einem Wechsel prüfen, ob das noch stimmt.
- [ ] **Notfallzettel an Sonja übergeben** (2026-11-18, Mike; ausgefüllt 2026-11-23): eine Seite A4 mit age-Schlüssel,
  Ort der Sicherungen, Zugang zu Storage Box (Hetzner-Konto mit Recovery Key, Hauptkonto) und IONOS, Verweis auf die
  Anleitung im Repo. Ausgefüllt nur verschlüsselt gespeichert (`claude_references/notfallzettel.html.age`, lokal).
  Recovery Key auch im Passwort-Manager; wird er benutzt, 2FA neu einrichten und an beiden Stellen ersetzen. Nach dem
  Abschalten des Mac (S13 Teil 6) die Zeile „Auf Mikes Mac“ streichen, dann drucken, laminieren und beim Zeigen der
  Website übergeben. Konten laufen weiter auf Mike; ob sie später auf Sonja übergehen, ist offen.
- [ ] **Postfächer:** Mail-Hosting für Sonjas Adressen unter sonjaczak.com (`music@` liegt bereits bei IONOS; prüfen, ob
  noch etwas offen ist).
- [ ] Kandidaten aus der Durchsicht des Templates (2026-11-03):
  - Symfony Language Server aus dem Dev-Image entfernen (lädt ungepinnt die neueste Version, wird nicht genutzt)
  - GitHub Actions auf Commit-SHA statt Tag pinnen; zizmor für Workflow-Sicherheit
  - Image-Scan auf bekannte Schwachstellen (`docker scout` oder Trivy) in der CI
  - Ungenutzten `allow-plugins`-Eintrag `php-http/discovery` entfernen

## Termine

- **Upgrade auf Symfony 8.2 vor Ende Jänner 2027** (dann endet der Support für 8.1). Danach 8.3 (Mai 2027) und
  8.4 LTS (laut Release-Regeln voraussichtlich November 2027).
- **Rücksicherung der Backups erneut testen** etwa jährlich (nächstes Mal ca. November 2027) und nach jedem
  PostgreSQL-Upgrade (`deployment.md` S11, „Test der Rücksicherung“).
- **Tokens erneuern:** gh CLI läuft 2027-11-02 ab, Server-Token 2027-11-03 (Erinnerung per Cronjob, `deployment.md` S6).

## Fortschritt

Details zu jedem Schritt: `verlauf.md`. Technische Erklärungen: `erklaerungen/`.

**Phase 1** (2026-11-03): Jeder Push auf `main` läuft durch Tests → Build → Deploy und geht auf https://sonjaczak.com
live. Server eingerichtet und abgesichert, Rollback getestet.

**Erstes Release** (2026-11-11/12, Plan `planung/seitenstruktur.md` 10): Twig, Übersetzungen, AssetMapper, Bootstrap,
Layout und Menü, drei Themes, Seiten mit Inhalt, Bilder, Formulare mit Mailversand. Fertig.

**Admin-Bereich** (Plan `planung/umsetzung-admin.md`, Erklärungen in `erklaerungen/08`–`18`):

- [x] A1, B1–B4 Grundlagen, Login, Sessions, Passwort vergessen, Benutzerverwaltung (2026-11-13)
- [ ] B5 Passkeys: B5a Grundlage (Bundle, Tabelle `passkey`, Zufallskennung, 2026-11-17); B5b „Mein Konto“, B5c Login (2026-11-17); B5d lokal geprüft (virtueller Authenticator, Touch ID von Mike, 2026-11-18), gepusht und live 2026-11-18 (CI 44269704195); offen Einrichten mit Sonja (beim Zeigen der Website, verschoben 2026-11-18)
- [x] C1–C3, C5, C6 Schüler:innen, Kurse und Ferien, Teilnahmen, Schnupperstunden, Einstellungen (2026-11-13)
- [x] C4 Kursraster, C7 Anzahl je Teilnahme (2026-11-16, gepusht 2026-11-16); C4f Raster je Unterrichtstag, Hinweis bei Einheit im Ferientag (2026-11-18); C4g Raster als Zeitachse mit Schnupperstunden (2026-11-18, gepusht und live 2026-11-18, CI 44342529876); C4h Einheiten ziehen (2026-11-20, gepusht und live 2026-11-20, CI 44642630806)
- [x] D1, D3–D5, E Honorarnoten, Zahlungen und Gutschriften, Erinnerung (Cronjob läuft), Export, Dashboard (2026-11-13)
- [x] D2 PDF und Download (dompdf, 2026-11-17, gepusht und live 2026-11-17)
- [ ] Backups ohne Mac (`planung/backups-ohne-mac.md`): 1 Skript und Anleitung S13 (2026-11-18); 2 Bestellung, AVV,
  Unterkonten, Snapshots, 3 Server (Key, erster Lauf, Monatsmail), 4 Rücksicherung aus der Storage Box (alle
  2026-11-19), Cronjobs, Datenschutz-Information (2026-11-19); 5 Test gegen Löschen (2026-11-21); offen 6 Mac
  abschalten
- [ ] Wartung (`planung/wartung.md`): W2 Runner auf `ubuntu-26.04` gepinnt, W1 `composer audit` in der CI, W3 Mercure nur lokal, Vulcain entfernt (2026-11-23); offen W4, W5
- [ ] F Vor den echten Daten: Backups (2026-11-15), Datenschutz-Information als Seite und PDF (F1, F2, 2026-11-17, gepusht und live 2026-11-17). F3 automatische Löschung (Befehl `app:privacy:purge`, Cronjob 03:30, 2026-11-17, live)

**Zum Ausprobieren:** `rundgang-admin.md` (lokal mit Fixtures, inklusive was vor dem Zeigen in Produktion passieren
muss).

## Getroffene Entscheidungen

| Bereich | Entscheidung | Begründung |
|---|---|---|
| Hosting | Eigener IONOS-VPS statt Webhosting | VPS ist bereits bezahlt; Webhosting mit SSH wäre erst ab ca. 10 €/Monat verfügbar; volle Kontrolle; Wartung übernimmt Mike |
| Container | Docker Compose lokal und in Produktion | Gleiche Umgebung überall; Workflow bereits bekannt |
| PHP-Server | FrankenPHP über Template dunglas/symfony-docker | Ein Container statt Nginx + PHP-FPM; automatisches HTTPS über Caddy |
| Symfony-Version | 8.1, danach Minor-Upgrades alle 6 Monate bis 8.4 LTS | Neues Projekt ohne Altcode; bis 8.4 LTS nur kleine Minor-Upgrades statt späterem Major-Sprung von 7.4; aktuelle Doku passt. Quelle: symfony.com/releases (8.1: Support bis Jan. 2027) |
| Datenbank | PostgreSQL (Standard des Templates) in eigenem Container, ohne veröffentlichten Port | Kein Umbau des Templates nötig; Docker umgeht ufw für veröffentlichte Ports |
| PostgreSQL-Version | 18 (`postgres:18-alpine`) | Längster Support (bis Nov. 2030, Quelle: postgresql.org/support/versioning); Major-Upgrades erfordern Datenmigration, daher spät wie möglich |
| CI/CD | GitHub Actions, Images in ghcr.io, Deploy per SSH | Kein eigener Runner nötig; Rollback über vorheriges Image-Tag |
| Builds | Images nur in der CI bauen, nie auf dem Server | Server hat ~850 MiB RAM |
| Test-App | smart-ui.mikeczak.com offline genommen | Spart RAM; FrankenPHP kann 80/443 direkt belegen, kein Reverse Proxy nötig |
| Arbeitsumgebung | Ab Repo-Anlage mit Claude Code in der IDE; CLAUDE.md und dieses Dokument liegen im Repo | Direkter Zugriff auf die Codebase; Projektstand wird mit dem Code versioniert |
| Referenzmaterial | `claude_references/` nur lokal, in `.gitignore` (2026-11-11) | Enthält eine echte Honorarnote (DSGVO) und große Originalbilder; ins Repo kommen nur Web-Versionen |
| CSS-Framework | Bootstrap 5.3 mit `symfonycasts/sass-bundle`, Assets über AssetMapper (2026-11-11) | Fertige Komponenten (Navbar, Formulare, Accordion), Erfahrung vorhanden; kein Node nötig. Details: `docs/planung/seitenstruktur.md` |
| Übersetzungen | YAML, Domains `messages` (Zweck-Schlüssel), `content` (Seite.Abschnitt), `validators` (2026-11-11), `admin` (2026-11-13) | Lange Texte lesbarer als XLIFF; `content` wandert später teilweise ins CMS |
| Admin-Oberfläche | Eigene Controller, Forms und Voter, kein EasyAdmin (2026-11-12) | Volle Kontrolle; Mike kennt Symfony-Forms und Voter |
| Daten für Entwicklung | Erfundene Daten aus Fixtures (Zenstruck Foundry, nur `require-dev`); echte Daten nur über die Admin-Masken in Produktion (2026-11-12) | Keine echten Personendaten lokal oder im Repo; Factories auch für Tests |
| Seitenstruktur | Deutsche URLs ohne Sprachpräfix; Impressum als Abschnitt auf `/kontakt`; Ansprache „Sie“, nur Unterricht „Du“; Logo „Sonja Czak“; Google Maps nur als Link (2026-11-11) | Siehe `docs/planung/seitenstruktur.md` |
| Mail-Versand | IONOS-SMTP (`smtp.ionos.de:465`), synchron, Absender `anfrage@sonjaczak.com`, Empfänger `music@sonjaczak.com` (2026-11-11) | Postfach ist bereits bei IONOS, kein weiterer Dienst; kein Messenger-Worker nötig (RAM). Details: `docs/planung/formulare.md` |
| Admin-Planung | Datenmodell, Rollen und Umsetzungsplan freigegeben (2026-11-13) | `planung/datenmodell.md`, `planung/rollen.md`, `planung/umsetzung-admin.md` |
| Vorführung | Kein eigener Demo-Termin: Mike sieht sich die Funktionen lokal an (`rundgang-admin.md`) und zeigt sie Sonja in Produktion (2026-11-13) | Spart eine eigene Demo-Umgebung; Honorarnoten in Produktion verbrauchen aber Nummern |
| PDF-Bibliothek | dompdf (2026-11-14) | HTML aus Twig mit Übersetzungsschlüsseln, LGPL-2.1, läuft im PHP-Container; GD nur für Bilder nötig, die Honorarnote hat keine (2026-11-17). Optionen: `planung/umsetzung-admin.md` D2 |
| Kursraster | Stimulus + `fetch` (2026-11-14) | Tastaturlastig, viele kleine Änderungen; durchschaubar. Live Components ggf. später fürs Booking-Formular. Optionen: `planung/umsetzung-admin.md` C4 |
| Konten in Produktion | Super-Admin (Mike) per Befehl angelegt; Sonjas Konto über die Benutzerverwaltung mit Einladungslink (2026-11-14) | Sonja setzt ihr Passwort selbst |
| Cronjob überfällige Honorarnoten | Freigegeben, täglich 07:30, ohne Prüfung der Zeitzone (2026-11-14) | Es geht um Tage, nicht Stunden; eine Stunde früher oder später ist egal |
| Unterschrift | Keine Unterschrift auf der Honorarnote; Upload-Feld und Spalte entfernt (2026-11-14) | Nicht nötig (Mike); kein Risiko durch eine Unterschrift in PDFs, Datenbank und Backups |
| Doppelklick bei Honorarnoten | Regeln „eine je Teilnahme“ und „Gutschriften bis zum Betrag“ werden unter dem Lock in der Datenbank erneut geprüft (2026-11-14) | Lücke gefunden bei Mikes Frage zu Sperren; `erklaerungen/15-sperren.md` |
| Backups | Plan `planung/backups.md` mit allen Empfehlungen freigegeben (2026-11-14): age, Verschlüsselung am Server, privater Schlüssel im Passwort-Manager, 14 Tage + 12 Monate, täglich 12:00 per launchd, Rücksicherungstest in temporärer Datenbank am Server, Sessions ohne Inhalt | Begründungen im Plan |
| Passkeys (B5) | Dürfen nach dem Start des Echtbetriebs kommen, bleiben aber eingeplant (2026-11-14) | Passwort reicht vorerst; Fingerabdruck ist für Sonja einfacher als ein Einmalcode |
| Passkeys: Umsetzung | `web-auth/webauthn-symfony-bundle`; Fingerabdruck bzw. Geräte-PIN immer verlangt; Zufallskennung `user.webauthn_handle`; Name des Passkeys selbst eingegeben; Verbindung zum Konto nur über `userHandle`; Sonja: je ein Passkey auf MacBook Air und Android-Handy (Samsung A52s), kein iPhone (2026-11-17) | Empfehlungen aus dem Plan (Mike). `umsetzung-admin.md` B5 |
| Absenderdaten | Nur in Produktion unter `/admin/einstellungen`, lokal in `claude_references/` (2026-11-14) | Repo ist öffentlich; IBAN und Unterschrift gehören nicht hinein |
| Status der Einheit | Nur `cancelledBy` gespeichert (nullable: Schüler:in, ich); „geplant“ = Einheit existiert, „gehalten“ aus der Zeit abgeleitet (2026-11-16) | Ableitbare Fakten nicht doppelt speichern; Sonja trägt nur Absagen ein (Mike). `erklaerungen/11`, `datenmodell.md` 3.5 |
| Nachholregel | Mindestzahl = geplante Einheiten des Kurses, kein Schwellwert; Einstellung „Einheiten pro Semester“ belegt neue Kurse vor (2026-11-15) | Absagen durch Schüler:innen werden nie nachgeholt; mehr Einheiten als vereinbart sind möglich, weniger nicht (Mike). `erklaerungen/11` |
| CSRF im Admin | Stimulus-CSRF-Controller aus dem Rezept entfernt; Formulare weiter über Herkunfts-Header geprüft, Raster mit Session-Token (2026-11-16) | Ein Verfahren für ganze Seite und Tests, kein Ausfall bei fehlendem JavaScript. `erklaerungen/17-csrf.md` |
| Kursname | Wochentage aus den Teilnahmen angehängt, „Wintersemester 2026/27 (Fr)“, kein eigenes Feld (2026-11-16) | Nichts doppelt speichern, stimmt immer (Mike). `umsetzung-admin.md` „Kursname“ |
| Einzelne Absage ersatzlos | Kein eigener Absage-Typ; Umweg: Einheit im Raster löschen, dann Anzahl um 1 senken, Grund eintragen (2026-11-16) | Seltener Fall, Umweg reicht vorerst (Mike). `umsetzung-admin.md` C7, „Korrektur“ |
| Anzahl je Teilnahme | Teilnahme hat eine eigene Anzahl der Einheiten (Kopie aus dem Kurs), Grund Pflicht, nicht unter die begonnenen Einheiten; „Teilnahme beenden“ = diese Untergrenze; Geld nur über Gutschrift (2026-11-16) | Häufigster Fall für weniger Einheiten ist ein vorzeitiges Ende aus persönlichem Grund (Mike). `umsetzung-admin.md` C7 |
| Ferien nachträglich | Alle Einheiten im Zeitraum löschen nach Popup mit Liste, am Ende auffüllen; Ferien löschen gibt die Einheiten zurück (2026-11-16) | Nachträgliche Ferien heißen: nichts fand statt (Mike). `umsetzung-admin.md` C4 Fragen 6, 7 |
| PDF der Honorarnote | Erzeugt in derselben Transaktion wie die Nummer; Gutschrift als `HN17_GU_WiSe_2026_Springer_Gutschrift.pdf` (2026-11-17) | Honorarnote und PDF entstehen nur gemeinsam, ein Fehler verbraucht keine Nummer. `umsetzung-admin.md` D2b, D2d |
| Datenschutz-Information Unterricht | Eigene Seite und PDF, spricht die Eltern direkt an (2026-11-17) | Eltern bekommen sie in die Hand, die Seite bleibt verlinkbar. `umsetzung-admin.md` F |
| Speicherdauer | Honorarnoten (PDF und Angaben in der Zeile), Zahlungen und die Zuordnung zur Schüler:in 7 Jahre ab Schluss des Kalenderjahres (§ 132 BAO); alle übrigen Daten bis zum Ende des Folgesemesters nach dem letzten Kurs bzw. der letzten Schnupperstunde (2026-11-17) | Option A, kurze Frist, weil Sonja Kontaktdaten ohnehin selbst hat (Mike). `umsetzung-admin.md` F |
| Datenschutzerklärung der Website | Kein Wort zum Verwaltungsbereich und seinem Session-Cookie; Steuerberaterin Mag.iur. Ulla Kröll namentlich in der Datenschutz-Information Unterricht (2026-11-17) | Betrifft nur Sonja und Mike, keine Besucher:innen; ein Hinweis weniger für Angreifer (Mike). Ersetzt den Textvorschlag in `planung/rollen.md` 10 |
| Automatische Löschung | Vor dem Echtbetrieb, täglich per Befehl; Semester fest September–Februar und März–August; übrige Daten bis Ende des Folgesemesters (auch bei einem Semester Pause); bei aufzubewahrender Honorarnote wird die Schüler:in gelöscht, die Teilnahme bleibt ohne Person; Dateiname der Honorarnote mit dem PDF gespeichert; keine Mail, Hinweis im Dashboard 30 Tage vorher; laufendes Verfahren beim Finanzamt nicht abgebildet (2026-11-17) | App soll Sonja Arbeit abnehmen (Mike); ein Name ist nicht anonym. `umsetzung-admin.md` F3 |
| Raster je Unterrichtstag | Eine Zeile je Unterrichtstag, KW-Spalte mit `rowspan`; ganze Ferienwoche als gelbe Tageszeilen mit Namen bei der KW; Einheit auf Ferientag verschieben erlaubt, mit Hinweis (2026-11-18) | Ferien betreffen einen Tag oder nicht, unmissverständlich (Mike). `umsetzung-admin.md` C4f |
| Raster als Zeitachse | Je Tag eine Zeitachse statt Spalten je Zeitfenster, fest 3 px pro Minute mit waagrechtem Scrollen; Schnupperstunden an ihrer Zeit, Überschneidungen rot und untereinander; Zählung, Name (Link zur Teilnahme) und „Nachholstunde fällig“ (bei der nächsten Einheit) in der Box; Platzhalter „frei“ mit Doppelstunde; Abgesagte in niedriger roter Zeile (2026-11-18) | Uhrzeiten verschiedener Tage stehen minutengenau untereinander, Schnupperstunden werden nicht übersehen (Mike). `umsetzung-admin.md` C4g |
| Backups ohne Mac | Plan freigegeben mit allen Empfehlungen: Hetzner Storage Box BX11, Server lädt täglich 12:00 hoch, Snapshots täglich (10 Tage Schutz gegen Löschen), Sonja bestellt und schließt den AVV, Mac-Sicherung abschalten nach 2 Wochen ohne Fehler und einer Rücksicherung aus der Storage Box (2026-11-18); Umsetzung begonnen 2026-11-18 (Mike) | Täglich unabhängig von Mikes Rechner, Kopien im Rechenzentrum (Mike). `planung/backups-ohne-mac.md` |
| Einheiten ziehen | Nur innerhalb des Tages, nur geplante Einheiten, 5-Minuten-Raster, Ablegen nur ohne Überschneidung mit Einheiten des Kurses und Schnupperstunden; Pointer Events ohne Bibliothek, kein Ziehen mit dem Finger; danach ersetzt die vom Server gezeichnete Tageszeile die alte, ohne Neuladen (2026-11-20) | Abkürzung für Editor → Einzelseite → Beginn ändern (Mike). `umsetzung-admin.md` C4h |
| Port-Namen in `compose.yaml` | Entfernt (`http`, `https`, `http3` aus dem Template); Ports unverändert (2026-11-20) | PhpStorms Compose-Parser lehnt `name:` bei Ports ab, der Docker-Compose-Interpreter für Tests aus der IDE fand sonst keinen Service (Mike). `begriffe.md` „PhpStorm mit Docker“ |
| Web Debug Toolbar | `symfony/profiler-pack` als `require-dev`, nur die Leiste und der Profiler, ohne `symfony/debug-bundle` (2026-11-20) | Mike will sehen, welcher Controller und welche Templates eine Seite erzeugen; nicht in Produktion (`--no-dev`). `begriffe.md` |
| Wartung | Plan `planung/wartung.md` mit allen Empfehlungen freigegeben: `composer audit --locked --abandoned=report` in der CI, Runner `ubuntu-26.04`, Vulcain entfernen, Mercure nur noch lokal für den Hot Reload (Variante A2, nachträglich 2026-11-23), OPcache über Admin-Seite „System“ messen, Bootstrap nur mit benötigten Teilen (abbrechen unter einem Drittel Ersparnis) (2026-11-23) | Begründungen im Plan (Mike) |
| Dokumentation | Offenes nur in `projektstand.md`, Geschichte in `verlauf.md`, Erklärungen nur zum Verstehen; Server-Schritte S1–S10 (2026-11-14) | Offene Punkte waren über sieben Stellen verstreut, Kürzel doppelt vergeben (B1–B8 in `deployment.md` und in `umsetzung-admin.md`) |

## Server-Konfiguration

IONOS-VPS mit Ubuntu 24.04, 1 vCore, ~850 MiB nutzbarem RAM und 8,7 GB Platte. Ressourcen sind knapp und
bei jeder Entscheidung zu berücksichtigen.

| Bereich | Einstellung | Datei / Befehl |
|---|---|---|
| Swap | 1 GB Swapfile | /swapfile, Eintrag in /etc/fstab |
| Journal | max. 200 MB | /etc/systemd/journald.conf.d/size.conf |
| Docker-Logs | json-file, 3 × 10 MB pro Container | /etc/docker/daemon.json |
| SSH | Kein Root-Login, kein Passwort-Login, nur Key | /etc/ssh/sshd_config.d/00-hardening.conf |
| Firewall | ufw: 22, 80/tcp, 443/tcp, 443/udp | `sudo ufw status verbose` |
| Updates | unattended-upgrades, Neustart um 04:00 | /etc/apt/apt.conf.d/52unattended-upgrades-local |
| Benutzer | mike (sudo); deploy (ohne sudo, Gruppe docker) für GitHub Actions; dbbackup (ohne Docker, nur Backup-Skript per sudo) | `id mike`, `deployment.md` S1, S11 |
| Backups | Mac holt täglich 12:00 einen mit age verschlüsselten Dump; Skripte in /usr/local/sbin, sudo-Regeln in /etc/sudoers.d/sonjaczak-backup | `deployment.md` S11 |
| Cronjobs (root) | Token-Erinnerungen (`deployment.md` S6), überfällige Honorarnoten 07:30 (S8), automatische Löschung 03:30 (S12) | `sudo crontab -l` |
