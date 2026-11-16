    # Phase 2: Umsetzungsplan Admin-Bereich (Entwurf)

Stand: 2026-11-13, **freigegeben**. Grundlage: `datenmodell.md` und `rollen.md` (beide freigegeben 2026-11-13).
Fortschritt und offene Punkte stehen im Projektstand (`docs/projektstand.md`), nicht hier.

## Spielregeln

- **Kleine Schritte.** Jeder Schritt ist ein eigener Commit (oder wenige), läuft durch die CI und geht live. Bei
  Datenbankänderungen genau eine Migration je Schritt, erzeugt mit `doctrine:migrations:diff` und vor dem Commit
  gemeinsam gelesen.
- **Vor jedem Schritt** nenne ich kurz Dateien, neue Pakete mit Version (aus Packagist/`composer.lock`) und offene
  Entscheidungen. Bei den Lernschwerpunkten (Doctrine, Security) erkläre ich ausführlicher, mit Fallstricken.
- **Tests in jedem Schritt:** Zugriff (anonym → Login, falsche Rolle → 403, Admin → 200), Fachregeln über
  Foundry-Factories, Formulare. Dazu ein Test, der **alle** Routen unter `/admin` aus dem Router liest und prüft, dass
  keine ohne Login erreichbar ist. Damit fällt eine vergessene Absicherung sofort auf.
- **Am Server** (Konten anlegen, Cronjobs, Backups) frage ich vorher.
- **Echte Daten erst nach Meilenstein F** (Backups und Datenschutz-Information).

## A. Grundlagen

**A1 Zeitzone und Werkzeuge** – erledigt 2026-11-13

- `date.timezone = Europe/Vienna` in `frankenphp/conf.d/10-app.ini`, Test dazu.
- `zenstruck/foundry` und `symfony/maker-bundle` als `require-dev` (Maker für `make:user`, `make:entity`,
  `make:migration`; erzeugter Code wird gelesen und angepasst, nicht blind übernommen). `doctrine/doctrine-fixtures-bundle`
  war geplant, ist aber überflüssig: Foundry lädt Fixtures selbst (`foundry:load-fixtures`, Stories mit `#[AsFixture]`)
  und setzt die Datenbank dabei über die Migrationen neu auf.
- PHPStan mit `objectManagerLoader`, damit es Entities und DQL prüft (Projektstand: „sobald es Entities gibt“).

## B. Login und Konten

**B1 User und Login mit Passwort** (`rollen.md` 1–4) – erledigt 2026-11-13, Konten am Server noch offen

- `symfony/security-bundle`, Entity `User` (`make:user`), erste Migration.
- Firewall nur für `^/admin`, `access_control`, Rollenhierarchie, `form_login` mit CSRF, `login_throttling`, Logout.
- Admin-Layout (eigenes Basis-Template, Navigation, `noindex`), Startseite `/admin` als Platzhalter, Domain `admin`
  für Übersetzungen.
- Konsolenbefehl `app:user:create` (Passwort verdeckt).
- Datenschutzerklärung: Absatz zum Session-Cookie im Verwaltungsbereich (`rollen.md` 10, Rechtsgrundlage vorher prüfen).
- Tests: öffentliche Seiten weiterhin ohne `Set-Cookie`, Login-Seite ohne Absenden ohne Cookie, Zugriffsmatrix,
  Routen-Test (siehe Spielregeln).
- Danach am Server (nach Rückfrage): Konten für Mike und Sonja anlegen.

**B2 Sessions in PostgreSQL** (`rollen.md` 6) – erledigt 2026-11-13 (zusammen mit B1 committet)

- `PdoSessionHandler` mit der Doctrine-Verbindung, Migration für die Tabelle `sessions`.
- `cookie_path: /admin`, `gc_maxlifetime: 28800`, `cookie_lifetime: 43200`.
- Prüfen nach dem Deploy: angemeldet bleiben über einen Deploy hinweg.

**B3 Passwort vergessen** (`rollen.md` 4.2) – erledigt 2026-11-13

- `symfonycasts/reset-password-bundle`, Routen unter `/admin`, Mail-Template (Text), immer gleiche Antwort.
- „Mein Konto“: Passwort ändern.

**B4 Benutzerverwaltung** (nur `ROLE_SUPER_ADMIN`) – erledigt 2026-11-13

- Liste, anlegen (mit Einladungslink über den Mechanismus aus B3 statt Passwort im Formular), Rolle ändern,
  deaktivieren. Ein Konto darf sich nicht selbst die letzte Super-Admin-Rolle nehmen (Voter).

**B5 Passkeys** (`rollen.md` 5, 10)

- `web-auth/webauthn-symfony-bundle`, Tabelle für Passkeys, Login-Knopf, „Mein Konto“: Passkey hinzufügen,
  Liste mit Gerät und letzter Nutzung, löschen.
- Vor dem Einbau: Konfiguration und Datenmodell im Code des Bundles prüfen (die Doku ist lückenhaft).
- Am Ende: Sonja richtet Passkeys auf Mac und Samsung-Handy ein (mit Mike).

## C. Unterricht

**C1 Schüler:innen und Elternteile** – erledigt 2026-11-13

- Embeddable `Address`, Entities `Guardian` und `Student` (mit Beziehung zu `User`, vorerst ungenutzt).
- CRUD mit eigenen Controllern und Form-Types (`AddressType` wiederverwendbar), Liste mit Suche.
- `StudentVoter::DELETE` (keine Honorarnoten; greift ab D1, bis dahin immer erlaubt).
- Foundry-Factories und `AppFixtures` mit erfundenen Daten.

**C2 Kurse und Ferien** – erledigt 2026-11-13

- Entities `Course` (Enum `Term`) und `CourseBreak` (Enum `BreakSource`), CRUD.
- C2b: Ferien-Vorschlag aus der OpenHolidays API (Steiermark `AT-SM`, Schulferien und Feiertage), mit Test gegen
  eine aufgezeichnete Antwort (`MockHttpClient`), damit die CI nicht vom fremden Dienst abhängt.

**C3 Teilnahmen und Einheiten** – erledigt 2026-11-13

- Entities `Enrollment` (Enum `Billing`) und `Lesson` (Enum `LessonStatus`, Selbstreferenz `makeUpFor`).
- Service, der die Einheiten erzeugt (bis `plannedLessons`, ohne Ferien), Neuberechnung bei neuen Ferien.
- Nachholregel als Service mit Unit-Tests. Schwellwert vorerst als Parameter, ab C6 aus den Einstellungen.
- `EnrollmentVoter::DELETE`.

**C4 Kursraster** (`datenmodell.md` 4) – entschieden 2026-11-14: Stimulus + `fetch`

- **Entscheidung vorher:** Symfony UX (Stimulus + `fetch`, Turbo Frames oder Live Components). Frage und Antwort im
  Projektstand („Jetzt“).

  *Optionen (vorgestellt 2026-11-13).* Alle drei laufen mit AssetMapper ohne Node, bauen auf
  `symfony/stimulus-bundle` 3.7.0 auf (PHP ≥ 8.4, Packagist) und brauchen am Server keinen zusätzlichen Prozess.

  | | Stimulus + `fetch` | Turbo Frames (`symfony/ux-turbo` 3.7.0) | Live Components (`symfony/ux-live-component` 3.7.2) |
  |---|---|---|---|
  | Pro | Volle Kontrolle über Tastatur und Zellen; eine Anfrage pro Änderung; durchschaubar (kleines eigenes JavaScript plus JSON-Endpunkt) | Fast kein eigenes JavaScript; Symfony-Forms und Validierung bleiben; Tests mit WebTestCase | Kaum JavaScript; das Raster wird nach jeder Änderung neu gerendert, Zählungen stimmen automatisch; später passend für die Preisschätzung im Booking-Formular |
  | Contra | Mehr eigenes JavaScript; Zählungen und Fehler im Browser nachführen; JavaScript braucht eigene Tests | Je Zelle zwei Anfragen (Formular laden, absenden); Zählungen außerhalb des Frames brauchen Turbo Streams; Turbo Drive gilt sonst seitenweit und müsste auf `/admin` beschränkt werden | Eigene Konzepte (LiveProp, writable, Hydration), schwerer zu debuggen; jede Änderung schickt den Zustand des ganzen Rasters hin und zurück; zwei zusätzliche Pakete |

  **Empfehlung Claude:** Stimulus + `fetch` für das Raster (tastaturlastig, viele kleine Änderungen). Live Components
  erst beim Booking-Formular erwägen. Bei Unsicherheit: eine Rasterzeile in zwei Varianten zum Vergleichen.
- Raster nach Kalenderwochen, Bearbeiten direkt in der Zelle, Tastatur, Ferien per Zeile, Zählung je Spalte.
- Design mit Sonjas Rückmeldung nachschärfen, wenn Mike es ihr in Produktion zeigt.

**C4 Detailplan** (Entwurf 2026-11-15; alle Fragen entschieden 2026-11-16. Gerät: MacBook Air; in der Mobilansicht
(Breakpoints für Tablet und Handy) größere Knöpfe)

Stand heute: Kein Stimulus installiert, das JavaScript der öffentlichen Seiten (`assets/inquiry.js`, `slider.js`) ist
reines JavaScript in `app.js`. Einheiten werden einzeln auf `/admin/einheiten/{id}` bearbeitet (Status, Notiz, Beginn,
Dauer). CSRF ist für Formulare zustandslos (`config/packages/csrf.yaml`, Token-ID `submit`); im Admin gibt es eine Session.

Schritte (je ein Commit):

1. **C4a Stimulus installieren** (erledigt 2026-11-16, CSRF-Controller des Rezepts entfernt)**:** `composer require symfony/stimulus-bundle` (3.7.0, PHP ≥ 8.4, `composer show -a`
   2026-11-15). Was das Flex-Rezept anlegt (vermutlich `assets/stimulus_bootstrap.js`, `assets/controllers.json`,
   Einträge in `importmap.php`), prüfe ich beim Installieren am Diff und erkläre es. Laden über einen eigenen
   Entrypoint `admin` (Frage 2).
2. **C4b Raster nur lesend** (erledigt 2026-11-16; Schnupperstunden als Zeile über der Tabelle statt als Tabellenzeile)**:** Service `CourseGrid` baut aus einem Kurs die Zeilen (Kalenderwochen von Kursbeginn bis
   Kursende, Ferienwochen markiert, Schnupperstunden in eigener Zeile oben) und Spalten (Teilnahmen nach Wochentag und
   Uhrzeit). Zelle = Einheiten dieser Teilnahme in dieser Woche (meist eine; eine Nachholstunde kommt dazu, mit
   Verweis auf die ausgefallene Einheit). Kopfzeile: „11 von 15“ und Hinweis aus `MakeUpRule`. Jede Zelle ist ein
   Link auf `/admin/einheiten/{id}`, das Raster funktioniert also auch ohne JavaScript. Eine Abfrage mit Fetch Join
   (Teilnahmen, Schüler:innen, Einheiten). Unit-Test für `CourseGrid`, WebTestCase für die Seite.
0. **C4-0 Status umbauen** (Frage 5) und **Ferien mit Bestätigung** (Frage 6, 7) (erledigt 2026-11-16): Neue Ferien
   löschen alle Einheiten im Zeitraum (auch gehaltene und abgesagte), aufgefüllt wird am Ende (`fillUpAtEnd()`). Vorher
   fragt der Stimulus-Controller `break-confirm` den Server (`GET …/ferien/pruefen`, `…/ferien/vorschlag-pruefen`) und
   zeigt die Liste in `confirm()`; gelöscht wird nur mit `confirmed=1`, sonst speichert der Server nichts und nennt die
   Einheiten (Schutz ohne JavaScript). Ferien löschen legt die Einheiten an den frei gewordenen Tagen wieder an und
   kürzt am Ende (`restoreBreakDays()`).
3. **C4c Endpunkt zum Speichern** (erledigt 2026-11-16)**:** `PATCH /admin/einheiten/{id}` mit JSON `{cancelledBy, note}`;
   nur mitgeschickte Schlüssel ändern sich (geändert 2026-11-16 auf Mikes Frage: Die Zelle **ist** die Einheit, also
   dieselbe Adresse wie die Bearbeitungsseite; `PATCH` heißt „Teile ändern“, und eine eigene Route je Methode hält die
   HTML-Seite (GET/POST) und den JSON-Endpunkt getrennt). Antwort JSON mit Zustand, Beschriftung und den Zählungen der
   betroffenen Teilnahmen, schon übersetzt, damit der Browser nichts selbst rechnet. Fehler: 403 (Token), 400 (kein
   JSON), 422 (unbekannte Absage, Validator). CSRF: Session-Token **je Kurs** (`csrf_token('lesson-grid-<Kurs-ID>')` im
   Raster, Header `X-CSRF-Token`, `isCsrfTokenValid()`, `erklaerungen/17-csrf.md`). **„Absage aufheben“** (Frage 8,
   Punkte 4 und 5) gleich hier: Überschneidet sich etwas mit der wieder aufgenommenen Einheit, antwortet der Server mit
   409 und einem Text für `confirm()`; erst mit `deleteOverlapping: true` wird gelöscht und für die betroffenen
   Teilnahmen am Ende aufgefüllt. Verschieben, Dauer und Nachholstunden bleiben auf den bestehenden Seiten.
   WebTestCases: speichern, nur gesendete Schlüssel, ungültige Absage, Token eines anderen Kurses, Absage aufheben mit
   Bestätigung; ohne Login über `AdminRoutesRequireLoginTest`.
4. **C4d Stimulus-Controller `grid`** (erledigt 2026-11-16)**:** Klick auf eine Einheit öffnet in der Zelle die Knöpfe
   „abgesagt (Schüler:in)“ / „abgesagt (ich)“ (Schalter), Notiz, Speichern, Abbrechen, „Beginn, Dauer …“ (bestehende
   Seite), Löschen (Popup, danach am Kursende anhängen); in der roten Zeile zusätzlich „Absage aufheben“ (Popup, falls
   etwas überschneidet). Leere Zellen bieten „← + Doppelstunde Laura“ / „+ Doppelstunde Petra →“ für die Nachbarn am
   selben Tag (`POST /admin/einheiten/{id}/doppelstunde`, Dauer der Teilnahme, letzte zukünftige Einheit fällt weg).
   Keine Tastaturkürzel (Frage 3). Nur eine geänderte Notiz wird ohne Neuladen eingetragen, alles andere lädt die Seite
   neu (der Server zählt, der Browser rechnet nichts). Während einer Anfrage sind die Knöpfe gesperrt; Fehler stehen im
   Editor. Controller wird nur auf der Rasterseite geladen (`stimulusFetch: 'lazy'`). Neu: `LessonPlanner::fillUpAtEnd()`,
   weil `fillUp()` eine gelöschte Einheit in dieselbe Woche zurücklegen würde. Geprüft mit Headless Chrome über das
   DevTools-Protokoll (Skript im Scratchpad, nicht im Repo).
5. **C4e Ferien per Zeile** (erledigt 2026-11-16, geändert: Link statt eigenem Formular)**:** Link „Ferien …“ in jeder
   Wochenzeile öffnet das Ferienformular auf der Kursseite, vorbelegt mit Montag bis Sonntag (`?ferien_von=…&ferien_bis=…`);
   Sonja gibt die Bezeichnung ein oder kürzt auf einen Tag, nach dem Speichern geht es zurück ins Raster
   (`zurueck=raster`). Grund: Die Bezeichnung braucht eine Eingabe, und so gibt es nur ein Formular mit Bestätigung.
6. **C4f Doku** (erledigt 2026-11-16; Tastatur entfällt)**:** `erklaerungen/18-kursraster.md` (Stimulus, `fetch`, JSON-Endpunkt, CSRF ohne Formular, Tastatur),
   `rundgang-admin.md`.

**Zu entscheiden:**

| # | Frage | Empfehlung | Alternative |
|---|---|---|---|
| 1 ✓ | Wo steht das Raster? | **Eigene Seite** `/admin/kurse/{id}/raster`, verlinkt von Kursseite, Kursliste und Dashboard. Die Kursseite ist mit Formular, Ferien und Teilnahmen schon voll; das Raster braucht die ganze Breite. | Auf der Kursseite unter dem Formular: ein Klick weniger, aber lange Seite und schmale Spalten |
| 2 ✓ | Wo wird Stimulus geladen? | **Eigener Entrypoint `admin`** nur im Admin-Layout. Die öffentlichen Seiten laden nichts Neues. | In `app.js` für alle Seiten: einfacher, aber zusätzliches JavaScript für alle Besucher:innen |
| 3 ✓ | Bedienung einer Zelle (Mike 2026-11-16) | **Nur Knöpfe, keine Tastaturkürzel.** In der offenen Zelle: „Abgesagt (Schüler:in)“, „Abgesagt (ich)“ (als Schalter, zweiter Klick nimmt zurück), Notiz, Speichern. | – |
| 4 ✓ | Wie wird das JavaScript getestet? | **Endpunkt mit PHPUnit, Raster ohne JS ebenfalls; das JavaScript selbst prüfe ich im Browser** (Headless Chrome, Screenshots) und beschreibe die Prüfung in `rundgang-admin.md`. Das eigene JavaScript bleibt klein (ein Controller). | Symfony Panther (echter Browser in den Tests): automatisiert, aber Chrome im CI-Image, langsamere Tests, mehr Pflege |
| 5 ✓ | Status der Einheit (Mike 2026-11-16) | **Kein Status mehr für „geplant“ und „gehalten“:** Dass die Einheit existiert, heißt „geplant“. Gespeichert wird nur, wer abgesagt hat: `cancelledBy` als **nullable** Enum (`student`, `teacher`), `NULL` = nicht abgesagt. Angezeigt wird „gehalten“, wenn nicht abgesagt und das Ende (Beginn + Dauer) vorbei ist, sonst „geplant“. Migration: `held`/`planned` → `NULL`, die Absagen werden übernommen. Fallstrick SQL: `NULL` vergleicht man mit `IS NULL`, nie mit `= NULL`. `datenmodell.md` 3.5 wird angepasst. | Nicht-nullbares Enum mit eigenem Wert `none`: vermeidet `NULL`, ist aber ein Wert für „nichts“ |
| 6 ✓ | Ferien nachträglich eintragen (Mike 2026-11-16) | Alle Einheiten im Zeitraum werden gelöscht, am Ende kommen neue dazu. Gibt es im Zeitraum Einheiten, fragt ein **Popup** (`confirm()`) mit der Liste nach; die Liste holt der Stimulus-Controller vorher vom Server. Gilt für Kursseite, Raster und „Ferien vorschlagen lassen“. Folge: Ferien eintragen braucht dann JavaScript. **Einzelne Tage** gehen schon heute (Feld „Letzter Tag“ leer). | Bestätigungsseite: ohne JavaScript, aber ein Seitenwechsel mehr |
| 7 ✓ | Ferien **löschen** (Mike 2026-11-16: ok) | Beim Löschen von Ferien wird die Einheit am frei gewordenen Tag wieder angelegt, dafür fällt die letzte noch nicht stattgefundene am Ende weg. | So lassen und von Hand korrigieren |
| 8 ✓ | Absage-Zeile und Doppelstunden (Mikes Vorschlag 2026-11-16) | Siehe „Frage 8: Durchsicht“ unter der Tabelle, Antworten dort je Punkt. | – |
| 10 ✓ | Was passiert nach dem **Löschen** einer Einheit? (2026-11-16, aus Punkt 3; Mike: ja, auffüllen) | **Am Kursende wird aufgefüllt** (`fillUp()`), wie nach Ferien (Frage 7) und beim Entfernen einer Doppelstunde (Punkt 2). Damit gilt überall eine Regel: Die Teilnahme hat immer `plannedLessons` reguläre Einheiten; eine dazukommende nimmt die letzte zukünftige weg, eine gelöschte kommt am Ende wieder. Vorher ein Popup, weil Inhalte (Notiz) verloren gehen. | Nicht auffüllen: Die Nachholregel zeigt dann „1 Nachholstunde fällig“, Sonja trägt sie selbst ein. Passt, wenn Löschen „die Stunde schulde ich noch“ heißt |
| 9 ✓ | Doppelstunde (Mike 2026-11-16) | **Zwei Einheiten:** Statt die Dauer zu verlängern, wird eine zweite Einheit derselben Person im frei gewordenen Zeitfenster angelegt. | Dauer verlängern: zählte nur als eine Einheit |

**C4a: CSRF** (2026-11-16, beim Installieren gefunden). Das Flex-Rezept von `symfony/stimulus-bundle` legt
`assets/controllers/csrf_protection_controller.js` an. Symfony versieht das CSRF-Feld jedes Formulars standardmäßig mit
`data-controller="csrf-protection"` (`vendor/symfony/framework-bundle/DependencyInjection/Configuration.php`, Standardwert
von `field_attr`). Sobald Stimulus im Admin läuft, würde der Controller also bei jedem Admin-Formular geladen und den
CSRF-Schutz umstellen: Statt nur die Herkunft der Anfrage zu prüfen (Header `Sec-Fetch-Site` bzw. `Origin`, so wie heute),
erzeugt das JavaScript ein Zufallstoken und schickt es als Formularfeld **und** als Cookie mit („double submit“).
Laut Kommentar in `vendor/symfony/security-csrf/SameOriginCsrfTokenManager.php` ist das für Browser gedacht, die keinen
der beiden Header senden. Mit Session merkt sich Symfony außerdem die Methode und lehnt danach einen Rückfall auf die
Header-Prüfung ab.

| Option | Pro | Contra |
|---|---|---|
| **Entfernen (Empfehlung)** | Verhalten bleibt wie heute und wie in den Tests (die laufen ohne JavaScript); überall dieselbe Prüfung, die öffentlichen Seiten haben ohnehin kein Stimulus; aktuelle Browser senden `Sec-Fetch-Site` | Kein zusätzlicher Schutz für sehr alte Browser |
| Behalten | Zusätzliche Absicherung, Standard des Rezepts | Admin prüft anders als der Rest und als die Tests; lädt das JavaScript einmal nicht, scheitert ein Formular nach erfolgtem Double-Submit in derselben Session |

Der JSON-Endpunkt des Rasters (C4c) ist davon nicht betroffen: Er bekommt ein Token aus der Session.

**→ Entschieden (Mike, 2026-11-16): entfernt.** Erklärung: `erklaerungen/17-csrf.md`.

**Frage 8: Durchsicht** (2026-11-16). Mikes Vorschlag: Jede Wochenzeile bekommt darunter eine niedrige, nur bei Bedarf
sichtbare Zeile für abgesagte Einheiten (rot). Die abgesagte Einheit wandert dorthin, ihr Zeitfenster oben wird frei, die
Einheit links oder rechts davon kann sich hineinziehen (= zweite Einheit derselben Person, Frage 9). In der abgesagten
Einheit gibt es „Absage aufheben“: Sie kehrt nach oben zurück, was inzwischen in ihrem Zeitfenster eingetragen wurde, wird
gelöscht.

Machbar, auch ohne Änderung am Datenmodell: „Nach unten wandern“ ist nur Anzeige von `cancelledBy`, die zweite Einheit
ist eine normale `Lesson`. Was dabei leicht vergessen wird:

Antworten von Mike (2026-11-16) stehen je Punkt als **→**.

1. **→ Zeitfenster im Spaltenkopf, Name in der Zelle** (Stundenplan-Ansicht). **Spalten sind heute Teilnahmen, nicht Zeitfenster.** Die zweite Einheit von B gehört zu B, steht aber in der Spalte
   von A. Die Spalte muss also als **Zeitfenster** gelten (Wochentag + Uhrzeit der Teilnahme), die Zelle zeigt dann den
   Namen von B und hängt optisch an B an. Die Zählung im Kopf („11 von 15“) bleibt je Teilnahme.
2. **→ ok.** **Gesamtzahl der Einheiten von B.** Zählt die Doppelstunde als zwei Einheiten, hätte B am Ende 16. Vorschlag: Beim
   Anlegen fällt die letzte noch nicht stattgefundene Einheit von B am Kursende weg (wie bei Frage 7). Liegt keine mehr
   in der Zukunft (Kursende), bleibt es bei 16, mit Hinweis.
3. **→ Allgemein: jede Einheit im Raster löschbar** (irrtümlich angelegt, oder Sonja will nicht „abgesagt“ eintragen).
   Popup vorher; was danach passiert, ist Frage 10. **Rückgängig für die zweite Einheit selbst** fehlte im Vorschlag:
   irrtümlich angelegt → löschen; die Einheit am Kursende kommt zurück.
4. **„Absage aufheben“ löscht Einheiten mit Inhalt**, z. B. die Doppelstunde von B mit Notiz. Davor dasselbe Popup wie
   bei den Ferien, und für B kommt die Einheit am Kursende zurück (Punkt 2 umgekehrt).
5. **„Im Zeitfenster“ heißt überschneidend,** nicht „gleicher Beginn“: gelöscht wird, was sich zeitlich mit der wieder
   aufgenommenen Einheit überschneidet.
6. **→ Dauer von B, nicht des Zeitfensters** (Mike: Schüler:innen zahlen für die vereinbarte Dauer; anders ginge es nur
   mit Abrechnung nach Minuten). Daher: Die zweite Einheit hat die Dauer der Teilnahme von B und schließt direkt an B an,
   links endet sie bei Bs Beginn, rechts beginnt sie bei Bs Ende. Ist das frei gewordene Zeitfenster kürzer, meldet der
   Server eine Überschneidung mit der nächsten nicht abgesagten Einheit und legt nichts an. Angezeigt mit Dauer.
7. **Nachbarn nur am selben Tag:** links/rechts gilt nur zwischen Zeitfenstern desselben Wochentags. Heute unterrichtet
   Sonja je Kurs an einem Tag, bei mehreren Tagen wäre „rechts“ sonst ein anderer Tag.
8. **→ In der Spalte ihrer Teilnahme.** Gemeint war: Ihre **Uhrzeit** passt zu keinem Spaltenkopf (Nachholstunde z. B.
   Mittwoch 17:00, alle Spalten sind Freitag). Die Spalte ergibt sich deshalb nicht aus der Zeit, sondern aus der
   Teilnahme; die Zelle zeigt Datum und Uhrzeit dazu. Liegt in derselben Woche auch die reguläre Einheit dort, stehen
   beide untereinander.
9. **→ Entfällt:** Findet die Doppelstunde von B doch nicht statt, wird sie gelöscht und verschwindet aus dem Raster.
   (Ursprünglich: Mehrere Absagen im selben Zeitfenster stünden untereinander.)
10. **→ Knöpfe am Rand der Zelle.** **Bedienung:** Ziehen am Rand ist wie die Tastaturkürzel eher Luxus und am Tablet fummelig. Vorschlag: In der frei
    gewordenen Zelle stehen Knöpfe „+ Doppelstunde B“ und „+ Doppelstunde C“ (die Nachbarn). Ziehen ließe sich später
    ergänzen, der Endpunkt bliebe derselbe.
11. **Unverändert:** Abrechnung (Pauschale je Teilnahme), Nachholregel (A: Absage durch Sonja → nachholen, durch
    Schüler:in → verfällt), Ferien (löschen auch Doppelstunden).

**Kursname** (2026-11-16, Mikes Frage). Heute gibt es kein Namensfeld: Der Titel entsteht aus Semester und Jahr
(„Wintersemester 2026/27“, `Term::labelKey()`, `templates/admin/course/_title.html.twig`). Zwei Kurse im selben Semester
hießen also gleich. Mikes Vorschlag: „Wochentag WS/SS Jahr“. Der Wochentag ist aber keine Eigenschaft des Kurses, sondern
jeder Teilnahme (`Enrollment::$weekday`); ein Kurs kann Teilnahmen an mehreren Tagen haben. Honorarnote (Leistungstext,
Dateiname `WiSe_2026`) bleibt in allen Varianten unverändert.

| Option | Pro | Contra |
|---|---|---|
| **Abgeleitet (Empfehlung):** Wochentage aus den Teilnahmen anhängen, „Wintersemester 2026/27 (Fr)“, bei mehreren „(Mo, Fr)“ | Keine Migration, stimmt immer, nichts doppelt gespeichert (wie beim Status der Einheit) | Neuer Kurs ohne Teilnahmen zeigt keinen Tag; braucht die Teilnahmen beim Anzeigen (in Listen eine Abfrage mehr oder ein Join) |
| Freies Feld „Bezeichnung“ (optional), angehängt: „Wintersemester 2026/27 – Freitag“ | Sonja bestimmt den Text, auch Ort oder Schule möglich | Migration; kann veralten, wenn sich die Tage ändern |
| So lassen | Nichts zu tun | Zwei Kurse im selben Semester nicht unterscheidbar |

**→ Entschieden (Mike, 2026-11-16): abgeleitet.** `Course::getWeekdays()`, `templates/admin/course/_title.html.twig`.

**C5 Schnupperstunden** – erledigt 2026-11-13

- Entity `TrialLesson`, Anlegen und Liste, Übernahme in eine Teilnahme (Transaktion, `fromTrial`).

**C6 Einstellungen** – erledigt 2026-11-13 (ohne `HomeSlide`, das ist ein eigener Schritt)

- Entity `Settings` (eine Zeile): Theme, Standarddauer, Schwellwert, Absenderdaten, Unterschrift (Upload, PNG, in der
  Datenbank). `App\Theme\SiteSettings` liest das Theme dann von hier.

## D. Honorarnoten

**D1 Honorarnoten erstellen** – erledigt 2026-11-13 (Nummernvergabe mit Advisory Lock statt Wiederholung, siehe `erklaerungen/13`)

- Entities `Invoice` (Embeddable `Recipient`, Nummer je Jahr mit Unique-Constraint und Wiederholung bei Kollision)
  und `Payment`. Erstellen aus einer Teilnahme mit Snapshot, Unveränderlichkeit über fehlende Setter.

**D2 PDF und Download** – entschieden 2026-11-14: dompdf

- **Entscheidung vorher:** PDF-Bibliothek, die im PHP-Container läuft (kein zusätzlicher Container). Maßstab ist das
  Muster `HN16_…`. Frage und Antwort im Projektstand („Jetzt“).

  *Optionen (vorgestellt 2026-11-13, Versionen und Lizenzen von Packagist).* Ausgeschlossen: wkhtmltopdf (GitHub-Repo
  seit 2023 archiviert), Gotenberg (zusätzlicher Container), Headless Chrome (zu viel RAM).

  | Bibliothek | Version, Lizenz | Arbeitsweise | Pro | Contra |
  |---|---|---|---|---|
  | `dompdf/dompdf` | 3.1.6, LGPL-2.1 | HTML und CSS aus Twig (mit Übersetzungsschlüsseln) | Layout wie eine Webseite, leicht änderbar | Laut README kein Flexbox und kein Grid, Layout über Tabellen |
  | `mpdf/mpdf` | 8.3.1, GPL-2.0-only | HTML und CSS | Etwas mehr Funktionen für Seiten (Kopf-/Fußzeilen) | Strengere Lizenz (Copyleft), mehr Abhängigkeiten |
  | `tecnickcom/tc-lib-pdf` | 8.84.0, LGPL-3.0+ | PHP-Code mit Koordinaten | Pixelgenau, sehr aktiv (Nachfolger von TCPDF) | Viel mehr Code, Layout in PHP statt Template |
  | `setasign/fpdf` | 1.9.0, MIT | PHP-Code mit Koordinaten | Winzig | Laut FAQ kein UTF-8 (nur windows-1252) |

  (2026-11-14: Die Honorarnote bekommt keine Unterschrift; ob GD dann noch nötig ist, beim Einbau prüfen.)
  Alle brauchen die PHP-Erweiterung GD für die Unterschrift (PNG mit transparentem Hintergrund): eine Zeile im
  `Dockerfile` (`install-php-extensions gd`), das Image wird etwas größer (beim Einbau messen).

  **Empfehlung Claude:** dompdf; vorher RAM-Bedarf und Ergebnis an einem Prototyp messen.
- PDF beim Erstellen erzeugen und in `invoice_pdf` ablegen, Dateiname nach Schema, Download einzeln und als ZIP je
  Kurs mit Hinweis auf fehlende Honorarnoten.

**D3 Zahlungen und Gutschriften** – erledigt 2026-11-13

- Zahlung erfassen (ein Klick für den Rest, Datum änderbar), Status berechnet, Gutschrift mit `credits` und
  `InvoiceVoter::CREDIT`.

**D4 Erinnerung bei überschrittener Frist** – Befehl erledigt 2026-11-13, Cronjob: `deployment.md` S8

- Befehl `app:invoices:remind-overdue` mit `remindedFor`, Tests mit festgelegtem Datum (`ClockInterface`/`MockClock`,
  damit Tests nicht vom heutigen Tag abhängen). Danach Cronjob am Server (nach Rückfrage, `deployment.md` S8).

**D5 Export der Einnahmen** – erledigt 2026-11-13 (mit Sonjas Excel noch zu prüfen)

- CSV je Jahr nach `Payment.paidOn` (Semikolon, Dezimalkomma, UTF-8 mit BOM). Mit Sonjas Excel prüfen.

## E. Dashboard – erledigt 2026-11-13

- Nächste Einheiten (inkl. Schnupperstunden), zuletzt gehaltene Einheit je Schüler:in mit Inhalt, Ausfälle, offene
  Honorarnoten, Einnahmen je Kurs und Jahr.

## F. Vor den echten Daten

- **Backups** der Datenbank: Mac zieht täglich einen verschlüsselten Dump per SSH (launchd, eingeschränkter Schlüssel),
  Rücksicherung einmal testen. Plan: `backups.md` (Entwurf 2026-11-14), Server-Teil nach Rückfrage.
- **Datenschutz-Information** für Schüler:innen und Eltern (Art. 13 DSGVO), Entwurf zum Prüfen.
- **Aufbewahrungsfrist** der Honorarnoten in der BAO nachlesen, Lösch-/Anonymisierungsregel festhalten.
  Nachgelesen 2026-11-13 (RIS, § 132 BAO): Abs. 1 „Bücher und Aufzeichnungen sowie die zu den Büchern und
  Aufzeichnungen gehörigen Belege sind sieben Jahre aufzubewahren“, darüber hinaus so lange, wie sie für anhängige
  Abgabenverfahren von Bedeutung sind; die Frist läuft für Bücher und Aufzeichnungen „vom Schluß des Kalenderjahres“.
  Abs. 2: Aufbewahrung auf Datenträgern zulässig, wenn die Wiedergabe vollständig, geordnet, inhaltsgleich und bis zum
  Ende der Frist jederzeit gewährleistet ist (spricht für Backups der Datenbank samt PDFs, Abschnitt F).
  Die Lösch- und Anonymisierungsregel daraus steht als offener Punkt im Projektstand.

## Meilensteine

| Meilenstein | Nach Schritt | Was Sonja sehen kann |
|---|---|---|
| **Sonja zeigen** | C5 + D1–D3 (Mike lokal mit Fixtures, Sonja in Produktion) | Login, Schüler:innen, Kurs mit Ferien, Kursraster, Schnupperstunde übernehmen, Honorarnote als PDF, Zahlung |
| **Echtbetrieb** | F | Sonja trägt ihre sechs Schüler:innen ein |
| **Komplett** | D4, D5, E, B5 | Erinnerungen, Export, Dashboard, Passkeys |

B5 (Passkeys) kann vor oder nach dem Zeigen kommen; spätestens vor dem Echtbetrieb, wenn Sonja auf Passkeys umsteigen
will.

Kein eigener Demo-Termin (Entscheidung 2026-11-13, Projektstand): Mike sieht sich die Funktionen lokal an
(`docs/rundgang-admin.md`) und zeigt sie Sonja in Produktion.

## Später (außerhalb dieses Plans)

`HomeSlide` mit Bild-Upload, Zugang für Schüler:innen (`ROLE_STUDENT`, ggf. Login für Elternteile), Repertoireliste,
CMS für Texte, FAQ und Unterrichtsregeln von Sonja.
