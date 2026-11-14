# Phase 2, Schritt 6: Formulare mit Mailversand

Stand: 2026-11-11 – Entscheidungen getroffen, freigegeben.

Grundlage: `inhalte.md` (C30, E43, F50), `seitenstruktur.md` (Abschnitt 8). Wie gehabt: **Entscheidung** mit Bindestrich
zum Beantworten, der Rest ist mein Vorschlag.

## 1. Was gebaut wird

Drei Formulare, die je eine Mail an Sonja schicken. Sie ersetzen die `mailto:`-Links (`_partials/mail_inquiry.html.twig`).

| Formular | Seite | Felder (* = Pflicht) | Quelle |
|---|---|---|---|
| Allgemein | `/kontakt` | Name*, E-Mail*, Nachricht* | Vorschlag |
| Booking solo | `/booking/solo` | Name*, E-Mail*, Telefon, Anlass*, Datum*, Ort*, Nachricht* | C30 |
| Unterricht | `/unterricht` | Name*, E-Mail*, Telefon, Alter*, Erziehungsberechtigte:r* (nur unter 18), Vorerfahrung, gewünschte Uhrzeit*, Nachricht | E43 |

- **Anlass** als Auswahl aus der Liste auf der Seite (Trauung, Taufe, Agape, Firmenfeier, Geburtstag) plus „Anderer
  Anlass“. Die Auswahlwerte kommen aus den Übersetzungen, damit die Liste auf der Seite und im Formular gleich bleibt.
- **Datum** als `<input type="date">` (Kalender auf dem Handy), darf nicht in der Vergangenheit liegen.
- **Erziehungsberechtigte:r:** Ein kleines Script blendet das Feld ein, sobald das Alter unter 18 ist. Ohne
  JavaScript ist das Feld immer sichtbar, mit dem Hinweis „nur bei Schüler:innen unter 18“. Die eigentliche Prüfung
  macht der Server (Pflicht, wenn Alter < 18). Ein Stimulus-Controller wäre die Symfony-UX-Variante. Die Entscheidung
  zu Symfony UX ist aber noch offen (Projektstand), und für ein Feld reicht ein kleines ES-Modul über die Importmap.

**Entscheidung 1 – Felder:** Passen die Felder? Offen sind vor allem: Telefon beim Unterricht Pflicht? Gewünschte
Uhrzeit als Freitext oder Auswahl (z. B. „ab 14 Uhr / ab 16 Uhr / egal“)? Optionales Nachrichtenfeld beim
Unterricht? Telefon im allgemeinen Formular? - Telefon bei Unterricht nicht Pflicht, Uhrzeit als Freitext mit placeholder: z. B. „ab 14 Uhr / ab 16 Uhr / egal“

## 2. Mailversand (F50: „Ist Mail-Versand auch über IONOS möglich?“)

Ja. Die Domain hat bereits IONOS-Mail (MX `mx00/mx01.ionos.de`, siehe `deployment.md` S10). Laut IONOS-Hilfe
(ionos.de/hilfe → „Serverinformationen für IMAP, POP3 und SMTP“) gilt:

- Server `smtp.ionos.de`, Port 465 mit SSL/TLS, alternativ Port 587 mit STARTTLS
- Benutzername ist die vollständige E-Mail-Adresse, jede Adresse hat ein eigenes Passwort
- Seit 29.01.2024 akzeptiert der Server nur Absender mit „derselben Absenderdomain“. Wir können also nicht im Namen
  der Besucher:innen senden. Die Mail geht **von** einer sonjaczak.com-Adresse, die Adresse der Besucher:in steht
  in `Reply-To`. Sonja klickt dann einfach auf „Antworten“.

Symfony Mailer (`symfony/mailer`) mit `MAILER_DSN=smtps://…@smtp.ionos.de:465` (symfony.com/doc/current/mailer.html).
Sonderzeichen im Passwort müssen URL-kodiert sein (laut derselben Seite).

| Variante | Vorteil | Nachteil |
|---|---|---|
| **IONOS-SMTP** (Vorschlag) | Schon bezahlt, schon in der Datenschutzerklärung (Hoster), kein neuer Vertrag/AVV, SPF passt bereits | Kein Versand-Dashboard; Limits nicht dokumentiert (bei ein paar Anfragen pro Woche egal) |
| Externer Dienst (z. B. Brevo, Mailjet; beide EU) | Statistik, Bounce-Handling, Symfony-Bridge vorhanden | Neues Konto, AVV, zusätzlicher Empfänger in der Datenschutzerklärung |
| Eigener Mailserver am VPS | – | RAM, Wartung, schlechte Zustellbarkeit von VPS-IPs. Nein. |

**Entscheidung 2 – Postfach:** Existiert `music@sonjaczak.com` schon als IONOS-Postfach? Mein Vorschlag: eine eigene
Absenderadresse `website@sonjaczak.com` (falls das Mail-Paket eine weitere Adresse erlaubt). Dann liegt nicht das
Passwort von Sonjas Hauptpostfach auf dem Server, und Sonja kann Website-Mails leicht filtern. Empfänger bleibt
`music@sonjaczak.com`. Alternative: direkt mit `music@` senden (weniger Aufwand, aber ein wichtigeres Passwort am
Server). - ich habe anfrage@sonjaczak.com angelegt, das können wir als Absender verwenden. Wo muss ich das Passwort hinterlegen?

**Synchron senden:** Messenger ist nicht installiert, die Mail geht also direkt beim Absenden raus (laut Mailer-Doku
werden Mails nur mit Messenger standardmäßig asynchron verschickt). Asynchron hieße: Worker-Prozess, mehr RAM. Für
ein paar Mails pro Woche reicht synchron, die Antwortzeit liegt dann bei etwa einer Sekunde.

**Fehlerfall:** Schlägt der Versand fehl (`TransportExceptionInterface`), bleibt das Formular ausgefüllt, und es
erscheint eine Meldung mit dem `mailto:`-Link als Ausweg. Ins Log kommt nur „Versand fehlgeschlagen“ mit dem
Formulartyp, **keine Formulardaten** (DSGVO-Regel aus `CLAUDE.md`).

**Mail-Inhalt:** Nur Text (`templates/email/*.txt.twig`), keine HTML-Mail. Das braucht kein Paket für das Einbetten
von CSS, ist robust und reicht für Sonja. Betreff z. B. „Anfrage Unterricht: Max Muster“.

## 3. Schutz vor Spam und Missbrauch

Kein CAPTCHA: reCAPTCHA und hCaptcha wären Drittanbieter und müssten in die Datenschutzerklärung.

1. **Honeypot:** ein für Menschen unsichtbares Feld. Ist es ausgefüllt, tut der Server so, als wäre alles gesendet
   worden, verschickt aber nichts.
2. **Rate Limiter** (`symfony/rate-limiter`): z. B. 5 Anfragen pro Stunde und IP. Speicher ist der Cache im
   Dateisystem, also kein Redis. Die IP wird dabei nur gehasht im Cache abgelegt und verfällt nach Ablauf des Fensters.
3. **CSRF:** Laut Symfony-Doku sind zustandslose CSRF-Tokens mit Flex standardmäßig aktiv. Sie prüfen die Header
   `Origin`/`Referer` und brauchen **keine Session und kein Cookie**. Wichtig, weil die Seite bisher keine Cookies setzt.
4. **Keine Bestätigungsmail an die Absender:in.** Sonst könnte jemand über das Formular Mails an beliebige Adressen
   auslösen. Stattdessen erscheint auf der Seite eine klare Bestätigung.

Später nachrüstbar, falls trotzdem Spam kommt: Mindest-Ausfüllzeit (signierter Zeitstempel im Formular).

## 4. Ablauf im Browser, ohne Session

Absenden → Server prüft → bei Fehlern dieselbe Seite mit Meldungen (HTTP 422) → bei Erfolg Weiterleitung (Post/
Redirect/Get) auf `…?gesendet=1#anfrage` mit einer Dankesbox. Flash-Messages würden eine Session und damit ein
Cookie brauchen. Mit dem Query-Parameter bleibt die Seite cookie-frei.

Eingaben bleiben bei Versandfehlern, Eingabefehlern und Rate Limit erhalten (Seite wird mit den Werten erneut
angezeigt). Nur bei Netzabbruch oder unerwartetem 500er hilft allein der Zurück-Button des Browsers. Ein Entwurf in
`sessionStorage` wäre ein kleines ES-Modul, speichert aber Daten im Endgerät (ePrivacy Art. 5 Abs. 3 / § 165 Abs. 3
TKG 2021) und auf geteilten Geräten. Vorschlag: vorerst nicht; nachrüsten, falls Anfragen verloren gehen.

**Entscheidung 3 – Speichern:** Anfragen nur per Mail schicken, **nicht** in der Datenbank speichern (Datenminimierung;
mit dem Admin-Bereich kann das später kommen). Einverstanden? - ja

## 5. Datenschutz

- Unter jedem Formular ein kurzer Hinweis mit Link auf `/datenschutz`, keine Einwilligungs-Checkbox. Rechtsgrundlage
  für eine Anfrage ist Art. 6 Abs. 1 lit. b DSGVO (vorvertragliche Maßnahmen „auf Anfrage der betroffenen Person“).
  Das prüfe ich beim Schreiben gegen den Verordnungstext. Bei Unterrichtsanfragen für Minderjährige nennen wir
  bewusst die Erziehungsberechtigten als Kontakt. Diesen Punkt kläre ich beim Textentwurf genauer und gebe ihn dir
  als Entwurf, keine Rechtsberatung.
- Datenschutzerklärung ergänzen: welche Daten, Zweck, Empfänger (IONOS als Mail-Anbieter), Speicherdauer (im
  Postfach, solange für die Anfrage nötig), Rate Limiter (gehashte IP, max. 1 Stunde).
- Impressum: Das Kontaktformular erfüllt den „zweiten schnellen Kontaktweg“ (Projektstand, EuGH C-298/07).

## 6. Technik

- **Pakete:** `symfony/mailer`, `symfony/form`, `symfony/validator`, `symfony/rate-limiter` (CSRF-Paket, falls Form es
  nicht mitbringt; prüfe ich mit `composer why`). Versionen prüfe ich nach der Installation in `composer.lock`.
- **Aufbau:** je Formular eine Datenklasse mit Validator-Attributen (`src/Inquiry/…Inquiry.php`, keine Entity) und ein
  Form-Type (`src/Form/…Type.php`); ein Service `InquiryMailer` baut und verschickt die Mail. Die Controller bleiben
  dünn. Übersetzungen: Labels in `messages`, Fehlermeldungen in `validators`.
- **Konfiguration:** `MAILER_DSN=null://null` in `.env` (verschickt nichts), CI dito. Am Server kommt `MAILER_DSN`
  in `/srv/sonjaczak/.env`, `compose.prod.yaml` reicht es als Pflichtvariable (`${MAILER_DSN:?…}`) durch. Absender-
  und Empfängeradresse als Parameter in `config/services.yaml`.
- **Lokal testen:** Das Mailer-Rezept bietet Mailpit an (fängt Mails ab, Web-Oberfläche). Das wäre ein zusätzlicher
  Container, **nur lokal** in `compose.override.yaml`, nicht am Server.
  **Entscheidung 4 – Mailpit lokal:** ja / nein (dann nur Tests und `null://null`) - ja
- **Tests** (PHPUnit, `assertEmailCount`): gültige Anfrage → genau 1 Mail mit richtigem `To`/`Reply-To` und
  Weiterleitung; ungültige → 422 und keine Mail; Honeypot → keine Mail; Unterricht unter 18 ohne
  Erziehungsberechtigte:n → Fehler. Laut Mailer-Doku darf der Test-Client der Weiterleitung nicht folgen, sonst ist
  die Mail aus dem Zähler verschwunden.
- **Ressourcen:** keine neuen Container in Produktion, kein Worker. Pakete und Cache machen nur wenige MB aus;
  Image-Größe messe ich nach.

## 7. Reihenfolge

- **6a** Mailer installieren, `MAILER_DSN` lokal/CI/Server, Testversand vom Server mit `bin/console mailer:test`
  (prüft auch, ob der VPS ausgehend Port 465 erreicht). **Am Server, also mit dir gemeinsam.**
- **6b** Allgemeines Kontaktformular inkl. Honeypot, Rate Limiter, Tests, Mail-Template.
- **6c** Booking-Formular.
- **6d** Unterrichtsformular inkl. Erziehungsberechtigte:r (Script und Server-Prüfung).
- **6e** Datenschutzerklärung und Formular-Hinweise; `mail_inquiry`-Partial entfernen.

**Blocker für den Deploy:** Die CI startet seit 2026-11-09 nicht (`startup_failure`, Projektstand). 6b–6e lassen
sich lokal bauen. Für 6a am Server und jeden Deploy muss das vorher gelöst sein.
