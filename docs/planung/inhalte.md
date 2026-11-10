# Phase 2: Inhalte der öffentlichen Seiten

Stand: 2026-11-07 – Fragen gestellt, Antworten offen.

Ziel: Die öffentlichen Seiten (Home, Booking solo, Band, Lessons, Impressum, Datenschutz) so weit klären, dass sie als
erstes Release online gehen können. Admin-Bereich, Honorarnoten und dynamische Formulare kommen danach
(siehe `anforderungen.md`, F).

Antworten wie gehabt mit Bindestrich hinter die Fragen; „weiß ich noch nicht“ oder „frag Sonja“ ist auch eine Antwort.
Wo Texte, Bilder oder Dateien gefragt sind, reicht vorerst ein Hinweis, wer sie liefert und bis wann.

## A. Übergreifend (alle Seiten)

### Struktur und Navigation

1. Passt diese Seitenliste fürs erste Release: Home, Booking (solo), Band, Unterricht, Kontakt, Impressum,
Datenschutz? Fehlt etwas (z. B. „Über mich“, Referenzen/Stimmen, Medien, Termine/Auftritte)? - Home, Über mich, Booking, Unterricht, Kontakt (Impressum kann Teil von Kontakt sein, sofern das nicht einer gesetzlichen Vorgabe widerspricht) 
2. Hauptmenü: Welche Punkte, in welcher Reihenfolge, wie beschriftet? Sind „Booking“ solo und Band zwei Menüpunkte
   oder ein Menüpunkt mit Untermenü? - Home, Über mich, Booking, Unterricht, Kontakt; Booking mit Unterpunkten Solo (Taufe, Trauung, etc) und Band (Hochzeit, Feier, Gala, etc), genaues wording noch ausarbeiten
3. Gibt es eine eigene Kontaktseite oder nur Formulare auf den jeweiligen Seiten (Booking, Unterricht)? - Kontaktseite für allgemeine Anfragen/Kontaktaufnahme ist ok, aber dort soll darauf hingewiesen werden, dass für Anfragen bzgl Booking und Unterricht die Formulare auf den jeweiligen Seiten genutzt werden können.
4. URLs auf Deutsch (`/unterricht`, `/buchen`) oder Englisch (`/lessons`, `/booking`)? Wir haben vorerst nur Deutsch;
   Sprachpräfix (`/de/…`) jetzt schon oder erst, wenn eine zweite Sprache kommt? - URLs auf deutsch, vorerst kein Präfix für Übersetzung

### Gestaltung

5. Gibt es ein Logo, einen Schriftzug oder Farben, die Sonja schon verwendet (Visitenkarten, Social Media, Plakate)? - Nein, leider noch nicht. Kannst du ein Logo aus ihrem Namen "Sonja Czak" gestalten? Möglichst in Handschrift
6. Gibt es Websites, die Sonja (oder dir) gefallen – als Richtung für Stil und Aufbau? Bitte 2–3 Links und was daran
   gefällt. - https://dorohanke.com/
7. Grundstimmung: eher elegant/ruhig (Hochzeiten) oder eher bunt/lebendig (Band, Unterricht)? Eine Stimmung für alle
   Seiten oder je Bereich unterschiedlich? - eher elegant/ruhig für Auftritte, Unterricht können wir gerne bunt/lebendig versuchen. Muss ich sehen, um es zu beurteilen.
8. Schriftarten: Wunsch vorhanden? (Webfonts binden wir lokal ein, nicht über einen externen Dienst – spart
   Datenschutz-Aufwand.) - Schlicht, modern, für Hero/Hauptüberschriften gerne handwritten, muss aber nicht sein. Muss ich auch sehen, um es zu beurteilen.
9. CSS-Framework (offene Entscheidung im Projektstand): Bootstrap oder Tailwind? Ich lege dir vor dem ersten Template
   Vor- und Nachteile vor; hier nur, ob du schon eine Tendenz hast. - ich glaube, bootstrap reicht mir. Ich impliziere mit dieser Aussage, dass Tailwind besser wäre (ich weiß nur, dass feingranularer gesteuert werden kann, ohne sass zu kompilieren), bitte um Beratung!

### Bilder, Audio, Video

10. Welche Fotos gibt es (Porträts, Auftritte, Hochzeiten, Unterricht, Band)? Ungefähre Anzahl und Auflösung? - Siehe Ordner claude_references/images, die Fotos dort können für Band/Auftritte und Unterricht verwendet werden.
11. Bildrechte: Wer hat fotografiert, darf das Bild auf der Website verwendet werden, muss der Name der Fotografin /
    des Fotografen genannt werden? Sind auf Hochzeitsfotos Gäste oder das Brautpaar erkennbar (Einwilligung)? - die Fotos liegen jeweils in einem Unterordner, der den Namen des Fotografen/der Fotografin trägt, bitte verwenden, Einwilligungen sind vorhanden.
12. Sollen Hörproben oder Videos auf die Seite? Wenn ja: selbst gehostet (Datei auf unserem Server, ca. 8,7 GB Platte)
    oder eingebettet von YouTube/Vimeo/Spotify/SoundCloud (bindet einen Drittanbieter ein → Datenschutzerklärung,
    ggf. Zwei-Klick-Lösung)? - Hörproben sind auf der Website der Band verfügbar, wir können dorthin verlinken: https://www.redmulletmusic.at

### Kontakt und Präsenz

13. Welche Kontaktdaten sollen öffentlich sichtbar sein: E-Mail, Telefon, Adresse? Welche E-Mail-Adresse
    (eigenes Postfach unter sonjaczak.com ist noch offen, siehe Projektstand)? - Email und Telefonnummer können öffentlich sichtbar sein. 
14. Social-Media-Profile, die verlinkt werden sollen (Instagram, Facebook, YouTube, …)? - https://www.youtube.com/@redmulletmusic
15. Footer: Was soll dort stehen (Kontakt, Social Links, Impressum/Datenschutz)? - genau das

### Technik mit Auswirkung auf Inhalte

16. Webanalyse (Besucherstatistik) gewünscht? Wenn ja, eher ohne Cookies und selbst gehostet bzw. serverseitig – sonst
    wird ein Cookie-Banner nötig. - ja, selfhosted ohne cookies
17. Suchmaschinen: Für welche Begriffe soll Sonja gefunden werden (z. B. „Sängerin Hochzeit [Ort]“,
    „Gesangsunterricht [Ort]“)? Welche Region? - Regionen: Frohnleiten, Graz Umgebung, Steiermark (macht es Sinn, hier viele Orte in der Umgebung namentlich anzugeben? Wenn ja, suche nach Orten im 50km-Umkreis von Frohnleiten)
18. Pflege der Texte (offene Frage E14): Vorschlag fürs erste Release: Texte in Übersetzungsdateien, Bilder im Repo –
    Änderungen gehen dann über dich und einen Deploy. Ein kleines CMS planen wir mit dem Admin-Bereich und legen dort
    fest, welche Teile Sonja selbst ändern können muss (z. B. Preise, Unterrichtstermine, Startseitentext).
    Einverstanden? Welche Inhalte ändern sich voraussichtlich am häufigsten? - ja, einverstanden. Ich rechne nicht mit häufigen Änderungen, aber am ehesten Preise, Unterrichtszeiten, Startseitentext, ja. Evtl kommen auch neue Projekte/Bands hinzu (unter Booking), das kann aber dann auch ich machen, das muss nicht übers CMS laufen. 

## B. Home

19. Was soll man in den ersten Sekunden sehen und verstehen? (z. B. Name, „Sängerin & Gesangslehrerin“, ein Satz,
    ein großes Bild) - ja genau, den Satz soll sie selbst bearbeiten können - vorerst leer lassen aber Platz vorsehen.
20. Vollbild-Bilder: ein einzelnes Bild, mehrere untereinander (Abschnitte beim Scrollen) oder eine Slideshow? - Abschnitte beim Scrollen, mit subtilen Scroll-Effekten (leichte zoom oder translate Effekte)
21. Kurze Vorstellung von Sonja: Wer schreibt den Text, wie lang (ein Absatz oder mehrere)? Ich-Form oder dritte Person?
    Du oder Sie in der Ansprache (gilt dann für die ganze Seite)? - Text schreibe ich, sie kann ihn dann im CMS bearbeiten, die Kurzvorstellung nur 1 Absatz, bei Unterricht dann mehr, weil hier der Personenbezug relevanter ist, Ansprache auf Unterricht mit Du, bei Booking mit Sie, 
22. Soll die Startseite auf die drei Angebote (Solo, Band, Unterricht) als Kacheln/Abschnitte mit Link verweisen? - ja
23. Weitere Elemente: Zitate/Stimmen von Kund:innen, nächste Auftritte, Hörprobe? - Zitate/Stimmen ja, nächste Auftritte ja, Hörproben vorerst nur durch Verlinkung auf die Bandwebsite (https://wwww.redmulletmusic.at)

## C. Booking: Sonja solo

24. Welche Anlässe sollen genannt werden (Trauung standesamtlich/kirchlich/frei, Taufe, Begräbnis, Agape, Firmenfeier,
    Geburtstag, …)? - Begräbnis nicht, sonst alle
25. Was ist im Angebot enthalten (Stagepiano, Gesang, eigene Technik/Anlage, Mikrofon für Redner:innen, Absprache-
    gespräch)? - Stagepiano, eigener Lautsprecher, Mikrofon, alle Stative. Rednermikro auf Anfrage (nicht standardmäßig dabei)
26. Region: Wo tritt Sonja auf, ab wann fällt Anfahrt an? - in der Steiermark, Anfahrt ab 15 km ab Frohnleiten
27. Preise öffentlich zeigen (fix, „ab …“) oder nur auf Anfrage? (Die Preisschätzung mit Repertoireliste kommt später.) - nur auf Anfrage
28. Repertoire: Soll im ersten Release schon eine Liste (oder Auswahl / Beispiel-Songs) sichtbar sein, z. B. als
    statische Liste oder PDF? - nein, bitte für später notieren
29. Ablauf einer Buchung als Text (Anfrage → Gespräch → Angebot → Bestätigung)? Gibt es Anzahlung, Storno-Regeln, die
    auf der Seite stehen sollen? - Anfrage → Gespräch oder Schriftverkehr → Angebot → Bestätigung.
    Stornogebühren (gestaffelt nach Absagedatum):
    • Bis 90 Tage vor dem Termin: Einbehalt der Anzahlung (25 % der Gesamtgage).
    • 90 bis 60 Tage vor dem Termin: 50 % der vereinbarten Gage.
    • 60 bis 14 Tage vor dem Termin: 75 % der vereinbarten Gage.
    • Ab 14 Tage vor dem Termin / bei Nicht-Erscheinen: 100 % der vereinbarten Gage.
    Zusatzklauseln:
    • Berechnungsbasis: Alle Stornogebühren beziehen sich auf die reine Gage (exklusive eventueller Fahrtkosten).
    • Verschiebung: Bei einer Terminverschiebung kann aus Kulanz auf Stornogebühren verzichtet werden, sofern der neue Termin frei ist und fest gebucht wird.
    • Höhere Gewalt: Bei behördlichen Verboten oder unvorhersehbaren Katastrophen entfällt die Stornopflicht.
    • Krankheit der Sängerin: Bei eigenem Ausfall entfällt das Honorar; es wird (nach Verfügbarkeit) aktiv nach gleichwertigem Ersatz gesucht.

30. Erstes Kontaktformular (einfach, Mail an Sonja): Welche Felder? Vorschlag: Name, E-Mail, Telefon (optional),
    Anlass, Datum, Ort, Nachricht. Pflichtfelder? - Vorschlag passt, Alles außer Telefon Pflichtfelder

## D. Booking: Sonja mit Band

31. Name der Band und Link zur Band-Website? - Red Mullet - https://www.redmulletmusic.at
32. Hero-Bild: Gibt es ein passendes Bandfoto im Querformat (Bildrechte siehe A11)? - claude_references/red_mullet_sonja_vorne.png
33. Hero-Text und kurze Beschreibung: Wer liefert sie? Ungefähre Länge? - Ein Satz, wird von mir nachgereicht
34. Welche Anlässe/Genres deckt die Band ab (Hochzeitsfeier, Firmenfeier, Ball, …)? - Hochzeit, Gala, Firmenfeier, Ball, Sylvesterparty
35. Läuft die Anfrage für die Band über diese Seite (Formular / Mail an Sonja) oder nur über die Band-Website? - Nur über die Band-Website, das hat mit uns nichts zu tun.

## E. Unterricht

36. Ort: Name und Adresse des Schulgebäudes – darf das öffentlich stehen? Karte gewünscht (eingebettete Google Maps
    = Drittanbieter, alternativ nur Link oder statisches Bild)? - Bundesgymnasium Rein, Rein 1, 8103 Rein; Ja, Google Maps ist ok und Adresse darf öffentlich stehen
37. Zielgruppe: Altersgruppen (Kinder ab …, Jugendliche, Erwachsene), Niveaus (Anfänger:innen, Fortgeschrittene)? - Alter ab 12, Anfänger und Fortgeschrittene
38. Inhalte: Stilrichtungen (Pop, Musical, Jazz, Klassik, …), Schwerpunkte (Atemtechnik, Stimmbildung, Mikrofontechnik,
    Bühnenpräsenz, Vorbereitung auf Aufnahmeprüfungen, …)? - Pop, Rock, Funk, Soul, Musical, Jazz; Atemtechnik, Stimmbildung, Mikrofontechnik, Erarbeiten selbst ausgewählter Songs, Fokus auf Finden der eigenen Stimme
39. Sonjas Ausbildung/Qualifikationen: Sollen sie hier oder bei „Über mich“ stehen? - Hier ein kurzer Text:
    Sonja studierte in Wien Jazz- und Populargesang sowie Instrumental- und Gesangspädagogik und ist seit 2012 hauptberuflich als Sängerin und Gesangslehrerin tätig. Live-Erfahrung in unterschiedlichsten Formationen – von Ska-Punk über Funk bis Jazz – prägt ihren Unterricht ebenso wie Workshops und die Arbeit mit renommierten Musiker:innen. [Mehr über Sonja(Link zu Über mich)]
40. Preise: Semesterpreis öffentlich zeigen? Hinweis auf Ratenzahlung? Schnupperstunde mit eigenem Preis oder
    kostenlos? - Eckdaten öffentlich: Preis: € 495,- / Semester (= € 33,- pro Einheit); Unterricht zu zweit möglich (auf Anfrage)
41. Termine: Zeiträume der Kurse (z. B. „Wintersemester ab Oktober“), Unterrichtstage? Ferienregelung als Text? - Kurse richten sich grundsätzlich nach den Schuljahren (und auch den Schulferien), starten aber meist etwas später (Oktober), weil nur 15 Einheiten pro Semester
42. Regeln als Text auf der Seite (Absagen verfallen, Nachholung bei Absage durch Sonja – siehe `anforderungen.md`, B6)?
    Oder nur bei der Anmeldung/in den AGB? - Ich würde diese Texte gern Sonja selbst schreiben lassen. Notiere diese Texte als noch offen, ich muss sie dazu erst befragen.
43. Erstes Kontaktformular (einfach, ohne Termin-Widget): Welche Felder? Vorschlag: Name, E-Mail, Telefon, Alter bzw.
    „minderjährig“ (dann Name der Erziehungsberechtigten), Vorerfahrung, gewünschte Tage/Uhrzeiten (Freitext),
    Nachricht. Das Termin-Widget kommt mit dem Admin-Bereich. - Name, E-Mail, Telefon, Alter ("minderjährig" nicht in dem Wortlaut, sondern Feld für den Erziehungsberechtigten anzeigen wenn unter 18), Vorerfahrung (Freitext optional), gewünschte Uhrzeit (es wird vorerst nur ein Unterrichtstag angeboten)
44. Gibt es FAQ, die oft gestellt werden (Noten lesen nötig? Eigenes Instrument? Was mitbringen?)? - Ja, vorsehen, aber auch durch Sonja ausfüllen lassen.

## F. Impressum und Datenschutz

Die genauen Pflichtangaben prüfe ich gegen Primärquellen (RIS: E-Commerce-Gesetz, Mediengesetz, Gewerbeordnung;
DSGVO) und lege sie dir vor. Hier nur die Daten, die ich dafür brauche:

45. Name, Anschrift (ladungsfähig), E-Mail für das Impressum? - Sonja Riepl, Adriach-Rabenstein 83, 8130 Frohnleiten, rieplsonja@gmail.com (ad Namensungleichheit: wir sind noch nicht verheiratet, Name wird dann Czak sein, daher die Domain, vorerst Riepl verwenden)
46. Rechtsform und Status: Einzelunternehmerin? Gewerbe oder freiberuflich/neue Selbständige (betrifft z. B.
    Kammermitgliedschaft und Aufsichtsbehörde)? Mit Sonja bzw. Steuerberatung klären, nicht annehmen. - Muss ich abklären
47. UID-Nummer vorhanden? (Bei Kleinunternehmerregelung oft nicht – bitte nachsehen.) - nein
48. Wer ist für Inhalte verantwortlich (Sonja)? Wer wird als technischer Betreuer genannt, oder gar nicht? - Sonja, keine zweite Nennung
49. Datenschutzerklärung: selbst schreiben (ich entwerfe sie anhand der tatsächlich verarbeiteten Daten: Server-Logs,
    Kontaktformular, Mailversand, ggf. eingebettete Dienste) oder einen Generator / eine Vorlage der WKO verwenden? - gerne selbst schreiben.
50. Wer ist Hoster und Mail-Dienst (IONOS-VPS ist klar; Mail-Versand für Formulare ist noch offen)? Diese Dienste
    werden in der Datenschutzerklärung genannt. - Ist Mail-Versand auch über Ionos möglich? Ich weiß noch nicht, wie wir das technisch lösen.

## G. Sonstiges

51. Fehlerseite (404): neutral oder mit einem Augenzwinkern (z. B. „Dieser Ton wurde nicht getroffen“)? - Gerne mit Augenzwinkern
52. Gibt es einen Termin, bis wann die Seite online sein soll (z. B. vor der Hochzeitssaison, vor Semesterbeginn)? - so bald wie möglich
53. Wer gibt Texte frei – Sonja direkt im Markdown/Pull-Request, oder schickst du ihr eine Vorschau? - Ich gebe die Texte vorerst frei, sobald die Seite online ist, soll Sonja diese ohnehin über das Mini-CMS bearbeiten können.
