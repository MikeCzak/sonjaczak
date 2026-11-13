# Einstellungen (C6)

Plan: `docs/planung/datenmodell.md` 3.9. Seite: `/admin/einstellungen` (für `ROLE_ADMIN`).

## Dateien

| Datei | Inhalt |
|---|---|
| `src/Entity/Settings.php`, `src/Repository/SettingsRepository.php` | Eine Zeile mit typisierten Spalten |
| `src/Migrations/Version20261113094819.php` | Tabelle `settings` |
| `src/Form/Admin/SettingsType.php`, `src/Controller/Admin/SettingsController.php`, `templates/admin/settings/` | Oberfläche, Upload der Unterschrift |
| `src/Theme/SiteSettings.php` | Liest das Theme jetzt aus der Datenbank |
| `tests/Admin/SettingsTest.php` | Tests |

## Eine Zeile, erzwungen über den Primärschlüssel

`Settings::$id` ist immer `1` und hat **kein** `GeneratedValue`. Eine zweite Zeile müsste ebenfalls die ID 1 haben, und
das verbietet der Primärschlüssel. `SettingsRepository::current()` liefert die Zeile oder, solange Sonja noch nie
gespeichert hat, ein neues Objekt mit Standardwerten (nicht gespeichert). Das erste Speichern legt die Zeile an. Die
Migration fügt deshalb keine Zeile ein, und auf dem Server muss nichts vorbereitet werden.

Alternative wäre eine Schlüssel-Wert-Tabelle (`name`, `value`): neue Einstellungen ohne Migration, aber alles ist ein
String, Typen und Validierung gehen verloren.

## Worker-Modus: nichts im Dienst merken

FrankenPHP läuft als Worker: Ein Dienst lebt über viele Anfragen. Würde `SiteSettings` das Theme beim ersten Aufruf
laden und sich merken, sähen Besucher:innen nach Sonjas Änderung weiter das alte Theme, bis der Worker neu startet.
Deshalb fragt `SettingsRepository::current()` jedes Mal (eine Abfrage per Primärschlüssel). Doctrines Identity Map
wird nach jeder Anfrage geleert (`Doctrine\Bundle\DoctrineBundle\Registry::reset()`, im Container mit `kernel.reset`
markiert: `vendor/doctrine/doctrine-bundle/config/dbal.php`). Aus demselben Grund bekommt `MakeUpRule` den Schwellwert als Argument statt im
Konstruktor.

## Öffentliche Seiten fragen jetzt die Datenbank

Bisher brauchten öffentliche Seiten keine Datenbank. Jetzt liest jede Seite das Theme. Fällt die Datenbank aus,
fängt `SiteSettings::theme()` den Fehler ab, schreibt ihn ins Log und nimmt das Standard-Theme. So bleibt die Website
erreichbar (nur die Formulare brauchen die Datenbank ohnehin nicht).

Erwogen und verworfen: das Theme im Symfony-Cache (`cache.app`) zu halten und beim Speichern zu löschen. Spart die
Abfrage, bringt aber eine zweite Stelle, die stimmen muss (Tests mit zurückgesetzter Datenbank, mehrere Worker). Bei
einer Abfrage per Primärschlüssel lohnt sich das nicht.

## Unterschrift als BLOB

- Upload als PNG, höchstens 1 MB (`Assert\Image` mit `mimeTypes: ['image/png']`; die Prüfung nutzt `getimagesize()`
  und braucht kein GD). PHPs Upload-Grenze im Container ist 2 MB (`upload_max_filesize`).
- Gespeichert als `BYTEA` in `settings.signature`, nicht als Datei: Sie ist damit im Datenbank-Backup enthalten, und es
  gibt keinen Ordner auf dem Server, der beim Deploy erhalten bleiben muss.
- **Fallstrick:** Doctrine liefert einen `BLOB` beim Laden als **Stream** (`resource`), nicht als String, und ein
  Stream lässt sich nur einmal lesen. `Settings::getSignature()` liest ihn beim ersten Zugriff und behält den String.
- Ausgeliefert nur im Admin-Bereich (`/admin/einstellungen/unterschrift`) mit `Cache-Control: private, no-store`.

## Validierung

- **IBAN:** `Assert\Iban` prüft Länderformat und Prüfziffer. Gespeichert ohne Leerzeichen, in Großbuchstaben.
- **BIC:** nur das Format per Regex. `Assert\Bic` würde auch den Ländercode prüfen, braucht dafür aber
  `symfony/intl` (Fehlermeldung beim ersten Test: „The Intl component is required to use the Bic constraint“). Das
  Paket bringt große Länder- und Sprachdaten mit; für eine Plausibilitätsprüfung habe ich es nicht installiert.

## Was sich dadurch geändert hat

- `app.theme` und `app.make_up_threshold` sind aus `config/services.yaml` entfernt. Die Slider-Bilder stehen weiter
  dort, bis die Verwaltung der Startseiten-Bilder (`HomeSlide`) gebaut ist.
- Neue Kurse und Schnupperstunden übernehmen die Standarddauer aus den Einstellungen.
- Absenderdaten sind Sonjas personenbezogene Daten: im Code leer, in der Story erfunden („Erika Musterfrau“, Beispiel-
  IBAN), echt nur in Produktion.
