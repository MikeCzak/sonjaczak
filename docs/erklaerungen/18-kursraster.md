# Kursraster: Stimulus, `fetch` und JSON-Endpunkte

Stand 2026-11-16 (`umsetzung-admin.md` C4). Geprüft gegen den installierten Code: `symfony/stimulus-bundle` 3.7.0,
`@hotwired/stimulus` 3.2.2 (`assets/vendor/@hotwired/stimulus/stimulus.index.js`), Symfony 8.1. Begründungen und
Einschätzungen sind als solche formuliert.

## 1. Konzepte

### Wer was macht

Das Raster ist eine normale Twig-Seite. JavaScript macht sie nur bequemer:

| Teil | Aufgabe | Datei |
|---|---|---|
| `CourseGrid` | Baut Wochen × Zeitfenster und ordnet jede Einheit einer Zelle zu | `src/Course/Grid/CourseGrid.php` |
| Twig | Zeichnet die Tabelle; jede Einheit ist ein Link auf ihre Bearbeitungsseite | `templates/admin/course/grid.html.twig`, `_grid_lesson.html.twig` |
| Stimulus-Controller `grid` | Öffnet den Editor in der Zelle, schickt Änderungen per `fetch` | `assets/controllers/grid_controller.js` |
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

### Die eine Regel für die Zahl der Einheiten

Jede Teilnahme hat immer `plannedLessons` reguläre Einheiten (alle außer Nachholstunden, abgesagte zählen mit):

| Aktion | Folge am Kursende |
|---|---|
| Einheit löschen | eine kommt dazu (`fillUpAtEnd()`) |
| Doppelstunde anlegen | die letzte zukünftige, nicht abgesagte fällt weg (`removeLastFutureLesson()`) |
| Absage aufheben löscht Überschneidendes | bei der betroffenen Teilnahme kommt eine dazu |
| Ferien eintragen | alle Einheiten im Zeitraum gehen, am Ende kommen neue dazu |
| Ferien löschen | die Einheiten an den freien Tagen kommen zurück, am Ende fallen sie weg (`restoreBreakDays()`) |

**Fallstrick `fillUp()`:** Es füllt Lücken ab Kursbeginn. Nach dem Löschen hätte es die Einheit in dieselbe Woche
zurückgelegt. `fillUpAtEnd()` beginnt erst nach der letzten regulären Einheit.

## 2. Zeile für Zeile (Auszüge)

**Zuordnung zu einer Spalte** (`CourseGrid::columnFor()`):

1. Überschneidet die Einheit das Zeitfenster ihrer eigenen Teilnahme → dort (auch eine leicht verschobene).
2. Sonst das Zeitfenster am selben Tag mit der größten Überschneidung (Doppelstunde von B im Zeitfenster von A).
3. Sonst die eigene Spalte (Nachholstunde an einem anderen Tag); die Zelle zeigt dann Wochentag und Uhrzeit.

Zeiten rechnet `GridColumn` in Minuten nach Mitternacht; Überschneidung ist dann
`min(Ende1, Ende2) − max(Beginn1, Beginn2) > 0`.

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
| Ziehen statt Knöpfe (Drag and Drop) | Natürlich am Laptop | Am Tablet fummelig; Knöpfe am Zellenrand reichen (Mike, Frage 8 Punkt 10) |
| Ferien-Rückfrage als eigene Seite | Ohne JavaScript | Ein Seitenwechsel mehr (Mike: Popup) |
| Bearbeiten ohne Neuladen (Zellen im Browser umsetzen) | Flüssiger | Logik von `CourseGrid` doppelt in JavaScript |

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
  Ändert sich der Controller, die Abläufe aus `rundgang-admin.md` Abschnitt 3, Punkte 5 und 6 erneut durchgehen.
