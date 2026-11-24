# 25 Seitentexte aus der Datenbank: der Translator-Decorator

Stand 2026-11-24. Quellen: der installierte Code von Symfony 8.1.11 (`vendor/`, Fundstellen mit Zeilennummer im Text),
dazu `debug:container` im lokalen Container. Die Symfony-Doku zur Service-Dekoration
(symfony.com/doc/current/service_container/service_decoration.html) habe ich für diesen Text nicht abgerufen; alle
Aussagen sind am Code belegt. Anlass: das Mini-CMS (`planung/cms.md` K1), Datei `src/Content/PageTextTranslator.php`.

## 1. Das Problem

Alle Texte der öffentlichen Seiten stehen in `translations/content.de.yaml` und kommen so ins Template:

```twig
<p>{{ 'lessons.audience.body'|trans({}, 'content') }}</p>
```

Sonja soll jeden dieser Texte ändern können, ohne Mike und ohne Deploy. Entschieden ist (Mike, 2026-11-23): Ein
geänderter Text liegt in einer eigenen Tabelle `page_text`. Gibt es dort keinen Eintrag, gilt der Text aus dem YAML.

Offen war nur, **an welcher Stelle** das Programm in der Tabelle nachsieht. Dazu muss man wissen, welchen Weg ein Text
vom YAML bis ins HTML nimmt.

## 2. Der Weg eines Textes vom YAML ins HTML

1. **Build:** Beim Bauen des Images ruft `composer run-script post-install-cmd` den Befehl `cache:clear` auf
   (`Dockerfile`, Abschnitt „copy sources“; `composer.json` → `auto-scripts`). Dabei liest Symfony alle Dateien in
   `translations/` und schreibt je Sprache **einen Katalog** als PHP-Datei in den Cache, z. B.
   `var/cache/prod/translations/catalogue.de.gieD4Pp.php` (Dateiname: `Translator.php` Zeile 357). Ein Katalog ist ein
   großes Array: Domain → Schlüssel → Text.
2. **Anfrage:** Twig ruft für `|trans` die Methode `TranslationExtension::trans()` auf
   (`vendor/symfony/twig-bridge/Extension/TranslationExtension.php` Zeile 96). Die gibt den Aufruf an den Dienst
   `translator` weiter.
3. **Translator:** `Translator::trans()` (`vendor/symfony/translation/Translator.php` Zeile 177) holt den Katalog
   (Zeile 185) und sucht darin den Schlüssel in der Domain.
4. **Formatter:** Der gefundene Text geht an den Formatter (`translator.formatter`, Zeile 223), der Platzhalter wie
   `%email%` ersetzt. Bei einfachen Texten macht das `strtr()`, bei `%count%` zusätzlich die Pluralregeln
   (`vendor/symfony/translation-contracts/TranslatorTrait.php` Zeile 50).
5. Twig maskiert das Ergebnis (`<` wird `&lt;`) und schreibt es ins HTML.

Eingreifen kann man an drei Stellen: im Template (Schritt 2), im Katalog (Schritt 1) oder im Dienst `translator`
(Schritt 3).

## 3. Die drei Möglichkeiten und warum ich den Dienst gewählt habe

| | Wo | Pro | Contra |
|---|---|---|---|
| **A. Decorator** (gewählt) | Dienst `translator` | Kein Template ändert sich. Werkzeuge, die `|trans` lesen, funktionieren weiter | Im Template unsichtbar, dass die Datenbank mitredet |
| B. Eigener Twig-Filter `|page_text` | Template | Im Template sofort sichtbar | 99 Stellen in 13 Templates ändern; `debug:translation` und `TranslationsTest` sehen die Schlüssel nicht mehr |
| C. Loader aus der Datenbank | Katalog | Symfony kennt Loader für viele Formate, ein weiterer passt ins Konzept | Der Katalog ist eine Datei im Cache, gebaut beim Build. Nach jeder Änderung durch Sonja müsste der laufende Server den Cache neu bauen |

**Die Beweggründe für A, der Reihe nach:**

1. **C scheidet aus.** Der Katalog ist bewusst eine Datei, die beim Build entsteht. Ihn bei jeder Änderung im
   laufenden Betrieb neu zu bauen, widerspricht dem Grundsatz „gebaut wird in der CI“. Außerdem muss jeder
   FrankenPHP-Worker die neue Datei bemerken. Das wäre der heikelste der drei Wege.
2. **Zwischen A und B entscheidet, was sich ändern muss.** Bei A ändert sich keine Zeile in den Templates, und die
   vorhandene Prüfung auf fehlende Schlüssel bleibt wirksam. `TranslationsTest` ruft `debug:translation` auf; der
   Befehl sucht `|trans` in den Templates und vergleicht mit den YAML-Dateien. Mit B fände er die Seitentexte nicht
   mehr, und wir bräuchten einen eigenen Ersatz.
3. **Alle Wege eines Textes sind abgedeckt.** Seitentexte kommen nicht nur über Twig. Das PDF der
   Datenschutz-Information wird aus denselben Templates gerendert, und ein Controller könnte `TranslatorInterface`
   direkt benutzen. Ein Decorator sitzt an der Stelle, über die alle gehen.
4. **Symfony macht es selbst so.** In dev hängt Symfony seinen eigenen Decorator `DataCollectorTranslator` um den
   Translator (`FrameworkExtension.php` Zeile 964), damit der Profiler jede Übersetzung mitschreiben kann. Das Muster
   ist also für genau diesen Dienst vorgesehen, und die Klasse war die Vorlage für unsere.
5. **Der Nachteil lässt sich mildern.** Dass im Template nicht sichtbar ist, woher ein Text kommt, steht im Kopf von
   `content.de.yaml`, in `begriffe.md` und hier. Ein Umstieg auf B ginge später ohne Änderung an den Daten.

## 4. Was ein Decorator ist

Ein **Decorator** ist ein Objekt, das ein anderes Objekt derselben Art umhüllt. Es bietet dieselbe Schnittstelle an,
gibt die meisten Aufrufe unverändert weiter und ändert nur einzelne. Wer es benutzt, merkt keinen Unterschied, außer
an dem, was der Decorator ändert.

Vergleich: Ein Sekretariat nimmt alle Anrufe für die Chefin entgegen. Fast alle stellt es durch. Nur bei einer
bestimmten Frage antwortet es selbst, weil es die Antwort auf einem Zettel hat.

Im Code:

```php
final class PageTextTranslator implements TranslatorInterface /* , … */
{
    public function __construct(private TranslatorInterface $translator) {}   // the wrapped original

    public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        // answer itself for changed page texts, otherwise ask the original
    }
}
```

## 5. Dekoration im Symfony-Container

Normalerweise müsste man jeder Stelle, die den Translator braucht, sagen: „Nimm den Decorator.“ Das sind Dutzende
(Twig, Formulare, Validator, eigene Klassen). Der Container erledigt das mit einem Attribut:

```php
#[AsDecorator('translator')]
final class PageTextTranslator …
```

Was dabei passiert (`vendor/symfony/dependency-injection/Compiler/DecoratorServicePass.php`):

1. Der bisherige Dienst `translator` wird umbenannt in `App\Content\PageTextTranslator.inner` (Zeile 63: `$id.'.inner'`).
2. Unter dem Namen `translator` steht ab jetzt unser Decorator. **Jede** Stelle, die `translator` bekommt, bekommt
   ihn, ohne dass wir sie anfassen.
3. `#[AutowireDecorated]` am Konstruktor-Parameter sagt: Hier hinein gehört der umbenannte Original-Dienst
   (`vendor/symfony/dependency-injection/Attribute/AutowireDecorated.php`, ausgewertet in `AutowirePass.php` Zeile 420).

**Mehrere Decorators bilden eine Kette.** Bei uns in dev (geprüft mit `debug:container`):

```
translator  →  translator.data_collector   (Symfony, für den Profiler)
                 └─ inner: App\Content\PageTextTranslator   (unserer)
                      └─ inner: translator.default   (Symfonys eigentlicher Translator)
```

In prod fehlt der `DataCollectorTranslator` (er gehört zum Profiler). Dort ist unser Decorator der äußerste. Die
Reihenfolge ist hier egal: Der Profiler sieht so die Texte, wie sie wirklich auf der Seite stehen, also auch die
geänderten.

## 6. Die Klasse Schritt für Schritt

### 6.1 Die Schnittstellen

```php
final class PageTextTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface,
    WarmableInterface, ResetInterface
```

Ein Decorator muss alles können, was andere vom Original erwarten. Symfonys Translator wird von verschiedenen Stellen
über verschiedene Schnittstellen benutzt. Ich habe dieselbe Liste genommen wie `DataCollectorTranslator`
(`vendor/symfony/translation/DataCollectorTranslator.php` Zeile 22), weil der an derselben Stelle sitzt:

| Schnittstelle | Wer braucht sie | Was wir tun |
|---|---|---|
| `TranslatorInterface` | Twig, Formulare, Validator, eigener Code: `trans()` | Hier ändern wir etwas (6.3) |
| `TranslatorBagInterface` | Wer den Katalog lesen will: `getCatalogue()`, z. B. `debug:translation` und unsere Admin-Seite | Unverändert weiter (6.6) |
| `LocaleAwareInterface` | `setLocale()`/`getLocale()`. Symfony setzt die Sprache je Anfrage bei allen Diensten mit dieser Schnittstelle | Weiter an das Original |
| `WarmableInterface` | Der Cache-Warmer beim Build | Weiter an das Original (6.7) |
| `ResetInterface` | Der Kernel nach jeder Anfrage | Vergessen, was wir gelesen haben (6.5) |

**Fallstrick:** Fehlt eine Schnittstelle, gibt es keinen Fehler beim Bauen des Containers. Eine Stelle, die
`instanceof` prüft, überspringt dann still ihre Arbeit. Beim `WarmableInterface` hieße das: Der Katalog wird beim
Build nicht vorgewärmt, sondern erst bei der ersten Anfrage in Produktion gebaut
(`vendor/symfony/framework-bundle/CacheWarmer/TranslationsCacheWarmer.php` Zeile 41 prüft genau das).

### 6.2 Konstruktor

```php
public function __construct(
    #[AutowireDecorated]
    private readonly TranslatorInterface&TranslatorBagInterface&LocaleAwareInterface $translator,
    #[Autowire(service: 'translator.formatter')]
    private readonly MessageFormatterInterface $formatter,
    private readonly Connection $connection,
    private readonly LoggerInterface $logger,
) {}
```

- **`$translator`** ist das Original (5.). Der Typ `A&B&C` (Intersection Type) verlangt, dass das Objekt alle drei
  Schnittstellen hat; sonst schlägt schon das Bauen des Containers fehl.
- **`$formatter`** ist derselbe Formatter, den Symfonys Translator benutzt (Schritt 4 in Abschnitt 2). Er ist ein
  privater Dienst (`FrameworkExtension.php` Zeile 1609, `new Alias(…, false)`). Privat heißt: nicht über
  `$container->get()` abrufbar, aber injizierbar. Es gibt keinen Autowiring-Alias dafür, deshalb der Name im Attribut.
- **`$connection`** ist die Datenbankverbindung von Doctrine DBAL, **nicht** der EntityManager. Gründe:
  - Wir brauchen zwei Spalten, `text_key` und `body`, als Liste. `fetchAllKeyValue()` liefert genau das, ein Array
    Schlüssel → Text. Objekte, Identity Map und Änderungsverfolgung des ORM brächten hier nichts.
  - Der Translator ist ein Grunddienst, den sehr viel anderes benutzt, auch der Validator, den wiederum das ORM
    benutzt. Je weniger der Translator selbst braucht, desto kleiner die Gefahr eines Zirkelbezugs. Gemessen habe ich
    keinen; DBAL ist schlicht die kleinste Abhängigkeit, die reicht.
- **`$logger`** für den Fall, dass die Datenbank nicht antwortet (6.4).

### 6.3 `trans()`: die eigentliche Arbeit

```php
public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
{
    $id = (string) $id;
    if (self::DOMAIN !== $domain || self::LOCALE !== ($locale ?? $this->translator->getLocale())) {
        return $this->translator->trans($id, $parameters, $domain, $locale);   // (1)
    }

    $text = $this->texts()[$id] ?? null;                                         // (2)
    if (null === $text) {
        return $this->translator->trans($id, $parameters, $domain, $locale);   // (3)
    }

    return $this->formatter->format($text, self::LOCALE, $parameters);          // (4)
}
```

1. **Schneller Ausweg.** Alles, was nicht Domain `content` auf Deutsch ist, geht sofort weiter: Menü, Formulare,
   Admin-Bereich, Validierungsmeldungen. Das ist die große Mehrheit der Aufrufe, und sie kostet nur zwei Vergleiche.
   Es wird keine Datenbank gefragt.
2. Gibt es für diesen Schlüssel einen geänderten Text? `texts()` liefert alle geänderten Texte (6.4).
3. Nein: Das Original antwortet mit dem Text aus dem YAML, genau wie ohne Decorator.
4. Ja: Der geänderte Text geht durch **denselben Formatter** wie ein Text aus dem YAML. Damit verhalten sich
   Platzhalter (`%price%`, `%email%`), Plural mit `%count%` und übersetzbare Parameter gleich, egal woher der Text
   kommt. (Eine erste Fassung hatte hier ein eigenes `strtr()`. Das reichte für unsere Texte, wäre aber bei `%count%`
   still anders gewesen als Symfony; geändert am 2026-11-24.)

**Warum nur Deutsch (`LOCALE = 'de'`)?** Die Tabelle hat keine Spalte für die Sprache, weil es nur Deutsch gibt
(`config/packages/translation.yaml`). Käme Englisch dazu, würde ein deutscher geänderter Text sonst auf der
englischen Seite erscheinen. So fällt eine andere Sprache auf ihre YAML-Texte zurück, bis die Tabelle eine
Sprachspalte bekommt.

**Was nicht berücksichtigt ist:** globale Parameter (`framework.translator.globals`). Symfonys Translator mischt sie
in Zeile 211–213 in die Parameter. Wir haben keine; kämen welche dazu, fehlten sie in geänderten Texten.

### 6.4 `texts()`: einmal je Anfrage lesen

```php
private ?array $texts = null;   // null = noch nicht gelesen

private function texts(): array
{
    if (null !== $this->texts) {
        return $this->texts;
    }
    try {
        $texts = [];
        foreach ($this->connection->fetchAllKeyValue('SELECT text_key, body FROM page_text') as $key => $body) {
            $texts[(string) $key] = \is_string($body) ? $body : '';
        }
    } catch (DatabaseException $exception) {
        $this->logger->error(…);
        $texts = [];
    }
    return $this->texts = $texts;
}
```

- **Eine Abfrage für alle Texte, beim ersten Seitentext einer Anfrage.** Eine Seite braucht 10 bis 30 Seitentexte.
  Je eine Abfrage wären 30 Rundreisen zur Datenbank; alle auf einmal ist eine. Die Tabelle bleibt klein: höchstens
  ein Eintrag je Schlüssel, heute 97 Schlüssel.
- **Lazy:** Seiten ohne Seitentexte (Admin-Bereich) fragen die Tabelle nie ab, weil der Ausweg in 6.3 (1) vorher
  greift.
- **Datenbank weg:** Dann gelten die YAML-Texte, und ein Eintrag landet im Log. So hält es auch das Theme
  (`App\Theme\SiteSettings::theme()`). Die öffentlichen Seiten bleiben erreichbar; nur Sonjas Änderungen fehlen
  vorübergehend.
- **Warum `is_string()`:** DBAL liefert `mixed`. PHPStan (Level max) verlangt, dass wir den Typ prüfen, statt ihn
  einfach anzunehmen.

### 6.5 `reset()`: warum der Worker-Modus das braucht

Bei klassischem PHP lebt jedes Objekt nur für eine Anfrage. FrankenPHP läuft bei uns im **Worker-Modus**: Ein
PHP-Prozess startet einmal und beantwortet dann viele Anfragen hintereinander. Dienste wie der Translator leben über
alle diese Anfragen.

Ohne `reset()` würde `$this->texts` nach der ersten Anfrage gefüllt bleiben. Ändert Sonja dann einen Text, sähe ein
Worker die Änderung nie, bis er neu startet. Das wäre ein Fehler, der lokal mit einem einzigen Aufruf nicht auffällt.

```php
public function reset(): void
{
    $this->texts = null;
    if ($this->translator instanceof ResetInterface) {
        $this->translator->reset();
    }
}
```

- **Wer ruft das auf?** Jeder Dienst mit `ResetInterface` bekommt automatisch das Tag `kernel.reset`
  (`vendor/symfony/dependency-injection/Kernel/ServicesBundle.php` Zeile 84). Nach jeder Anfrage ruft Symfony bei
  allen so getaggten Diensten `reset()` auf.
- **Warum auch das Original zurücksetzen?** Ein Decorator ersetzt den Dienst. Hätte das Original selbst ein `reset()`,
  käme der Aufruf sonst nicht mehr bei ihm an.
- **Geprüft:** Im laufenden Container einen Eintrag per SQL angelegt, Seite abgerufen (neuer Text), Eintrag gelöscht,
  Seite wieder abgerufen (YAML-Text). Derselbe Worker zeigte beide Male den richtigen Stand.

Dasselbe Problem und dieselbe Lösung stehen in `SettingsRepository::current()` („No caching in this service“) und in
`erklaerungen/22` zu OPcache und Workern.

### 6.6 `getCatalogue()` bleibt unverändert

```php
public function getCatalogue(?string $locale = null): MessageCatalogueInterface
{
    return $this->translator->getCatalogue($locale);
}
```

Der Katalog enthält weiter nur die YAML-Texte, nicht Sonjas Änderungen. Das ist Absicht:

- Die Admin-Seite (`App\Content\PageTexts`) liest daraus den **ursprünglichen Text**, den sie neben dem Textfeld
  zeigt und auf den „Zurücksetzen“ zurückführt.
- `debug:translation` vergleicht Templates mit den Dateien. Datenbanktexte haben dort nichts verloren.

Nebenwirkung: Wer `getCatalogue()->get(…)` statt `trans()` aufruft, bekommt den YAML-Text. In unserem Code tut das
nur `PageTexts`, und dort ist es gewollt.

### 6.7 `warmUp()` und `__call()`

- `warmUp()` gibt das Vorwärmen an das Original weiter. Ohne diese Methode baute der Build keinen Katalog (6.1,
  Fallstrick). Geprüft mit `cache:warmup` in prod: Der Katalog liegt danach in `var/cache/prod/translations/`.
- `__call()` reicht jede andere Methode an das Original weiter. Symfonys Translator hat Methoden außerhalb der
  Schnittstellen, z. B. `getFallbackLocales()`, die `debug:translation` benutzt. `DataCollectorTranslator` macht es
  genauso.

### 6.8 Registrierung im Container

Mehr Konfiguration braucht es nicht. `#[AsDecorator]` wird gelesen, weil die Klasse automatisch registriert wird
(`config/services.yaml`, `App\` unter `_defaults` mit `autowire` und `autoconfigure`; `AutowireAsDecoratorPass.php`
Zeile 37 liest das Attribut nur bei autowired Diensten). Für `PageTexts` brauchte es noch `#[Autowire(service: 'translator')]`: Für
`TranslatorBagInterface` gibt es keinen Autowiring-Alias, nur für `TranslatorInterface`
(`vendor/symfony/framework-bundle/Resources/config/translation.php` Zeile 71). Der Container meldete das beim ersten
Versuch mit einer klaren Fehlermeldung.

## 7. Das Zusammenspiel mit der Admin-Seite

`App\Content\PageTexts` ist die zweite Hälfte:

- **Ursprünglicher Text:** `getCatalogue('de')->get($key, 'content')`, also das YAML (6.6).
- **Speichern:** Ist der neue Text gleich dem ursprünglichen, wird kein Eintrag angelegt bzw. der vorhandene gelöscht.
  So bedeutet „es gibt einen Eintrag“ immer „Sonja hat etwas geändert“, und die Markierung „geändert“ stimmt.
- **Zeilenumbrüche:** Browser schicken Textfelder mit `\r\n`. Das Makro `text.paragraphs` trennt Absätze bei `\n\n`.
  Ohne Umwandlung erkennt es keine Absätze mehr.
- **Zurücksetzen** löscht den Eintrag. Ab der nächsten Anfrage antwortet wieder das Original.

## 8. Grenzen und Fallstricke

- **Zwei Quellen.** Ändert Mike einen Text im YAML, den Sonja schon geändert hat, sieht man Mikes Änderung nicht. Die
  Markierung „geändert“ zeigt, wo das so ist.
- **Umbenannte Schlüssel.** Benennt Mike `lessons.prices.body` im YAML um, bleibt Sonjas Eintrag unter dem alten Namen
  liegen und wirkt nicht mehr. Kein Fehler, kein Hinweis.
- **Kein Cache über Anfragen hinweg.** Jede Seite mit Seitentexten kostet eine kleine Abfrage. Bei unserem Besuch
  harmlos; Zwischenspeichern hieße, bei jeder Änderung alle Worker zu benachrichtigen, und das lohnt sich nicht.
- **Unsichtbar im Template.** Wer `|trans({}, 'content')` liest, muss wissen, dass die Datenbank mitredet.
- **Profiler in dev:** Er zeigt geänderte Texte als „defined“, weil der Schlüssel im Katalog existiert. Der Wert im
  Profiler ist aber der tatsächlich angezeigte.

## 9. Abgesichert durch Tests

- `tests/Admin/PageTextTest.php`: Ändern, Zurücksetzen, Text gleich dem ursprünglichen, leerer Text blendet einen
  Abschnitt aus, Platzhalter aus den Einstellungen, unbekannter Platzhalter abgelehnt, unbekannter Schlüssel 404.
- `tests/TranslationsTest.php` läuft unverändert weiter (Beweggrund 2 in Abschnitt 3).
- `tests/PublicPagesTest.php` rendert alle öffentlichen Seiten durch den Decorator.

## 10. Bearbeiten direkt auf der Seite (K4, 2026-11-24)

Der Decorator bleibt unverändert; K4 ändert nur, **wo** Sonja einen Text bearbeitet. Drei Entscheidungen im Code und
ihre Gründe:

**Markieren statt einrahmen.** Naheliegend wäre, den Decorator jeden Text in ein `<span data-page-text="…">` einpacken
zu lassen. Das geht schief, weil viele Texte nicht frei im HTML stehen:

- in Attributen: `alt="{{ 'about.image_alt'|trans }}"` würde zu `alt="<span …>Porträt…</span>"`;
- in Bedingungen: `{% if claim is not empty %}` ist mit einem Rahmen nie mehr leer;
- zerlegt: `text.paragraphs` trennt bei Leerzeilen, `text.list` bei Zeilenumbrüchen, ein Rahmen landet in einem Teil;
- in einer Adresse: Der Google-Maps-Link enthält die Schuladresse URL-kodiert.

Deshalb bekommt im Template das **Element, das den Text zeigt**, ein Attribut: `<p {{ page_text_attr('…') }}>`. Für
Besucher:innen gibt die Funktion nichts aus; ihr HTML ist bis auf die geänderten Bausteine (Makros mit einem dritten
Parameter, ein `<span>` um Texte mitten in einem Absatz) unverändert.

**Neu laden statt ersetzen.** Nach dem Speichern lädt die Seite neu. Den Text an Ort und Stelle zu ersetzen hieße, in
JavaScript nachzubauen, was Twig tut: Absätze, Listen, Platzhalter wie `%price%`, Ausblenden leerer Abschnitte. Das
wäre eine zweite Stelle, die mit den Templates übereinstimmen muss. Das Neuladen zeigt dagegen immer genau das, was
Besucher:innen sehen. Damit der Bearbeitungsmodus das Neuladen übersteht, steht er in `sessionStorage`, nur in Sonjas
und Mikes Browser.

**Fallstrick Stimulus-Bereich.** Ein Stimulus-Controller hört nur auf `data-action`s innerhalb seines eigenen Elements
(hier die Leiste). Das Bearbeitungsfeld wird aber neben den Text gesetzt, also außerhalb. Im ersten Versuch hatte das
Formular `data-action="page-editor#save"`: Stimulus hat es nie gesehen, der Browser schickte das Formular normal ab,
die Seite lud neu, und nichts war gespeichert. Die PHP-Tests konnten das nicht bemerken; erst der Versuch im echten
Browser. Jetzt hängt der Controller die Ereignisse selbst an (`addEventListener`).

Woher die Seite weiß, dass Sonja angemeldet ist, und warum Besucher:innen trotzdem kein Cookie bekommen:
`erklaerungen/08`, Abschnitt „Eine Firewall“.
