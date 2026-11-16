# CSRF und Tokens

Stand 2026-11-16, geprüft gegen den installierten Code (Symfony 8.1, `vendor/symfony/security-csrf/`,
`vendor/symfony/framework-bundle/`, `vendor/symfony/browser-kit/`). Anlass: Das Flex-Rezept von
`symfony/stimulus-bundle` brachte einen CSRF-Controller mit, der entfernt wurde (`umsetzung-admin.md` C4, „C4a: CSRF“).

## 1. Konzepte

### Der Angriff

CSRF heißt *Cross-Site Request Forgery*, auf Deutsch etwa „seitenübergreifend untergeschobene Anfrage“. Der Ablauf:

1. Sonja ist im Admin-Bereich angemeldet. Ihr Browser hat das Session-Cookie für `sonjaczak.com/admin`.
2. Sie öffnet in einem anderen Tab eine fremde Seite, z. B. aus einer Phishing-Mail.
3. Diese Seite enthält ein unsichtbares Formular, das sich per JavaScript selbst abschickt:
   `<form method="post" action="https://sonjaczak.com/admin/schueler/7/loeschen">`.
4. Der Browser schickt die Anfrage an sonjaczak.com und legt **von sich aus** das Session-Cookie bei. Für den Server sieht
   das aus wie ein Klick von Sonja.

Die fremde Seite kann die Antwort nicht lesen (das verhindert die Same-Origin-Policy des Browsers). Sie muss es aber auch
nicht: Der Schaden ist die Aktion selbst, z. B. ein Löschen. Deshalb betrifft CSRF nur **Anfragen, die etwas ändern**
(POST, PATCH, DELETE), nicht das Anzeigen von Seiten (GET).

Der Kern: **Das Cookie beweist, wer die Anfrage schickt, aber nicht, von welcher Seite aus sie ausgelöst wurde.** Jede
CSRF-Abwehr liefert diesen zweiten Beweis nach.

### Drei Arten, den Ursprung zu beweisen

| Verfahren | Wie | Warum die fremde Seite scheitert |
|---|---|---|
| **Geheimes Token in der Session** (klassisch) | Der Server erzeugt pro Session (und Zweck) einen Zufallswert, schreibt ihn ins Formular und merkt ihn sich in der Session. Beim Absenden vergleicht er beide. | Die fremde Seite kann unsere Seite nicht lesen, kennt das Token also nicht. |
| **Herkunfts-Header** (zustandslos) | Der Browser schreibt selbst dazu, woher die Anfrage kommt: `Sec-Fetch-Site: same-origin` bzw. `Origin`/`Referer`. Der Server prüft, ob das die eigene Adresse ist. | Diese Header setzt der Browser, JavaScript der fremden Seite kann sie nicht fälschen. |
| **Double Submit** (zustandslos) | JavaScript auf unserer Seite erzeugt einen Zufallswert und schickt ihn zweimal: als Formularfeld und als Cookie. Der Server prüft, ob beide gleich sind. | Die fremde Seite kann für unsere Domain kein Cookie setzen, also auch nicht beide Werte gleich machen. |

„Zustandslos“ heißt: Der Server muss sich nichts merken. Das ist für öffentliche Seiten wichtig, weil sie ohne Session
auskommen sollen (Caching, keine Session-Zeile in PostgreSQL für jede Besucherin).

### Was ein „Token“ hier ist

Ein Token ist ein Wert, der etwas beweist. Im klassischen Verfahren ist es ein Geheimnis, das nur unsere Seite kennt. Im
zustandslosen Verfahren von Symfony steht im Formular dagegen nur ein **Platzhalter**: der Text `csrf-token` (der Name des
Cookies, `SameOriginCsrfTokenManager::getToken()` gibt `new CsrfToken($tokenId, $this->cookieName)` zurück). Der
eigentliche Beweis sind dann die Herkunfts-Header oder, mit JavaScript, der Double Submit.

Die **Token-ID** (z. B. `submit`, `delete-student-7`) sagt, wofür das Token gilt. Beim klassischen Verfahren bekommt jede
ID einen eigenen Wert in der Session. So ist ein Token für „Schüler 7 löschen“ nicht für „Schüler 8 löschen“ gültig.

### SameSite-Cookies als zusätzliche Schicht

Symfony setzt das Session-Cookie standardmäßig mit `SameSite=Lax`
(`vendor/symfony/framework-bundle/DependencyInjection/Configuration.php`, `cookie_samesite`, Standard `lax`). Dann legt
der Browser das Cookie bei einem POST von einer fremden Seite gar nicht erst bei. Das allein reicht aber nicht als einziger
Schutz: Es hängt am Browser, und „same site“ ist weiter gefasst als „same origin“ (z. B. zählen Subdomains dazu). Deshalb
prüft Symfony trotzdem.

## 2. Wie es in diesem Projekt aussieht

### Zwei Token-Manager nebeneinander

`config/packages/csrf.yaml`:

```yaml
framework:
    form:
        csrf_protection:
            token_id: submit
    csrf_protection:
        stateless_token_ids:
            - submit
            - authenticate
            - logout
```

- **`stateless_token_ids`**: Diese drei IDs prüft der `SameOriginCsrfTokenManager` zustandslos. `submit` ist die ID aller
  Symfony-Formulare (`form.csrf_protection.token_id`), `authenticate` und `logout` gehören zu Login und Logout.
- **Alle anderen IDs** (`delete-student-7`, `user-access-3`, `import-breaks-2` …) gibt der Manager an den klassischen
  `CsrfTokenManager` weiter („fallback“, siehe `getToken()` und `isTokenValid()` im Vendor-Code). Diese Tokens liegen in der
  Session unter dem Schlüssel `_csrf` (`SessionTokenStorage::SESSION_NAMESPACE`). Im Admin-Bereich gibt es ohnehin eine
  Session, das kostet also nichts.

### Die zustandslose Prüfung Schritt für Schritt

`SameOriginCsrfTokenManager::isTokenValid()`:

1. **Herkunft prüfen** (`isValidOrigin()`): Ist `Sec-Fetch-Site` da, muss es `same-origin` sein. Sonst wird `Origin`, dann
   `Referer` mit der eigenen Adresse verglichen. Kein Header: Ergebnis „unbekannt“ (`null`).
2. **Double Submit prüfen** (`isValidDoubleSubmit()`): Steht im Feld nur der Platzhalter `csrf-token`, gibt es keinen
   Double Submit („unbekannt“). Sonst muss ein Cookie `csrf-token_<Wert>` (unter HTTPS `__Host-csrf-token_<Wert>`) da sein.
3. **Falsch** ist eines davon → abgelehnt. **Beide unbekannt** → abgelehnt.
4. **Kein Rückfall:** Gibt es eine Session, merkt sich Symfony dort, welches Verfahren schon funktioniert hat. Hat eine
   Anfrage einmal den Double Submit benutzt, wird eine spätere ohne ihn abgelehnt, auch wenn die Header passen. So kann
   ein Angreifer nicht auf das schwächere Verfahren ausweichen.

### Was der entfernte Controller getan hätte

Symfony versieht das CSRF-Feld jedes Formulars standardmäßig mit `data-controller="csrf-protection"`
(`field_attr`, Standardwert in `Configuration.php`). Das Rezept legte dazu `assets/controllers/csrf_protection_controller.js`
an. Es hängt sich an **jedes** `submit`-Ereignis der Seite, ersetzt den Platzhalter im Feld durch einen Zufallswert und
setzt das passende Cookie: also Double Submit (Schritt 2 oben).

Laut Kommentar im Vendor-Code ist das für den Fall gedacht, dass ein Browser weder `Sec-Fetch-Site` noch `Origin`/`Referer`
sendet. Mit Stimulus im Admin-Bereich wäre es dort aktiv geworden, auf den öffentlichen Seiten (ohne Stimulus) nicht.

**Entschieden (Mike, 2026-11-16): entfernt.** Ohne die Datei kennt Stimulus den Namen `csrf-protection` nicht und
ignoriert das Attribut; es bleibt harmlos im HTML stehen. Alle Formulare werden wie bisher über die Herkunfts-Header
geprüft.

### Und die Tests?

Die Tests laufen ohne Browser und ohne JavaScript. Sie bestehen trotzdem, weil der Symfony-Testbrowser beim Absenden die
vorige Seite als `Referer` mitschickt (`vendor/symfony/browser-kit/AbstractBrowser.php`, `HTTP_REFERER` aus der History).
`tests/ContactFormTest.php` prüft umgekehrt, dass eine fremde Herkunft abgelehnt wird.

### Das Raster (C4c)

Das Raster schickt Änderungen per `fetch` als `PATCH`, nicht über ein Symfony-Formular. Es bekommt eine eigene Token-ID
pro Kurs, die **nicht** in `stateless_token_ids` steht, also ein klassisches Session-Token. Twig schreibt es beim Laden der
Seite in ein `data-`-Attribut, Stimulus schickt es als Header mit, der Controller prüft es mit `isCsrfTokenValid()`. Damit
hängt das Raster nicht an den Herkunfts-Headern und nicht am entfernten Controller.

## 3. Alternativen

| Variante | Pro | Contra |
|---|---|---|
| **Header prüfen, Controller entfernt (gewählt)** | Ein Verfahren für die ganze Seite und die Tests; kein JavaScript nötig; alle aktuellen Browser senden `Sec-Fetch-Site` | Ein Browser ohne die Header (sehr alt, manche Datenschutz-Erweiterungen, die `Referer` und `Origin` entfernen) kann keine Formulare absenden |
| Controller behalten (Double Submit im Admin) | Funktioniert auch ohne Header; Standard des Rezepts | Admin prüft anders als der Rest und die Tests; fällt das JavaScript einmal aus, scheitert jedes Formular in dieser Session (kein Rückfall erlaubt) |
| Alles klassisch (Session-Token, `stateless_token_ids` leer) | Ältestes, bekanntestes Verfahren | Öffentliche Formulare (Kontakt, Booking) bräuchten eine Session, die es dort bewusst nicht gibt (`framework.yaml`: Cookie nur für `/admin`) |
| Nur `SameSite`-Cookies | Kein Code nötig | Hängt am Browser und an der Cookie-Einstellung; „same site“ umfasst auch Subdomains (Einschätzung: als einziger Schutz zu dünn) |

## 4. Sicherheit und Fallstricke

- **GET darf nie etwas ändern.** CSRF-Schutz greift nur bei Formularen bzw. POST/PATCH/DELETE. Ein Lösch-Link per GET wäre
  ungeschützt. Deshalb sind alle Lösch- und Schaltknöpfe im Admin kleine POST-Formulare (`templates/admin/_delete.html.twig`).
- **Token-ID je Datensatz.** `delete-student-7` statt nur `delete`: Ein abgefangenes Token taugt nur für genau diese Aktion.
- **Reverse Proxy:** Der Vergleich mit `Origin` braucht die eigene Adresse. Hinter einem Proxy muss Symfony den
  `X-Forwarded-*`-Headern vertrauen, sonst hält es `http://…` für die eigene Adresse und lehnt `https://…` ab. Bei uns
  terminiert FrankenPHP/Caddy HTTPS selbst, es gibt keinen Proxy davor.
- **Rezepte prüfen:** Flex-Rezepte bringen sinnvolle Standards mit, die aber nicht immer zu den eigenen Entscheidungen
  passen. Hier hätte das Rezept Stimulus auf allen Seiten geladen und das CSRF-Verfahren im Admin umgestellt, ohne dass
  ein Formular sichtbar anders wäre.
- **APP_SECRET** gehört nicht ins Repo; Symfony nutzt es u. a. für signierte Werte (`erklaerungen/03`).
