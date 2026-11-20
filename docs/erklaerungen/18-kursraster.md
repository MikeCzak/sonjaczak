# Kursraster: Stimulus, `fetch` und JSON-Endpunkte

Stand 2026-11-16 (`umsetzung-admin.md` C4), Zeitachse 2026-11-18 (C4g). Geprüft gegen den installierten Code: `symfony/stimulus-bundle` 3.7.0,
`@hotwired/stimulus` 3.2.2 (`assets/vendor/@hotwired/stimulus/stimulus.index.js`), Symfony 8.1. Begründungen und
Einschätzungen sind als solche formuliert.

## 1. Konzepte

### Wer was macht

Das Raster ist eine normale Twig-Seite. JavaScript macht sie nur bequemer:

| Teil | Aufgabe | Datei |
|---|---|---|
| `CourseGrid` | Baut Wochen und Tage; legt je Tag Boxen auf eine Zeitachse (Position, Spur, Überschneidung, Nummer) | `src/Course/Grid/CourseGrid.php`, `GridRow`, `GridItem`, `GridAxis` |
| Twig | Zeichnet die Tabelle; jede Einheit ist ein Link auf ihre Bearbeitungsseite | `templates/admin/course/grid.html.twig`, `_grid_track.html.twig`, `_grid_item.html.twig` |
| Stimulus-Controller `grid` | Öffnet den Editor schwebend unter der Box, schickt Änderungen per `fetch` | `assets/controllers/grid_controller.js` |
| Stimulus-Controller `break-confirm` | Fragt vor dem Speichern von Ferien nach | `assets/controllers/break_confirm_controller.js` |
| JSON-Endpunkte | Prüfen, ändern, zählen | `LessonController`, `CourseController` |

**Grundsatz: Der Server entscheidet und zählt, der Browser zeigt nur an.** Die Antworten enthalten fertige, übersetzte
Texte („14 von 15“, „1 Nachholstunde fällig“, der Text fürs Popup). Im JavaScript steht keine Geschäftsregel und kein
deutscher Text. Folge: Ändert sich die Nachholregel, ändert sich nur PHP.

**Ohne JavaScript** bleibt das Raster lesbar, und jede Einheit führt auf ihre Bearbeitungsseite (progressive
enhancement: erst eine Seite, die ohne Skript funktioniert, dann das Skript obendrauf). Bearbeiten im Raster und die
Ferien-Rückfrage brauchen JavaScript; der Server löscht aber ohne Bestätigung nichts (Abschnitt 4).

### Stimulus in fünf Begriffen

Stimulus verbindet HTML-Attribute mit Methoden einer JavaScript-Klasse. Es baut keine Seite, es reagiert auf vorhandenes
HTML.

| Begriff | HTML | im Controller |
|---|---|---|
| **Controller** | `data-controller="grid"` | Klasse in `assets/controllers/grid_controller.js`; eine Instanz je Element |
| **Action** | `data-action="grid#open:prevent"` | Methode `open(event)`; `:prevent` ruft `event.preventDefault()` auf (der Link öffnet dann nicht die Seite) |
| **Target** | `data-grid-target="editor"` | `this.editorTarget` |
| **Value** | `data-grid-token-value="…"` | `this.tokenValue`, typisiert über `static values = { token: String }` |
| **Action-Parameter** | `data-grid-url-param="/admin/…"` am auslösenden Element | `event.params.url` |

Bei `<form>` ist das Standard-Ereignis `submit`, deshalb reicht `data-action="break-confirm#check"` ohne `submit->`.

**Fallstrick Parameter:** Stimulus wandelt Parameter um (im Code: `get params()` ruft für jeden Wert eine
Typumwandlung auf). Aus `"123"` wird eine Zahl, aus `"true"` ein Boolean. Eine Notiz „123“ käme also als Zahl an. Die
Notiz steht deshalb in einem gewöhnlichen `data-note`-Attribut (`link.dataset.note`, immer ein String).

**Fallstrick Mutationen:** Stimulus bemerkt geänderte Attribute über einen `MutationObserver`, also erst nach dem
laufenden Code. Ein `data-action` kurz zu entfernen, damit ein zweites `submit` nicht noch einmal abgefangen wird, wirkt
deshalb zu spät. `break-confirm` nutzt stattdessen ein Flag (`this.passing`).

### Laden: nur auf der Seite, die den Controller braucht

`stimulus-bundle` liest `assets/controllers/` und registriert jede `*_controller.js` automatisch. Steht in der Datei
der Kommentar `/* stimulusFetch: 'lazy' */`, wird sie erst geladen, wenn ein passendes `data-controller` im HTML
auftaucht (`vendor/symfony/stimulus-bundle/src/AssetMapper/ControllersMapGenerator.php` sucht genau diesen Kommentar).
Beide Controller sind so markiert: Die anderen Admin-Seiten laden nur Stimulus selbst, die öffentlichen Seiten gar nichts
(eigener Entrypoint `admin`, Frage 2).

### `fetch`, HTTP-Methoden und Statuscodes

`fetch()` schickt eine Anfrage aus JavaScript, ohne die Seite zu verlassen. Cookies der eigenen Seite gehen dabei mit
(Standard `credentials: 'same-origin'`), also auch das Session-Cookie.

| Anfrage | Bedeutung | Adresse |
|---|---|---|
| `PATCH` | Teile ändern (nur die mitgeschickten Felder) | `/admin/einheiten/{id}` |
| `DELETE` | Löschen | `/admin/einheiten/{id}` |
| `POST` | Etwas Neues anlegen | `/admin/einheiten/{id}/doppelstunde` |
| `GET` | Nur lesen, nichts ändern | `/admin/kurse/{id}/ferien/pruefen`, `…/vorschlag-pruefen` |

Dieselbe Adresse wie die Bearbeitungsseite (GET/POST), weil die Zelle die Einheit **ist** (Mikes Frage). Symfony wählt
die Route nach Methode, jede Aktion bleibt eine eigene Controller-Methode.

| Status | Bedeutung hier |
|---|---|
| 200 | Gespeichert; JSON mit neuem Zustand und Zählungen |
| 400 | Kein gültiges JSON |
| 403 | CSRF-Token falsch (z. B. Seite zu lange offen, Session abgelaufen) |
| 409 | **Konflikt**: Absage aufheben würde etwas löschen (mit Text fürs Popup) oder die Doppelstunde hat keinen Platz |
| 422 | Inhalt ungültig (unbekannte Absage, Validator der Entity) |

**Fallstrick Weiterleitung:** Ist die Session abgelaufen, leitet die Firewall auf `/admin/login` um. `fetch` folgt
Weiterleitungen von selbst und bekommt dann die Login-Seite als HTML mit Status 200. Der Controller prüft deshalb den
`Content-Type`: Nur JSON gilt als Antwort, alles andere als Fehler („Bitte die Seite neu laden“).

### Rückfrage über 409

„Absage aufheben“ läuft in zwei Runden, ohne dass der Browser vorher wissen muss, was im Zeitfenster liegt:

1. `PATCH {"cancelledBy": null}` → Server findet die Doppelstunde der Nachbarin, speichert **nichts** und antwortet 409
   mit `{"confirm": "Im Zeitfenster … wird gelöscht: Berta Nachbar, Fr 16.10. 15:30, geplant (Vorsingen) …"}`.
2. Sonja klickt „OK“ → dieselbe Anfrage mit `"deleteOverlapping": true` → Server löscht und füllt bei Berta am Ende auf.

Die Regel steht nur am Server; der Browser zeigt nur den Text. Bei den Ferien geht es umgekehrt (erst `GET …/pruefen`,
dann das Formular mit `confirmed=1`), weil dort ein normales Formular abgeschickt wird.

### Neu laden statt nachbauen

Nach einer Absage wandert die Einheit in die rote Zeile, die es vielleicht noch gar nicht gibt; nach Löschen oder
Doppelstunde ändern sich Einheiten am Kursende. All das im Browser nachzubauen, hieße die Logik von `CourseGrid` in
JavaScript zu wiederholen. Deshalb lädt die Seite nach solchen Änderungen neu (`window.location.reload()`; die Scrollposition
stellen Chrome und Safari dabei in der Regel wieder her, nicht geprüft für alle Browser). Nur eine geänderte Notiz wird direkt eingetragen.

### Ziehen: die Zeile vom Server statt Neuladen (C4h)

Eine geplante Einheit lässt sich innerhalb ihres Tages ziehen. Weil sie den Tag nicht verlässt und in der Achse
bleibt, ändert sich nur diese eine Zeile (Spuren, rote Rahmen, Platzhalter „frei“ mit Doppelstunden-Angeboten);
Zählung „7/15“, „Nachholstunde fällig“ und die Zeile der Abgesagten bleiben gleich. Der Server antwortet deshalb mit
der neu gezeichneten Zeile (`renderView()` von `_grid_track.html.twig`, gebaut mit `CourseGrid` wie die Seite), und
das JavaScript tauscht nur das `div.grid-track` aus. So gibt es weiter nur eine Darstellung (Twig) und nur eine
Logik (`CourseGrid`).

- **Achse:** War die gezogene Einheit die früheste oder späteste des Kurses, kann sich die Achse ändern; dann passte
  die neue Zeile nicht zu den anderen. Jede Zeile trägt `data-axis="900-1080"` (Minuten ab Mitternacht); weicht der
  Wert ab, lädt die Seite doch neu.
- **Aktionen im neuen HTML:** Stimulus beobachtet das Element des Controllers mit einem `MutationObserver`
  (`childList` und `subtree`) und verbindet `data-action` in eingefügtem HTML selbst (nachgesehen im installierten
  Code `assets/vendor/@hotwired/stimulus/stimulus.index.js`, Version 3.2.2 laut `importmap.php`; im Browser geprüft:
  Klick auf die neue Zeile öffnet den Editor).
- **Pointer Events statt HTML Drag and Drop:** `pointerdown` auf der Box, `pointermove` und `pointerup` auf `window`.
  Erst ab 5 px Bewegung wird gezogen, sonst bleibt es ein Klick. Nach dem Ziehen schickt der Browser trotzdem einen
  `click`; ein einmaliger Listener in der Capture-Phase auf `window` schluckt ihn, sonst öffnete sich der Editor oder
  der Namenslink. Links sind von sich aus ziehbar (`dragstart`); das wird verhindert.
- **Kein Ziehen mit dem Finger** (`pointerType === 'touch'`): Es würde mit dem waagrechten Scrollen kollidieren.
- **Einrasten:** Pixel durch Pixel pro Minute, auf 5 Minuten gerundet. Die Achse beginnt immer zu einer vollen
  Stunde, also sind 5 Minuten ab Achsenbeginn auch 5 Minuten der Uhrzeit.
- **Kollision zweimal:** Der Browser prüft für die rote Vorschau mit den Positionen der Boxen in der Zeile, der
  Server prüft maßgeblich (`LessonRepository::findOverlapping`, `TrialLessonRepository::findOverlapping`) und
  antwortet mit 409. Anders als beim Aufheben einer Absage wird nie etwas gelöscht.

### Die eine Regel für die Zahl der Einheiten

Jede Teilnahme hat immer `plannedLessons` reguläre Einheiten (alle außer Nachholstunden, abgesagte zählen mit). Die
Zahl gehört seit C7 der Teilnahme (Kopie aus dem Kurs, wie der Preis):

| Aktion | Folge am Kursende |
|---|---|
| Einheit löschen | eine kommt dazu (`fillUpAtEnd()`) |
| Doppelstunde anlegen | die letzte zukünftige, nicht abgesagte fällt weg (`removeLastFutureLesson()`) |
| Absage aufheben löscht Überschneidendes | bei der betroffenen Teilnahme kommt eine dazu |
| Ferien eintragen | alle Einheiten im Zeitraum gehen, am Ende kommen neue dazu |
| Ferien löschen | die Einheiten an den freien Tagen kommen zurück, am Ende fallen sie weg (`restoreBreakDays()`) |
| Anzahl senken, „Teilnahme beenden“ (C7) | die letzten zukünftigen gehen, auch abgesagte (`changePlannedLessons()`) |
| Anzahl erhöhen (C7) | am Ende kommen welche dazu, höchstens bis Kursende |

**Zwei Zählungen:** Der Planer zählt reguläre Einheiten (eine von Sonja abgesagte behält ihren Platz), die
Nachholregel zählt alle außer Sonjas Absagen (`Lesson::counts()`). Beide vergleichen mit derselben Zahl. Deshalb hebt
„Anzahl −1“ eine fällige Nachholstunde nicht auf: Der Planer löscht dafür eine zukünftige Einheit, die Nachholregel
zählt danach eine weniger (`umsetzung-admin.md` C7, „Korrektur“).

**Fallstrick `fillUp()`:** Es füllt Lücken ab Kursbeginn. Nach dem Löschen hätte es die Einheit in dieselbe Woche
zurückgelegt. `fillUpAtEnd()` beginnt erst nach der letzten regulären Einheit.

## 2. Zeile für Zeile (Auszüge)

**Zeitachse** (seit C4g, 2026-11-18, `umsetzung-admin.md` C4g). Bis dahin hatte das Raster eine Spalte je
Zeitfenster, und `columnFor()` entschied, in welche Spalte eine Einheit kommt. Jetzt steht jede Box an ihrer Uhrzeit:

- **Achse** (`GridAxis::around()`): von der frühesten Beginnzeit bis zum spätesten Ende aller Boxen im Kurs, auf volle
  Stunden gerundet. Sie ist für alle Zeilen gleich, sonst stünde 15:00 nicht untereinander.
- **Position:** Twig schreibt Minuten ab Achsenbeginn als CSS-Variablen in die Box, `style="--start: 40; --length: 50;
  --lane: 0"`. Die SCSS rechnet daraus mit `calc()` und `--grid-minute: 3px` die Lage aus (`left`, `width`, `top`).
  Eine Content-Security-Policy, die Inline-Styles verbieten würde, gibt es nicht (geprüft 2026-11-18). Käme eine,
  bräuchte es `style-src 'unsafe-inline'` oder Klassen statt Werten.
- **Spuren** (`GridRow::stack()`): Die Boxen eines Tages sind nach Beginn sortiert. Jede kommt auf die erste Spur, deren
  letzte Box schon geendet hat. Überschneiden sich zwei Boxen, liegen sie untereinander, und die Zeile wird höher
  (`--lanes`). Endet die eine um 15:50 und beginnt die andere um 15:50, gilt das nicht als Überschneidung.
- **Überschneidung:** Stattfindende Termine (Einheit ohne Absage, Schnupperstunde) am selben Tag, die sich zeitlich
  überschneiden, kennen einander (`GridItem::$overlaps`). Sie bekommen einen roten Rahmen und den Hinweis im Tooltip.
- **Frei** (`CourseGrid::addFreeSlots()`): An einem Unterrichtstag ohne Ferien bekommt das Zeitfenster einer Teilnahme
  eine gestrichelte Box, wenn dort nichts stattfindet. Das gilt nur zwischen der ersten und letzten regulären Einheit
  der Teilnahme. Die Box bietet den Nachbarn die Doppelstunde an, deren zweite Einheit in das Fenster fiele
  (`GridRow::offerDoubles()`).
- **Zählung „7/15“:** die wievielte zählende Einheit (`Lesson::counts()`) der Teilnahme in zeitlicher Reihenfolge.
  „Nachholstunde fällig“ steht bei der nächsten stattfindenden Einheit der Teilnahme (nach der letzten bei der letzten).
- **Zwei Links in einer Box:** HTML erlaubt kein `<a>` in einem `<a>`. Der Link zur Einheit ist leer und deckt mit
  Bootstrap `.stretched-link` (ein `::after` über dem nächsten positionierten Vorfahren, `z-index: 1`) die ganze Box ab.
  Der Name liegt mit `position: relative; z-index: 2` darüber und führt zur Teilnahme. Für Screenreader trägt der leere
  Link ein `aria-label`.
- **Editor:** Er wird in die Achse des Tages eingefügt (`position: absolute`) und unter die Box gesetzt. In den letzten
  Zeilen kommt er über die Box, damit er nicht unten aus der Tabelle ragt. Schließen mit Abbrechen, Escape
  (`keydown.esc@window`, Tastenfilter von Stimulus 3.2) oder Klick daneben (`click@window`).

**Zeilen** (seit C4f, `CourseGrid::rows()`, `GridWeek`): je Tag eine Zeile, an jedem Wochentag mit Zeitfenster und an
jedem Tag mit Einheit, vom Kursbeginn (bzw. der frühesten Einheit) bis zum Ende (bzw. der letzten Einheit). Die
KW-Zelle hat `rowspan` über Tage und rote Zeilen der Woche. Sie ist gelb und nennt die Ferien, wenn alle Tage der
Woche Ferientage sind (`GridWeek::isWholeBreak()`); sonst steht die Ferienbezeichnung beim Tag. Weil die KW-Zelle nur
in der ersten Zeile der Woche steht, färbt das CSS nicht mehr mit `.grid-row--break > *`, sondern Tag und Zellen
einzeln. Doppelstunden werden nur in Zeitfenstern des eigenen Wochentags angeboten.

Zeiten rechnet `GridItem` in Minuten nach Mitternacht; zwei Zeiträume überschneiden sich, wenn
`Beginn1 < Ende2` und `Beginn2 < Ende1` (`GridItem::overlapsTime()`).

**Überschneidungen in der Datenbank** (`LessonRepository::findOverlapping()`): Zwei Zeiträume überschneiden sich, wenn
der eine beginnt, bevor der andere endet, und endet, nachdem der andere beginnt. Das Ende ist Beginn + Dauer; DQL kann
zu einem Datum keine Minuten aus einer Spalte addieren. Die Abfrage holt deshalb die Einheiten des Tages, die vor dem Ende
beginnen, PHP prüft den Rest.

**Token** (`grid.html.twig`): `data-grid-token-value="{{ csrf_token('lesson-grid-' ~ course.id) }}"` — eine Token-ID je
Kurs, nicht in `stateless_token_ids`, also ein Session-Token (`erklaerungen/17-csrf.md`). Der Controller schickt es im
Header `X-CSRF-Token`, `LessonController::isTokenValid()` prüft es mit `isCsrfTokenValid()`.

**Entities in einer Liste eindeutig machen:** `array_unique()` vergleicht Objekte Eigenschaft für Eigenschaft, bei
Doctrine-Entities mit Collections bis zur Rekursion. Stattdessen Schlüssel `spl_object_id($entity)`.

## 3. Alternativen

| Variante | Pro | Contra |
|---|---|---|
| **Stimulus + `fetch` (gewählt, 2026-11-14)** | Durchschaubar; eine Anfrage je Änderung; volle Kontrolle | Eigenes JavaScript, eigene Prüfung im Browser |
| Turbo Frames | Kaum JavaScript, Symfony-Forms bleiben | Je Zelle zwei Anfragen; Zählungen außerhalb des Frames brauchen Turbo Streams |
| Live Components | Raster wird nach jeder Änderung neu gerendert | Ganzer Zustand hin und zurück; eigene Konzepte, schwerer zu debuggen |
| Ziehen statt Knöpfe (Drag and Drop) | Natürlich am Laptop | Am Tablet fummelig; Knöpfe am Zellenrand reichen (Mike, 2026-11-14). Seit C4h zusätzlich zum Editor: Ziehen innerhalb des Tages, mit Maus oder Touchpad |
| HTML Drag and Drop (`draggable`) für C4h | Eingebaut | Geisterbild statt eingerasteter Vorschau; Touch nicht nachgelesen |
| Turbo Streams für die neue Zeile (C4h) | Fertiges Protokoll | Neue Abhängigkeit, übernimmt ohne Abschalten die Navigation im ganzen Admin |
| Ferien-Rückfrage als eigene Seite | Ohne JavaScript | Ein Seitenwechsel mehr (Mike: Popup) |
| Bearbeiten ohne Neuladen (Zellen im Browser umsetzen) | Flüssiger | Logik von `CourseGrid` doppelt in JavaScript |
| Spalten je Zeitfenster (bis C4g) | Einfaches HTML, kein Scrollen | Unterschiedliche Uhrzeiten an verschiedenen Tagen stehen ungenau untereinander; Regeln, welche Einheit in welche Spalte kommt |
| Zeitachse mit CSS-Grid (eine Spalte je Minute) | Das Grid könnte die Spuren selbst setzen | Verhalten bei Überlappung hätte ich erst gegen die Spezifikation prüfen müssen; schwer zu testen |
| Zeitachse mit Flexbox | Einfach | Abstände bräuchten Leer-Elemente; Überschneidungen gehen nicht |
| Bootstrap-Popover für den Editor | Fertig | Reinigt HTML nach einer Liste erlaubter Elemente (für Buttons und Textfeld nicht nachgelesen) |

## 4. Sicherheit und Fallstricke

- **Nichts löschen ohne Bestätigung, auch ohne JavaScript:** Ferien speichert der Server nur mit `confirmed=1`, wenn im
  Zeitraum Einheiten liegen; sonst speichert er nichts und nennt sie in einer Meldung. Absage aufheben löscht nur mit
  `deleteOverlapping`. Das Popup allein wäre kein Schutz, denn eine Anfrage kann auch ohne Popup kommen.
- **GET ändert nichts:** Die Prüf-Endpunkte rechnen nur. `vorschlag-pruefen` fügt die Vorschläge zwar dem Kurs-Objekt
  hinzu, ohne `flush()` erreicht davon aber nichts die Datenbank.
- **Rechte:** Alle Endpunkte liegen unter `#[IsGranted(User::ROLE_ADMIN)]`; `AdminRoutesRequireLoginTest` prüft jede
  Route unter `/admin` auch mit PATCH und DELETE.
- **Ausgabe im Popup:** `confirm()` zeigt reinen Text, kein HTML; Namen und Notizen können dort nichts ausführen. Im
  Raster gibt Twig sie escaped aus.
- **Testen:** PHPUnit prüft Endpunkte und `CourseGrid`. Das JavaScript wurde mit Headless Chrome über das
  DevTools-Protokoll durchgeklickt (Skript nur lokal, nicht im Repo; Entscheidung Frage 4 gegen Symfony Panther).
  Ändert sich der Controller, die Abläufe aus `rundgang-admin.md` Abschnitt 3, Punkte 5 bis 7 erneut durchgehen.
