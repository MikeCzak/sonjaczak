# sonjaczak.com

Website für Sonja Czak, Sängerin und Gesangslehrerin. Entwickelt von Mike (Webentwickler, Österreich,
langjährige Symfony-Erfahrung, aber erstes Projekt von Grund auf).

Aktueller Stand, Entscheidungen und offene Punkte: @docs/projektstand.md
Begriffe: `docs/begriffe.md` · Lokaler Entwicklungs-Workflow: `docs/entwicklung.md`

## Wie wir arbeiten

- **Sprache: Deutsch** – in Antworten, Commit-Messages und Doku. Code, Bezeichner und Kommentare auf Englisch.
- **Kein Vibe-Coding.** Mike will jeden Schritt verstehen. Erkläre bei jeder Entscheidung das *Warum* und
  nenne die realistischen Alternativen. Lieber kleine Schritte als große Diffs.
- **Erst planen, dann coden.** Bei allem, was über eine kleine Änderung hinausgeht: zuerst Plan vorstellen,
  auf Freigabe warten, dann umsetzen. Bei Unklarheiten nachfragen statt raten.
- **Lernschwerpunkte:** Datenbankdesign (Doctrine, Relationen, Migrationen), User-Management (Security,
  Rollen, Voter) und Übersetzungen. Hier besonders ausführlich erklären und auf Fallstricke hinweisen.
- **Versionen und technische Details nie aus dem Gedächtnis behaupten.** Vor jeder Versionsangabe,
  Konfigurationsoption oder API-Signatur gegen die offizielle Doku (symfony.com/doc, Doctrine-, Docker-,
  FrankenPHP-, GitHub-Docs) oder den installierten Code (`vendor/`, `composer.lock`) prüfen und die Quelle
  nennen. Mike prüft gegen Primärquellen.
- **Phase 1 (bis zum ersten Deploy):** Claude setzt zügig um und erklärt knapp. Neue Begriffe kommen nach
  `docs/begriffe.md`, Punkte zum späteren Nachlesen in `docs/projektstand.md`. Ausführliche Erklärungen holen
  wir nach dem ersten Deploy nach. Weiterhin vorher fragen: alles am Server, Secrets, Entscheidungen mit
  echten Alternativen.
- **Projektstand pflegen:** Wenn ein Schritt abgeschlossen oder eine Entscheidung getroffen ist,
  `docs/projektstand.md` aktualisieren und kurz sagen, was sich geändert hat.

## Tech-Stack

- PHP, Symfony 8.x (Start mit 8.1, Minor-Upgrades bis 8.4 LTS), Twig, Doctrine ORM, PostgreSQL 18 (Standard des Templates)
- Lokal und in Produktion Docker Compose, Basis ist das Template `dunglas/symfony-docker`
  (FrankenPHP/Caddy in einem Container, Service-Name `php`).
- Hauptmenü mit KnpMenuBundle (eigener `MenuBuilder`)
- CI/CD: GitHub Actions mit Stages Test → Build → Deploy; Images in ghcr.io (Tag = Commit-Hash);
  Deploy per SSH auf einen IONOS-VPS
- CSS-Framework und Symfony-UX-Bausteine sind noch offen (siehe Projektstand)

## Befehle

Alles läuft im Container, nie mit lokalem PHP/Composer:

```bash
docker compose build --pull --no-cache    # Images bauen
docker compose up --wait                  # starten
docker compose down --remove-orphans      # stoppen
docker compose exec php bin/console …     # Symfony-Konsole
docker compose exec php composer …        # Composer
```

Tests und statische Analyse (laufen genauso in der CI, siehe `.github/workflows/ci.yaml`):

```bash
docker compose exec php bin/phpunit                                          # Tests (Test-DB app_test)
docker compose exec php vendor/bin/phpstan analyse --memory-limit=512M       # PHPStan, Level max
docker compose exec php bin/console -e test doctrine:database:create         # Test-DB einmalig anlegen
```

<!-- TODO: Coding-Standard (php-cs-fixer) -->


## Harte Regeln

- **Produktionsserver hat ~850 MiB RAM, 1 vCore, 8,7 GB Platte.** Jede Entscheidung (Dienste, Worker,
  Caches, Images) muss das berücksichtigen. Keine zusätzlichen Container ohne Rückfrage.
- **Images werden nur in der CI gebaut, nie auf dem Server.**
- **Die Datenbank bekommt in Produktion keinen veröffentlichten Port.** Docker umgeht ufw bei veröffentlichten Ports.
- **Keine Secrets im Repo.** Lokal `.env.local`, in der CI GitHub Secrets, auf dem Server Umgebungsdateien
  außerhalb des Repos.
- Schema-Änderungen nur über Doctrine-Migrationen, nie `doctrine:schema:update --force`.
- Personenbezogene Daten (Schüler:innen, Rechnungsdaten) unterliegen der DSGVO: Zugriff nur für
  berechtigte Rollen, Backups einplanen, nichts davon in Logs oder Fixtures mit echten Daten.

## Übersetzungen

Standardsprache Deutsch, von Anfang an übersetzbar:

- Keine hartkodierten UI-Texte in Twig oder PHP: immer Übersetzungsschlüssel (`|trans`, `TranslatorInterface`).
- Schlüssel-Schema und Dateiformat werden beim ersten Template gemeinsam festgelegt und hier ergänzt.

## Funktionen

- **Home:** Willkommensseite mit Vollbild-Bildern und kurzer Vorstellung von Sonja.
- **Booking:**
  1. Sonja solo (Stagepiano + Gesang) für Trauungen, Taufen und ähnliche kleine Events. Vorerst nur ein
     Twig-Template, Inhalte folgen. Später dynamisches Kontaktformular: Songs aus einer Repertoireliste
     wählen → Preisschätzung, plus Eventdetails (Datum, Ort, Dauer).
  2. Sonja mit Band: großes Bild mit Hero-Text, kurze Beschreibung, Link zur Band-Website.
- **Lessons:** Infos zum Gesangsunterricht (Ort, Inhalte, Preise, Termine) und Kontaktformular mit Widget
  zur Auswahl eines freien Termins über die Backend-API `getAllLessonSlots`.
- **Admin-Bereich (mit Login und Rollen):** Sonja legt Unterrichtssemester und Termine an, verwaltet
  Schüler:innen mit persönlichen und Rechnungsdaten und erstellt daraus Honorarnoten. Details werden bei
  der App-Planung festgelegt.

## Reihenfolge

1. Phase 1: Eine leere Seite, die bei jedem `git push` automatisch auf sonjaczak.com live geht
   (Setup → Tests in der CI → Build → Deploy → DNS/HTTPS).
2. Phase 2: App-Planung (Anforderungen, Datenmodell als ER-Diagramm, Rollenkonzept), erst dann Features.
