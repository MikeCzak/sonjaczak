# Mini-CMS: Seitentexte im Admin-Bereich

Stand: 2026-11-24. Entscheidungen von Mike (2026-11-23) übernommen. **K1–K3 umgesetzt (2026-11-23).**
Ausprobieren: `rundgang-admin.md` 6a. **K4 (Bearbeiten direkt auf der Seite) umgesetzt 2026-11-24**; alles gepusht 2026-11-24
(Antworten Mike: 1 A, 2 Schalter, 3 behalten, 4 nur Seitentexte). Erklärung: `erklaerungen/25-seitentexte-decorator.md`.

Ziel: Sonja ändert die Texte der öffentlichen Seiten selbst, ohne Mike und ohne Deploy. Vorgemerkt seit
`inhalte.md` Frage 18 und 19 („am ehesten Preise, Unterrichtszeiten, Startseitentext“, Hero-Satz) und
`anforderungen.md` E14.

## Entscheidungen (Mike, 2026-11-23)

| Frage | Entscheidung |
|---|---|
| Wo liegen geänderte Texte? | Eigene Tabelle `page_text`; ohne Eintrag gilt der Text aus `translations/content.de.yaml` |
| Welche Texte? | Alle Seitentexte (Domain `content`) |
| Formatierung (Fett, Links)? | Vorerst nicht, reiner Text wie bisher (Leerzeile = Absatz, in Listen eine Zeile je Punkt) |
| Zwei Quellen (YAML und Datenbank) | Marker „geändert am … von …“ und Knopf „Auf Standard zurücksetzen“ |
| Preis des Unterrichts | Als Wert in den Einstellungen (Vorgabe), beim Anlegen eines Kurses vorbelegt und änderbar; der Text auf `/unterricht` zeigt den Wert aus den Einstellungen |

## Wie die Texte gelesen werden: Optionen

Alle Templates holen Seitentexte heute mit `'lessons.prices.body'|trans({}, 'content')` (99 Stellen in 13 Templates).

**A. Decorator um den Translator (gewählt).** Eine eigene Klasse `App\Content\PageTextTranslator` umhüllt Symfonys
Translator-Dienst (`#[AsDecorator('translator')]`). Fragt jemand einen Text der Domain `content` an und gibt es dazu
einen Eintrag in `page_text`, kommt dieser zurück, sonst reicht die Klasse die Anfrage unverändert weiter.

- Pro: Kein Template ändert sich. `debug:translation` (und damit `TranslationsTest`) sieht die Schlüssel weiter.
  Seitentexte im PDF der Datenschutz-Information kommen auf demselben Weg.
- Contra: Weniger sichtbar. Wer im Template `|trans` liest, sieht nicht, dass die Datenbank mitredet (Kommentar im
  YAML und in `begriffe.md`). Jeder `trans()`-Aufruf geht durch die Klasse, für andere Domains nur ein Vergleich.

**B. Eigener Twig-Filter `|page_text`** statt `|trans` (so im Chat zuerst skizziert).

- Pro: Im Template sofort sichtbar.
- Contra: 99 Stellen ändern. `debug:translation` erkennt die Schlüssel nicht mehr; die Prüfung auf fehlende
  Schlüssel müsste der Filter selbst übernehmen.

**C. Translation-Loader aus der Datenbank.** Symfony lädt Übersetzungen aus der Tabelle in den Katalog.

- Contra: Der Katalog wird beim Build gecacht. Nach jeder Änderung müsste der Übersetzungs-Cache im laufenden
  FrankenPHP-Worker neu gebaut werden; dieses Image baut den Cache beim Build, nicht am Server.

Wechsel von A nach B ist später ohne Datenänderung möglich.

## Umsetzung

### K1. Tabelle und Decorator

- Entity `PageText`: `id`, `text_key` (eindeutig, z. B. `lessons.prices.body`), `body` (Text), `updated_at`,
  `updated_by` (Benutzer, `ON DELETE SET NULL`). Migration.
- `PageTextTranslator`: lädt beim ersten Seitentext einer Anfrage alle Einträge mit einer Abfrage, merkt sie sich
  bis zum Ende der Anfrage (`ResetInterface`, wichtig im Worker-Modus: sonst sähe ein Worker geänderte Texte nicht).
  Platzhalter (`%email%`) ersetzt derselbe Formatter wie bei YAML-Texten (seit 2026-11-24, vorher eigenes `strtr()`).
  Nur für Deutsch (die einzige Sprache).
- Ist die Datenbank nicht erreichbar, gelten die YAML-Texte (wie beim Theme, `SiteSettings`).

### K2. Admin „Texte“

- Menüpunkt „Texte“ (`/admin/texte`), für Sonja und Mike (`ROLE_ADMIN`).
- Liste nach Seite gruppiert (Startseite, Über mich, Booking …), je Text Bezeichnung, Anfang des Textes und der
  Marker „geändert am … von …“.
- Bearbeiten: Textfeld mit dem aktuellen Text, darunter der Standardtext aus dem Repo. „Speichern“ und, wenn
  geändert, „Auf Standard zurücksetzen“ (löscht den Eintrag).
- Ist der gespeicherte Text gleich dem Standard, wird kein Eintrag angelegt bzw. der Eintrag gelöscht.
- Zeilenumbrüche aus dem Browser (`\r\n`) werden zu `\n`, sonst erkennt die Seite keine Absätze.
- Platzhalter: Erlaubt sind nur die, die der Standardtext enthält (`%price%` …). Ein unbekannter würde wörtlich auf
  der Seite stehen und wird abgelehnt. Fehlt einer, ist das Sonjas Entscheidung (Hinweis im Formular).

### K3. Preis als Wert

- `Settings.lessonPriceCents` (Vorgabe € 495,–, der heute veröffentlichte Preis), Feld in den Einstellungen.
- „Kurs anlegen“ belegt den Preis damit vor (wie heute schon Einheiten und Dauer), änderbar.
- `lessons.prices.body` und `lessons.dates.body` bekommen Platzhalter: `%price%`, `%lessons%` (Einheiten pro
  Semester aus den Einstellungen), `%per_lesson%` (Preis ÷ Einheiten). Format „€ 495,–“ bei ganzen Euro, sonst
  „€ 33,33“.

### Umgesetzt (2026-11-23)

| Teil | Datei |
|---|---|
| Tabelle, Migration | `src/Entity/PageText.php`, `src/Migrations/Version20261123202219.php` (auch `settings.lesson_price_cents`, Vorgabe 49500) |
| Decorator | `src/Content/PageTextTranslator.php` |
| Lesen und Ändern | `src/Content/PageTexts.php`, `src/Controller/Admin/PageTextController.php`, `src/Form/Admin/PageTextType.php`, `templates/admin/page_text/` |
| Preis | `Settings::$lessonPriceCents`, `SiteSettings::lessonParameters()`, `CourseController::new()`, `lessons/index.html.twig` |
| Tests | `tests/Admin/PageTextTest.php` (8 Tests) |

Bezeichnungen in der Liste: Überschrift des Abschnitts, wie sie auf der Seite steht, und die Art des Textes
(„Preise · Text“, „Anlässe · Liste“). Die Art kommt aus `admin.de.yaml` (`page_text.part.*`); kommt im YAML eine neue
Endung dazu, braucht sie dort einen Eintrag, sonst steht der Schlüssel da.

Geprüft: alle Tests (223), PHPStan, Twig- und YAML-Lint, Schema; `cache:warmup` in prod (der Decorator reicht das
Vorwärmen der Übersetzungen weiter). Die Admin-Seiten nur über Tests, nicht per Screenshot.

## K4. Bearbeiten direkt auf der Seite (freigegeben und umgesetzt 2026-11-24)

**Wunsch (Mike, 2026-11-24):** Statt einer zentralen Liste bei jedem Text dort, wo er angezeigt wird, ein Knopf
„Bearbeiten“. Er öffnet per JavaScript ein Textfeld, dazu „Zurücksetzen“, wenn der Text geändert ist. Nur sichtbar für
angemeldete Benutzer mit `ROLE_ADMIN` oder `ROLE_SUPER_ADMIN`.

**Machbar: ja.** K1–K3 bleiben die Grundlage (Tabelle, Decorator, Speichern, Zurücksetzen, Platzhalter); K4 ändert
nur, wo Sonja bearbeitet. Die eine echte Hürde ist Frage 1.

### Frage 1: Woher weiß eine öffentliche Seite, dass Sonja angemeldet ist?

Heute gar nicht, mit Absicht (`rollen.md` 3, `erklaerungen/08`): Die Firewall gilt nur für `/admin`, und das
Session-Cookie hat den Pfad `/admin` (`framework.session.cookie_path`). Der Browser schickt es nie an öffentliche
Seiten. `PublicPagesTest` prüft, dass öffentliche Seiten kein Cookie setzen.

**A. Session auch auf öffentlichen Seiten (Empfehlung).** Cookie-Pfad `/`, eine Firewall für die ganze Website,
weiter `lazy`. Anmeldung bleibt unter `/admin/login`, `access_control` schützt `/admin` wie bisher.

- Pro: Genau der Wunsch. Sonja surft auf der normalen Website und sieht die Knöpfe.
- Pro: Für Besucher:innen ändert sich nichts. Ohne Cookie im Browser liest die Firewall keine Session
  (`vendor/symfony/security-http/Firewall/ContextListener.php` Zeile 91: nur bei `hasPreviousSession()`) und setzt
  kein Cookie; `PublicPagesTest` prüft das weiter. Die Datenschutzerklärung („setzen keine Cookies“) bleibt richtig, weil
  das Cookie nur bei der Anmeldung entsteht, also nur bei Sonja und Mike.
- Contra: Hebt eine bewusste Entscheidung auf (`rollen.md` 3). Sonjas Session-Cookie geht dann bei jedem Aufruf der
  Website mit, nicht nur im Admin-Bereich. `SameSite=Lax` bleibt, Formulare auf fremden Seiten können es weiter nicht
  mitschicken.
- Contra: Für Sonja und Mike liest jede öffentliche Seite die Session aus der Datenbank (eine Abfrage), und die
  Antwort wird `Cache-Control: private`. Für Besucher:innen nicht.

**B. Bearbeitungsansicht unter `/admin`.** Ein Menüpunkt „Website bearbeiten“ öffnet z. B. `/admin/website/unterricht`;
der Controller gibt die Anfrage an die öffentliche Seite weiter (`forward()`) und schaltet die Knöpfe ein. Links in der
Seite schreibt ein Skript auf die Bearbeitungsansicht um.

- Pro: Die öffentlichen Seiten und die Entscheidung `rollen.md` 3 bleiben unverändert.
- Contra: Nicht ganz der Wunsch: Sonja muss über den Admin-Bereich hinein, auf der normalen Website sieht sie keine
  Knöpfe. Zwei Adressen je Seite, umgeschriebene Links, Formulare auf den Seiten nur zum Ansehen.

**C. Skript fragt bei jedem Seitenaufruf `/admin` an, ob jemand angemeldet ist.** Das Cookie geht an `/admin` mit.

- Contra: Jeder Besuch löst eine zusätzliche Anfrage aus, bei allen Besucher:innen, nur damit zwei Personen
  Knöpfe sehen. Nicht empfohlen.

Antwort: A, Session auch auf öffentlichen Seiten (Mike, 2026-11-24).

### Frage 2: Knöpfe immer sichtbar oder per Schalter?

- **Schalter (Empfehlung):** Für Angemeldete eine kleine Leiste am unteren Rand mit „Texte bearbeiten“ (an/aus) und
  einem Link in den Admin-Bereich. Aus: Sonja sieht die Seite wie Besucher:innen. An: neben jedem Text ein Stift. Der
  Zustand bleibt in `sessionStorage` (nur bei Sonja und Mike, endet mit dem Tab), damit er das Neuladen nach dem
  Speichern übersteht.
- Immer sichtbar: einfacher, aber jede Seite ist für Sonja voller Stifte, auch wenn sie sie nur ansehen will.

Antwort: Schalter (Mike, 2026-11-24).

### Frage 3: Was wird aus der Liste `/admin/texte`?

- **Behalten als Übersicht (Empfehlung):** Sie zeigt alle geänderten Texte auf einen Blick und erreicht Texte ohne
  sichtbaren Platz: Bildbeschreibungen (`image_alt`), Texte, die nur in einer Adresse stecken (Google-Maps-Link), und
  leere Abschnitte. Im Menü weiter „Texte“, auf der Seite ein Hinweis auf das Bearbeiten direkt auf der Seite.
- Entfernen: weniger Code; dann braucht jeder Sonderfall eine Lösung auf der Seite (siehe unten).

Antwort: Behalten (Mike, 2026-11-24).

### Frage 4: Auch Seitentitel und Menü?

Die großen Titel (`<h1>`, z. B. „Gesangsunterricht“), Menüpunkte, Knöpfe und Formularfelder stehen nicht in
`content`, sondern in `messages`. Sie sind Teil der Struktur und tauchen an mehreren Stellen auf (Titel auch im
Browser-Tab und im Menü).

- **Nein, vorerst nur `content` (Empfehlung):** klare Grenze, „alle Seitentexte“ wie entschieden.
- Ja, die Seitentitel: Sie wandern nach `content`, damit sie bearbeitbar werden; Menü und Tab-Titel bleiben gleich
  oder ändern sich mit (dann noch zu entscheiden).

Antwort: Nur Seitentexte (Mike, 2026-11-24).

### Technischer Entwurf (gilt für jede Antwort auf Frage 1)

- **Markierung im Template:** Das Element, das einen Text zeigt, bekommt ein Attribut über eine Twig-Funktion:
  `<p {{ page_text_attr('lessons.audience.body') }}>…</p>`. Für Besucher:innen gibt sie nichts aus, für Angemeldete
  `data-page-text="lessons.audience.body"`. Den Text selbst (`|trans`) fasst das nicht an, der Decorator bleibt.
  Etwa 99 Stellen in 13 Templates, rein mechanisch.
- **Warum nicht automatisch um jedes `|trans` einen Rahmen legen?** Viele Texte stehen nicht frei im HTML: in
  Attributen (`alt`), in Bedingungen (leere Abschnitte), zerlegt in Absätze oder Listen (`text.paragraphs`,
  `text.list`), in einer Adresse (Google Maps). Ein `<span>` darum herum zerstört dort das HTML. Die Markierung am
  umgebenden Element funktioniert überall.
- **Leere Abschnitte** (Regeln, FAQ, Satz auf der Startseite): Für Angemeldete im Bearbeitungsmodus ein Platzhalter
  „Regeln: noch kein Text, hinzufügen“, sonst gäbe es keine Stelle zum Klicken.
- **Bildbeschreibung:** der Stift am Bild.
- **JavaScript:** ein Stimulus-Controller, eigener Importmap-Einstieg, geladen nur für Angemeldete. Besucher:innen
  laden kein zusätzliches Skript. Klick auf den Stift: Der Controller holt per `fetch` den rohen Text (mit
  `%price%`, nicht dem ersetzten Preis), den ursprünglichen Text, die Markierung „geändert“ und die Platzhalter
  (`GET /admin/texte/{key}`, JSON). Er zeigt an der Stelle ein Textfeld mit „Speichern“, „Abbrechen“ und, wenn
  geändert, „Zurücksetzen“.
- **Speichern:** `POST` als JSON mit Session-Token gegen CSRF (wie beim Kursraster, `erklaerungen/17`), danach lädt
  die Seite neu, an derselben Stelle. Den Text an Ort und Stelle zu ersetzen hieße, das Stück Template auf dem Server
  zu rendern (Absätze, Listen, Platzhalter); das Neuladen ist einfacher und zeigt immer genau, was Besucher:innen
  sehen.
- **PDF der Datenschutz-Information:** Die Markierungen sind Attribute ohne sichtbare Wirkung; Platzhalter für leere
  Abschnitte erscheinen dort nie (das PDF hat keine leeren).
- **Tests:** Besucher:innen sehen keine Attribute, kein Skript, kein Cookie; Angemeldete sehen die Attribute; JSON-
  Endpunkte nur mit `ROLE_ADMIN`, mit Token, mit denselben Prüfungen wie das Formular (Platzhalter).

**Aufwand:** größer als K1–K3. Die meiste Arbeit sind die Markierungen in den Templates und der Controller in
JavaScript; bei Antwort A kommt die Umstellung von Firewall und Cookie samt Tests und Doku (`rollen.md` 3,
`erklaerungen/08`) dazu.

### K4 umgesetzt (2026-11-24)

| Teil | Datei |
|---|---|
| Firewall für alle Pfade, Cookie-Pfad `/` | `config/packages/security.yaml`, `config/packages/framework.yaml` |
| Markierung | `src/Content/PageTextEditorExtension.php` (`page_text_attr()`, `page_text_editor()`), 13 Templates, Makros `text.paragraphs`/`text.list` mit drittem Parameter `attrs` |
| Leere Texte | `templates/_partials/page_text_empty.html.twig`; Regeln/FAQ als versteckter Abschnitt nur für Admins |
| Leiste und Bearbeitungsfeld | `templates/_partials/page_editor.html.twig`, `assets/controllers/page_editor_controller.js`, `assets/styles/partials/_page-editor.scss` |
| JSON-Endpunkte | `PageTextController::data()`, `save()`, `resetJson()` (`/admin/texte/{key}/daten`, `/speichern`, `/zuruecksetzen.json`), Session-Token `page-text` im Header `X-CSRF-Token` |
| Tests | `tests/Admin/PageTextTest.php` (15), `LoginTest` (Cookie-Pfad `/`) |

Geprüft: 230 Tests, PHPStan, Lint, prod `cache:warmup`; im echten Chrome (DevTools, als Sonja angemeldet): Schalter an
→ 23 Stifte auf `/unterricht`, leere Abschnitte erscheinen, Preis bearbeiten zeigt `%price%`, `%preis%` wird mit
Meldung abgelehnt, Speichern lädt neu und bleibt im Bearbeitungsmodus, Markierung „geändert am … von …“,
Zurücksetzen. Akkordeon „Stornobedingungen“: Stift öffnet nur das Feld, klappt nicht auf.

Zwei Fehler dabei gefunden und behoben: Das Bearbeitungsfeld liegt außerhalb des Controller-Elements, dort wirken
Stimulus-`data-action`s nicht (das Formular wurde normal abgeschickt); die Ereignisse hängt jetzt der Controller an.
Beim Akkordeon saß der Stift im `<button>` und das Feld im `<h2>`: Markierung jetzt am `<h2>`, Feld mit eigener Schrift.

**Hinweis je Art des Textes** (Mike, 2026-11-24, nach der Frage „was heißt ‚in Listen steht jeder Punkt in einer
eigenen Zeile‘?“): Statt eines Hinweises für alle Texte sagt das Feld, was ein Zeilenumbruch bewirkt:
Liste (jede Zeile ein Punkt), Absätze (Leerzeile = neuer Absatz), Zeilen (Adresse, jede Zeile bleibt), einzeilig
(Umbruch wird Leerzeichen, kleines Feld); dazu „Bleibt das Feld leer, wird der Abschnitt nicht angezeigt“, wo das
stimmt. Die Art steht an einer Stelle, `PageTexts::kind()` (abweichend vom Vorschlag „Parameter im Template“, weil auch
`/admin/texte` sie braucht). `PageTextKindTest` rendert alle öffentlichen Seiten als Admin und vergleicht die Art mit
dem HTML (`ul`/`ol`, `div` mit Absätzen, `br`).

**Umstellung in Produktion:** Wer beim Deploy angemeldet ist, hat noch ein Cookie mit Pfad `/admin`. Danach kommt eines
mit Pfad `/` dazu, gleicher Name. Unter `/admin` schickt der Browser beide, das mit dem längeren Pfad zuerst, und PHP
nimmt das erste (lokal geprüft). Abhilfe: **vor dem Push in Produktion abmelden**; sonst die Cookies von sonjaczak.com
im Browser löschen. Sonst hilft Warten (das alte Cookie verfällt nach 12 Stunden).

## Auslegungen (auch in `projektstand.md` → „Später zu klären“)

- „Alle Seitentexte“ schließt Impressum, Datenschutzerklärung und Datenschutz-Information ein. Das sind Rechtstexte;
  ändert Sonja dort etwas, prüft niemand mehr gegen die Quellen.
- Ein geleerter Text blendet den Abschnitt aus, wo das Template das vorsieht (Regeln, FAQ, Hero-Satz); sonst bleibt
  eine leere Stelle.
- Benennt Mike einen Schlüssel im YAML um, bleibt ein alter Eintrag in `page_text` ohne Wirkung liegen.
- Die öffentliche Seite zeigt den Preis aus den Einstellungen, nicht den eines bestimmten Kurses.
- Lesen über einen Decorator (Option A) statt über eine eigene Twig-Funktion, wie zuerst im Chat skizziert.
- Ein Text gleich dem ursprünglichen wird nicht gespeichert (kein Marker). Ändert Mike später den Text im YAML, gilt
  der neue für alle nicht geänderten Texte.
- K4: Ein Text, der nur in einer Adresse steckt (Google-Maps-Link aus `lessons.location.address`), ändert sich mit
  der Adresse; Bildbeschreibung über den Stift am Bild. Seitentitel, Menü, Knöpfe bleiben im Repo (Frage 4).
- K4: Nach Speichern oder Zurücksetzen lädt die Seite neu statt den Text an Ort und Stelle zu ersetzen.
- K4: In der Datenschutz-Information als PDF stehen für Admins die `data-page-text`-Attribute im HTML (unsichtbar).
- Platzhalter: Unbekannte werden abgelehnt, fehlende sind erlaubt. Löscht Sonja `%price%`, steht kein Preis mehr da
  und eine Preisänderung in den Einstellungen wirkt nicht mehr auf diesen Text.
