# 20 Testdaten mit Foundry: Factories und Stories

Stand: 2026-11-17. Anlass: `tests/Story/AppStory.php` in D2d (Fixtures ohne PDF). Quellen: installierter Code von
`zenstruck/foundry` 2.16.0 (`vendor/zenstruck/foundry/src/`), `config/packages/zenstruck_foundry.yaml`. Bedienung:
`docs/entwicklung.md`, „Testdaten (Foundry)“.

## 1. Drei Begriffe

| Begriff | Was es ist | Bei uns |
|---|---|---|
| **Fixture** | Allgemein: fest vorbereitete Daten, mit denen man eine Anwendung oder einen Test startet | `bin/console foundry:load-fixtures main` |
| **Factory** | Bauplan für **ein** Objekt einer Klasse: sinnvolle Standardwerte, überschreibbar | `tests/Factory/*Factory.php` |
| **Story** | Ein **Szenario** aus vielen Objekten, die zusammenpassen, gebaut mit Factories | `tests/Story/AppStory.php` |

Kurz: Eine Factory beantwortet „Wie sieht eine typische Teilnahme aus?“, eine Story „Wie sieht ein ganzer Kurs mit
sechs Teilnahmen, Absagen, Honorarnoten und Zahlungen aus?“.

## 2. Factory (zur Einordnung)

```php
EnrollmentFactory::createOne(['course' => $course, 'priceCents' => 49500]);
```

- `defaults()` liefert die Standardwerte (Freitag 15:30, neuer Kurs, neue Schüler:in). Was man übergibt, ersetzt sie.
- **Zustände** sind benannte Abwandlungen: `UserFactory::new()->admin()`, `StudentFactory::new()->minor()`,
  `InvoiceFactory::new()->withPdf()`.
- **Hooks:** `afterInstantiate()` läuft nach dem Erzeugen, vor dem Speichern (Einheiten erzeugen, inverse Seite
  pflegen); `afterPersist()` nach dem Speichern (das PDF braucht die gespeicherte Honorarnote).
- `createOne()` erzeugt und speichert sofort, `new()` liefert die Factory selbst, um weitere Zustände anzuhängen.

Tests nutzen Factories direkt und bauen sich nur die paar Objekte, die sie brauchen. So sieht man im Test, wovon das
Ergebnis abhängt.

## 3. Story

Eine Story ist eine Klasse, die von `Zenstruck\Foundry\Story` erbt und eine Methode `build()` hat. Darin ruft sie
Factories in der richtigen Reihenfolge auf und verknüpft die Ergebnisse:

```php
#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        // Konten, Einstellungen, 20 Schüler:innen, ein Kurs ...
        flush_after(static function () use ($adults, $course): void {
            // sechs Teilnahmen, Absagen, zwei Honorarnoten mit PDF, eine Zahlung
        });
    }
}
```

### Wie eine Story geladen wird

- **Als Fixture:** `#[AsFixture(name: 'main')]` meldet die Story beim Befehl `foundry:load-fixtures` an
  (`vendor/zenstruck/foundry/src/Attribute/AsFixture.php`; optional mit `groups`). Der Befehl fragt nach, setzt die
  Datenbank zurück (über die Migrationen) und ruft `build()` auf. Mit `--append` bleibt die Datenbank stehen und die
  Daten kommen dazu (`src/Command/LoadFixturesCommand.php`). Ohne Rückfrage geht es mit `--no-interaction`.
- **Im Test:** `AppStory::load()`. Foundry baut jede Story **nur einmal** und merkt sich die Instanz
  (`src/StoryRegistry.php`, `load()`: ist die Klasse schon in `$instances`, wird sie zurückgegeben, sonst `build()`).
  Zweimal `load()` legt also nicht alles doppelt an. Unsere Tests nutzen `AppStory` nicht; sie bauen ihre Daten
  selbst, damit jeder Test zeigt, wovon er abhängt.

### Was eine Story noch kann (bei uns ungenutzt)

- **Zustände merken:** `$this->addState('kurs', $course)` in `build()`, im Test dann `AppStory::get('kurs')`. So
  findet ein Test „den Kurs aus der Story“, ohne ihn zu suchen.
- **Pools:** `addToPool('erwachsene', $student)` sammelt Objekte; `AppStory::getRandom('erwachsene')` oder
  `getRandomSet('erwachsene', 3)` holt zufällige daraus.
- **Globale Stories:** in der Konfiguration eingetragen und vor allen Tests einmal geladen (`loadGlobalStories()`),
  z. B. für Stammdaten, die jeder Test braucht. Haben wir nicht.

### `flush_after()`

Normalerweise speichert jede Factory sofort (ein Flush je Objekt). Innerhalb von `flush_after(function () { … })`
sammelt Foundry alles und speichert am Ende in einem Flush (`src/Persistence/functions.php`). In `AppStory` nötig,
weil nach `createOne()` noch Werte geändert werden (Notizen an Einheiten, Absagen), die sonst nicht gespeichert würden.

## 4. Wo das liegt und warum

- **In `tests/`, nicht in `src/`:** Factories und Stories kommen nicht ins Produktions-Image (`.dockerignore`), und
  Foundry steht nur in `require-dev`. Es gibt in Produktion also keinen Befehl, der die Datenbank mit erfundenen Daten
  überschreiben könnte.
- **Als Dienste nur in dev und test** (`config/packages/zenstruck_foundry.yaml`, Abschnitt `services`): Dadurch dürfen
  Factories und Stories Abhängigkeiten im Konstruktor haben, etwa den Passwort-Hasher (`UserFactory`) oder den
  PDF-Renderer (`InvoiceFactory`).
- **Nur erfundene Daten** (Faker mit `de_AT`), nie echte Personen: Entscheidung „Daten für Entwicklung“ im
  Projektstand, DSGVO.

## 5. Fallstricke

- **Factories umgehen die Anwendung.** `InvoiceFactory` legt eine Honorarnote direkt an, ohne `InvoiceIssuer`. Damit
  fehlt alles, was der Issuer zusätzlich tut: Nummernvergabe unter dem Lock, Prüfungen, und seit D2 das PDF. Genau
  das ist in D2d passiert: Die zwei Honorarnoten der Story hatten kein PDF, der Download hätte lokal 404 geliefert.
  Lösung `withPdf()`. Faustregel: Kommt im Issuer (oder einem anderen Dienst) ein Schritt dazu, prüfen, ob Factory und
  Story ihn auch brauchen.
- **Inverse Seiten.** Foundry trägt neue Objekte offenbar schon selbst in die Collection der Gegenseite ein (Versuch:
  2 statt 1, im Foundry-Code nicht nachgesehen). Unsere Factories taten es zusätzlich, also stand jede Teilnahme
  doppelt in `course.enrollments`. Jetzt mit `contains()`-Prüfung (`erklaerungen/19`, Abschnitt 4).
- **`load-fixtures` löscht die lokale Datenbank**, auch selbst angelegte Konten (`entwicklung.md`). Die Story legt
  deshalb Testkonten an.
- **Zufall:** Faker liefert bei jedem Lauf andere Namen. Die Tests geben den „Faker seed“ aus; damit lässt sich ein
  Lauf wiederholen, wenn ein Test nur manchmal fehlschlägt.

## 6. Alternativen

- **DoctrineFixturesBundle** (Klassen mit `load(ObjectManager $manager)`, Befehl `doctrine:fixtures:load`): der
  klassische Weg, Objekte von Hand mit `new` und `persist()`. Die Tests hätten dann eigene Hilfsmethoden. Foundry gibt
  uns einen Bauplan für Fixtures und Tests zugleich.
- **SQL-Dump** mit Beispieldaten: schnell geladen, bricht aber bei jeder Migration und zeigt nicht, wie die Daten
  zustande kommen.
- **Gar keine Fixtures**, alles über die Oberfläche eintragen: realistisch, aber für einen Rundgang mit Kurs, Absagen
  und Honorarnoten zu langsam.
