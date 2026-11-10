# Phase 2: Seitenstruktur (erstes Release)

Stand: 2026-11-11 – Entscheidungen getroffen, freigegeben.

Grundlage: Antworten in `inhalte.md`. Dieses Dokument legt Routen, Menü, Template-Aufbau, Übersetzungsschema und
CSS-Framework fest. Entscheidungen, die du treffen musst, stehen jeweils als **Entscheidung** mit Bindestrich zum
Beantworten; der Rest ist mein Vorschlag, den du einfach freigeben kannst.

## 1. Seiten und Routen

URLs auf Deutsch, ohne Sprachpräfix (A4). Routennamen und Controller auf Englisch (Code-Konvention aus `CLAUDE.md`).

| Seite | URL | Route | Controller | Inhalt erstes Release |
|---|---|---|---|---|
| Home | `/` | `app_home` | `PageController::home` | Hero, Kurzvorstellung, Angebote (3 Kacheln), Stimmen, nächste Auftritte |
| Über mich | `/ueber-mich` | `app_about` | `PageController::about` | Ausführlicher Text, Bilder |
| Booking: Übersicht | `/booking` | `app_booking` | `BookingController::index` | siehe Entscheidung 1 |
| Booking: Solo | `/booking/solo` | `app_booking_solo` | `BookingController::solo` | Anlässe, Leistungen, Region, Ablauf, Stornobedingungen, Formular |
| Booking: Band | `/booking/band` | `app_booking_band` | `BookingController::band` | Hero-Bild, ein Satz, Anlässe, Link zu redmulletmusic.at |
| Unterricht | `/unterricht` | `app_lessons` | `LessonsController::index` | Inhalte, Qualifikation, Ort mit Karte, Preise, Termine, FAQ, Formular |
| Kontakt + Impressum | `/kontakt` | `app_contact` | `ContactController::index` | Kontaktdaten, Hinweis auf die Formulare, allgemeines Formular, Impressum |
| Datenschutz | `/datenschutz` | `app_privacy` | `PageController::privacy` | Datenschutzerklärung |
| Fehlerseite 404 | – | – | Twig-Fehlertemplate | mit Augenzwinkern (G51) |

**Warum diese Controller-Aufteilung:** Seiten ohne Formular (Home, Über mich, Datenschutz) teilen sich einen
`PageController`. Booking, Unterricht und Kontakt bekommen je einen eigenen Controller, weil dort Formulare,
Mailversand und später das Termin-Widget bzw. die Preisschätzung dazukommen. Alternative: ein Controller pro Seite –
sauber, aber für drei statische Seiten mehr Dateien ohne Nutzen.

**Weitere Projekte/Bands später (A18):** Wenn neben Red Mullet weitere Bands dazukommen, wird aus `/booking/band`
z. B. `/booking/red-mullet`. Für das erste Release reicht die feste Route; eine Umbenennung lässt sich mit einer
Weiterleitung abfangen.

**Entscheidung 1 – Was zeigt `/booking`?** Der Menüpunkt „Booking“ hat Unterpunkte (A2). Optionen:
(a) Übersichtsseite mit zwei großen Kacheln „Solo“ und „Band“ – der Menüpunkt selbst ist klickbar, gut auf dem Handy;
(b) kein eigener Inhalt, `/booking` leitet auf `/booking/solo` weiter; (c) `/booking` gibt es nicht, „Booking“ öffnet nur das Untermenü. Mein Vorschlag: (a), weil das Menü dann auf allen Geräten gleich funktioniert und die Seite die zwei
Angebote voneinander abgrenzt. - Ja, a, aber nicht als Kacheln, sondern als Sections mit prominentem Hintergrundbild ausführen (probieren wir erstmal mit wedding.png und red_mullet_sonja_vorne.png)

## 2. Hauptmenü und Footer

```
Home · Über mich · Booking ▾ · Unterricht · Kontakt
                   ├ Solo (Trauung, Taufe, …)
                   └ Band (Hochzeit, Gala, …)
```

- Umsetzung mit KnpMenuBundle und eigenem `MenuBuilder` (wie in `CLAUDE.md` festgelegt). Aktuelle Version 3.8.0
  unterstützt Symfony `^8.0` (Quelle: packagist.org, `knplabs/knp-menu-bundle`). Für das Bootstrap-Dropdown braucht
  KnpMenu ein eigenes Render-Template, weil das Standard-Template nur verschachtelte `<ul>` ausgibt.
- Die genauen Beschriftungen der Unterpunkte arbeiten wir mit den Texten aus (A2, noch offen).
- **Footer** (A15): E-Mail, Telefon, YouTube-Link, Links zu „Impressum“ und „Datenschutz“.

## 3. Impressum als Teil der Kontaktseite (A1)

Geprüft im RIS:

- § 5 Abs. 1 ECG: Die Angaben sind „ständig … leicht und unmittelbar zugänglich zur Verfügung zu stellen“.
  Pflichtangaben u. a. Name, geografische Anschrift, Kontaktdaten inkl. E-Mail; je nach Tätigkeit Aufsichtsbehörde,
  Kammer/Berufsverband, Berufsbezeichnung und anwendbare Vorschriften; UID-Nummer nur „sofern vorhanden“.
- § 25 Abs. 1 MedienG: Auf einer Website sind die Offenlegungsangaben „ständig leicht und unmittelbar auffindbar zur
  Verfügung zu stellen“. Für Websites ohne meinungsbildenden Inhalt reichen nach Abs. 5 „Name oder die Firma,
  gegebenenfalls der Unternehmensgegenstand“.

Eine eigene Unterseite schreiben beide Bestimmungen nicht vor. **Vorschlag:** Impressum als eigener Abschnitt
`/kontakt#impressum`, im Footer jeder Seite ein Link mit der Beschriftung „Impressum“, der direkt dorthin springt. So
ist das Impressum von jeder Seite mit einem Klick erreichbar. Das ist meine Lesart des Gesetzestexts, keine
Rechtsberatung. Welche der optionalen Angaben (Kammer, Aufsichtsbehörde …) nötig sind, hängt von F46 ab (noch offen).

## 4. Template-Aufbau

```
templates/
├── base.html.twig              # HTML-Gerüst, <head>, Assets, Header, Footer
├── _partials/
│   ├── header.html.twig        # Logo + Menü
│   └── footer.html.twig
├── page/
│   ├── home.html.twig
│   ├── about.html.twig
│   └── privacy.html.twig
├── booking/ (index, solo, band)
├── lessons/index.html.twig
├── contact/index.html.twig
├── menu/knp_bootstrap.html.twig   # Render-Template für KnpMenu (falls Bootstrap)
└── bundles/TwigBundle/Exception/error404.html.twig
```

**Zwei Stimmungen (A7):** Booking elegant/ruhig, Unterricht bunt/lebendig. Umsetzung über eine Klasse am `<body>`
(z. B. `theme-stage` bzw. `theme-lessons`), die Farben und Akzente über CSS-Variablen umschaltet. Home, Über mich und
Kontakt bekommen das ruhige Grunddesign. So bleibt es *ein* Layout, und du kannst beide Varianten nebeneinander sehen,
bevor du entscheidest (A7/A8: „muss ich sehen“).

**Leere Platzhalter (B19):** Der Satz im Hero, Regeln und FAQ beim Unterricht, Stimmen, Auftritte: Abschnitte, deren
Text leer ist, werden nicht angezeigt. So kann die Seite online gehen, bevor alle Texte da sind.

**Ansprache (B21):** Booking mit „Sie“, Unterricht mit „Du“.
**Entscheidung 2 – Ansprache auf Home, Über mich und Kontakt?** - Entschieden (2026-11-11): alle Seiten außer
Unterricht mit „Sie“.

## 5. Übersetzungen: Schema und Format

Benötigte Pakete: `symfony/translation` (dazu `symfony/twig-bundle`, das noch gar nicht installiert ist).

**Schlüssel statt Text** – laut Symfony Best Practices (symfony.com/doc/current/best_practices.html, Stand 8.1):
„Use Keys for Translations Instead of Content Strings“, und Schlüssel sollen den *Zweck* beschreiben, nicht den Ort
(`label.username`, nicht `edit_form.label.username`).

Vorschlag: zwei Domains (= getrennte Dateien), weil sie sich unterschiedlich entwickeln:

| Domain | Datei | Inhalt | Schlüssel-Schema | Später |
|---|---|---|---|---|
| `messages` | `translations/messages.de.yaml` | Oberfläche: Menü, Footer, Buttons, Formularfelder, Fehlermeldungen | Zweck: `nav.lessons`, `form.label.email`, `action.send` | bleibt in der Datei |
| `content` | `translations/content.de.yaml` | Seiteninhalte: Überschriften, Fließtexte | Seite + Abschnitt: `home.hero.claim`, `lessons.prices.body` | Teile wandern ins CMS (Datenbank) |
| `validators` | `translations/validators.de.yaml` | eigene Validierungsmeldungen | Symfony-Standarddomain | bleibt |

Für die Inhalte weiche ich bewusst von der Best Practice ab und gruppiere nach Seite: Fließtexte gibt es nur einmal,
und beim Umzug ins CMS sieht man sofort, welche Schlüssel zu welcher Seite gehören.

**Entscheidung 3 – Dateiformat:** Die Best Practice empfiehlt XLIFF, weil Übersetzungswerkzeuge es am besten
unterstützen und man Notizen für Übersetzer:innen anhängen kann. Wir haben vorerst nur Deutsch und keine
Übersetzer:innen. Dafür schreibst du lange Texte selbst, und die lesen sich in YAML (mehrzeilig mit `|`) deutlich
leichter als in XML. Mein Vorschlag: **YAML**. Wechseln lässt sich später mit `translation:extract --format=xlf`. - yaml

Längere Texte mit Absätzen oder Links enthalten etwas HTML und werden mit `|trans|raw` ausgegeben. Das ist
unbedenklich, solange die Texte aus dem Repo kommen. Beim CMS müssen wir sie dann bereinigen (Fallstrick, notiert).

## 6. CSS-Framework und Assets – Beratung

**Assets:** Symfony empfiehlt AssetMapper (Best Practices: „Use AssetMapper to Manage Web Assets“): kein Node, kein
Bundler, Dateien werden direkt ausgeliefert und in Produktion mit Versions-Hash versehen. Beide Frameworks lassen sich
damit nutzen, jeweils über ein Bundle, das die nötige Binärdatei selbst herunterlädt („no Node required“):

| | Bootstrap 5.3.8 | Tailwind CSS 4.3.5 |
|---|---|---|
| Einbindung | `symfonycasts/sass-bundle` + `twbs/bootstrap` (Sass-Quellen) | `symfonycasts/tailwind-bundle` |
| Build-Schritt | `sass:build`, dann `asset-map:compile` | `tailwind:build --minify`, dann `asset-map:compile` |
| Fertige Komponenten | Navbar mit Dropdown und Mobilmenü, Formulare inkl. Validierungsstil, Accordion (FAQ), Modals – mit JavaScript | keine; alles aus Utility-Klassen selbst bauen (JS für Dropdown/Mobilmenü selbst) |
| Eigenes Design | über Sass-Variablen (Farben, Schriften, Abstände) + eigenes CSS | sehr direkt, jedes Detail in der Klasse |
| Symfony-Formulare | Formular-Theme `bootstrap_5_layout.html.twig` mitgeliefert | Tailwind-Theme möglich, aber eher selbst anpassen |
| Deine Erfahrung | viel | keine |

Versionen: npm-Registry, `latest` am 2026-11-11. Bundles und Build-Schritte: symfony.com/bundles/SassBundle und
symfony.com/bundles/TailwindBundle.

**Zu deiner Annahme (A9):** Tailwind ist nicht „besser“, sondern ein anderer Ansatz. Auch Tailwind braucht einen
Build-Schritt; es kompiliert zwar kein Sass, durchsucht aber die Templates und erzeugt daraus das CSS. Und auch
Bootstrap hat Utility-Klassen (`mt-3`, `d-flex` …), nur weniger. Tailwinds Stärke ist ein komplett eigenes Design ohne
gegen Framework-Vorgaben anzuarbeiten. Bootstraps Stärke sind fertige, zugängliche Komponenten.

**Empfehlung: Bootstrap mit Sass-Bundle.** Die Seite braucht genau die Teile, die Bootstrap fertig mitbringt (Navbar
mit Dropdown und Mobilmenü, Formulare, FAQ-Accordion), und du kennst es. Das elegante Eigen-Design (Schriften, Farben,
großzügige Bilder, Scroll-Effekte) entsteht über Sass-Variablen und eigenes CSS. Der Lernschwerpunkt des Projekts liegt
bei Datenbank, Security und Übersetzungen, nicht beim CSS-Framework. Tailwind wäre die bessere Wahl, wenn du es lernen
möchtest oder das Design stark von üblichen Website-Mustern abweichen soll.

**Entscheidung 4 – CSS-Framework:** - bootstrap

**Folge für Dockerfile und CI:** Der Prod-Build muss `sass:build` (bzw. `tailwind:build`) und `asset-map:compile`
ausführen. Das passiert beim Image-Bau in der CI, nicht auf dem Server.

## 7. Bilder, Schriften, Logo

- **Bilder:** Die Originale in `claude_references/` sind bis zu 8 MB groß. Für die Website erzeugen wir einmalig
  Web-Versionen in wenigen Breiten (z. B. für Handy, Laptop, großen Bildschirm) als WebP/JPEG und binden sie mit
  `srcset` ein. Ins Repo kommen nur diese Web-Versionen (`assets/images/`). Alternative: LiipImagineBundle erzeugt
  Größen zur Laufzeit, kostet aber Rechenzeit und Platz auf dem knappen Server. Das Werkzeug legen wir beim Umsetzen fest.
- **Bildnachweis:** Fotografin/Fotograf laut Ordnername (Doris Sporer, Simon Reithofer) als Nachweis am Bild oder
  gesammelt im Impressum. Für die Bilder unter `images/ai/` und `Potentielle Fotos FB und Bewerbung/` ist die Herkunft
  noch offen (siehe Abschnitt 9).
- **Schriften:** lokal eingebunden (A8). Ich schlage zwei bis drei Kombinationen vor (schlicht + handschriftlich für
  Hero/Überschriften), alle mit freier Lizenz (z. B. SIL Open Font License), damit du sie ansehen kannst.
- **Logo (A5):** Ich kann einen Schriftzug „Sonja Czak“ aus einer handschriftlichen Schrift als SVG entwerfen. Das ist
  ein Wortlogo, kein individuell gezeichnetes Logo. Für ein echtes Handschrift-Logo wäre eine Designerin/ein Designer
  oder Sonjas eigene Unterschrift (eingescannt und vektorisiert) besser. Vorerst „Sonja Czak“ oder „Sonja Riepl“? Laut
  F45 heißt sie noch Riepl, die Domain lautet aber auf Czak. → **Entscheidung 5:** - Entschieden (2026-11-11):
  „Sonja Czak“. Im Impressum steht bis zur Namensänderung „Sonja Riepl“.

Der Flyer (`Gesangsunterricht_Sonja_Riepl_A4.svg`) nutzt Dunkelgrau `#17151A`, Pink `#FF2E73` und Creme `#F8F5F2`.
Das wäre ein Kandidat für die „lebendige“ Unterrichtsvariante.

## 8. Dritte Dienste, Mail, Statistik, Suchmaschinen

Diese Punkte bestimmen, was in der Datenschutzerklärung steht. Deshalb kläre ich sie vor dem jeweiligen Schritt:

- **Google Maps (E36):** Eine eingebettete Karte überträgt beim Laden Daten an Google. Vorschlag: Zwei-Klick-Lösung
  (statischer Platzhalter, Karte lädt erst nach Klick) oder nur ein Link „In Google Maps öffnen“.
  **Entscheidung 6:** - Nur Link "In Google Maps öffnen"
- **Mail-Versand (F50):** Formulare brauchen einen SMTP-Zugang (Symfony Mailer). Ob und wie das über ein IONOS-Postfach
  geht, prüfe ich gegen die IONOS-Doku, bevor wir das erste Formular bauen. Das hängt mit den Postfächern unter
  sonjaczak.com zusammen (offene Entscheidung im Projektstand).
- **Statistik (A16):** selbst gehostet, ohne Cookies. Ein zusätzlicher Container braucht RAM, der knapp ist.
  Varianten (Auswertung der Server-Logs, kleines Tool, eigener Container) lege ich dir vor dem Online-Gang vor.
  Kein Blocker für die Seitenstruktur.
- **Suchmaschinen, Orte (A17):** Eine lange Liste von Orten im Umkreis empfehle ich nicht. Googles Spam-Richtlinien
  nennen ausdrücklich „Blocks of text that list cities and regions that a web page is trying to rank for“ als
  Keyword-Stuffing (developers.google.com/search/docs/essentials/spam-policies). Besser: die Region im Text natürlich
  nennen (Frohnleiten, Graz und Umgebung, Steiermark, BG Rein), gute Seitentitel und Beschreibungen pro Seite und ein
  Google-Unternehmensprofil (außerhalb der Website).

## 9. Offene Inhalte (blockieren die Struktur nicht)

| Inhalt | Wer | Seite |
|---|---|---|
| Wording der Booking-Unterpunkte | Mike | Menü |
| Hero-Satz | Sonja (später CMS), vorerst leer | Home |
| Kurzvorstellung (1 Absatz), Über-mich-Text | Mike | Home, Über mich |
| Stimmen/Zitate von Kund:innen: Texte und Einverständnis der Zitierten | Mike/Sonja | Home |
| Nächste Auftritte: Quelle? Eigene Liste oder Link auf die Band-Website | Mike | Home |
| Band: Hero-Satz | Mike | Booking Band |
| Regeln (Absagen, Nachholen), FAQ | Sonja | Unterricht |
| Rechtsform/Status für das Impressum | Mike/Sonja | Kontakt |
| Herkunft und Nutzungsrechte: Ordner `images/ai/`, `Potentielle Fotos FB und Bewerbung/`, `Sonja_rot.png`, `red_mullet_*.png` | Mike | – |

Für später notiert: Repertoireliste (C28), Preisschätzung, Termin-Widget, CMS für Preise, Unterrichtszeiten,
Startseitentext, FAQ.

## 10. Umsetzung in kleinen Schritten (nach Freigabe)

1. Pakete: `twig-bundle`, `translation`, `asset-mapper`, CSS-Bundle (laut Entscheidung 4), `knp-menu-bundle`;
   Dockerfile/CI um den Asset-Build ergänzen; prüfen, dass CI und Deploy grün bleiben.
2. `base.html.twig`, Header mit Menü (`MenuBuilder`), Footer, Übersetzungsdateien; Home als erste Seite mit
   Platzhaltern. Erster Test: jede Route liefert 200. Danach den Smoke-Test in der CI auf `/` umstellen
   (Kandidat aus dem Projektstand).
3. Design-Entwurf: Schriften, Farben, Logo – zwei Varianten zum Ansehen.
4. Restliche Seiten mit Texten; 404-Seite; Impressum und Datenschutzerklärung (gegen Primärquellen geprüft).
5. Bilder optimieren und einbinden, Scroll-Effekte.
6. Formulare mit Mailversand (nach Klärung SMTP) – eigener Plan.
