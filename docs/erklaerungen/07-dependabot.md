# 07 – Dependabot: automatische Updates von Abhängigkeiten

Datei: `.github/dependabot.yaml`.
Referenzen: GitHub-Doku *About Dependabot version updates*, *Dependabot options reference*, *About Dependabot on
GitHub Actions runners* (docs.github.com, geprüft 2026-11-11). Repo-Einstellungen per GitHub-API abgefragt.

## Konzepte

### Wozu?

Das Projekt hängt von fremdem Code ab: Composer-Pakete (`composer.lock`), Docker-Basis-Images (`Dockerfile`,
`compose.yaml`) und GitHub Actions (`ci.yaml`). Alle bekommen laufend neue Versionen, darunter Sicherheitsfixes.
Ohne Hilfe merkt man das nur, wenn man selbst nachsieht (`composer outdated`, `composer audit`). Dependabot ist ein
in GitHub eingebauter Dienst, der das automatisch prüft und für jedes Update einen **Pull Request** anlegt.

Beispiel aus diesem Projekt: Am 2026-11-04 erschien Symfony 8.1.11 mit Fixes für 10 Sicherheitshinweise. Wir haben
es am 2026-11-11 bei `composer audit` bemerkt, weil Dependabot wegen der CI-Störung keine PRs anlegen konnte.

### Drei Funktionen, die man auseinanderhalten muss

| Funktion | Was sie tut | Bei uns |
|---|---|---|
| **Dependabot alerts** | Warnt im Tab „Security“, wenn eine Version in der Lock-Datei eine bekannte Schwachstelle hat | **aus** (API `vulnerability-alerts`: 404) |
| **Dependabot security updates** | Legt bei so einer Warnung sofort einen PR mit der reparierten Version an | **aus** (API `automated-security-fixes`: `enabled: false`) |
| **Dependabot version updates** | Prüft nach Zeitplan auf *jede* neue Version, auch ohne Schwachstelle | **an**, über `.github/dependabot.yaml` |

Version updates kommen nach Zeitplan und mit Wartezeit (siehe `cooldown`). Für Security updates gilt diese
Wartezeit laut Doku nicht: „This default cooldown does not apply to security updates.“

### Wie ein Update bei uns abläuft

```
Dependabot (wöchentlich)  ──►  findet neue Version, z. B. symfony/* 8.1.11
        │
        ▼
Branch dependabot/composer/... + Pull Request „chore: Bump symfony/… “
        │
        ▼
ci.yaml läuft (Ereignis pull_request) ──► nur Job „Tests“, kein Build/Deploy
        │
        ▼
Du prüfst den PR (Changelog, Tests grün) und mergst ──► Push auf main ──► Tests → Build → Deploy
```

Dependabot ändert nie selbst etwas auf `main`. Ein Update geht erst live, wenn du den PR mergst.

### Dependabot läuft selbst auf GitHub Actions

Laut Doku erzeugt GitHub die Dependabot-PRs in eigenen, „dynamischen“ Workflows auf Actions-Runnern. Sie stehen
nicht in `.github/workflows/`, tauchen aber in der Liste der Actions-Läufe auf („Dependabot Updates“). Auf Standard-
Runnern zählen sie nicht zu den inkludierten Actions-Minuten. Darum scheitern bei einer Actions-Störung auch die
Dependabot-Läufe, wie seit 2026-11-09 bei uns.

## `.github/dependabot.yaml` Zeile für Zeile

```yaml
version: 2                          # Format der Konfigurationsdatei, derzeit immer 2
updates:                            # ein Eintrag pro Paketsystem
  - package-ecosystem: docker       # Image-Tags in Dockerfile/compose (z. B. dunglas/frankenphp, postgres)
    directory: /                    # wo die Manifest-Dateien liegen (Repo-Wurzel)
    schedule:
      interval: weekly              # möglich: daily, weekly, monthly, quarterly, semiannually, yearly, cron
    commit-message:
      prefix: "chore"               # Präfix für Commit-Message und PR-Titel
    cooldown:
      default-days: 7               # neue Versionen erst nach 7 Tagen vorschlagen (ohne Angabe: 3)

  - package-ecosystem: composer     # composer.json / composer.lock
    ...                             # gleiche Einstellungen

  - package-ecosystem: github-actions   # uses: …@v7 in .github/workflows/
    ...
```

**Warum `cooldown: 7`?** Eine frisch veröffentlichte Version ist das größte Risiko: Fehler werden oft in den ersten
Tagen gefunden, und kompromittierte Pakete (Supply-Chain-Angriff) fallen meist schnell auf und werden zurückgezogen.
Sieben Tage Wartezeit filtern beides heraus. Nachteil: Auch Sicherheitsfixes kommen über version updates erst nach
sieben Tagen. Genau dafür wären die security updates da (siehe Checkliste).

**Was Dependabot nicht abdeckt:**
- `importmap.php` (Bootstrap-JavaScript, Popper): kein unterstütztes Paketsystem. Prüfen mit
  `bin/console importmap:outdated` und `importmap:audit`.
- Die Dart-Sass-Version, die das Sass-Bundle intern pinnt (kommt mit Updates des Bundles).
- Pakete im Server-Betriebssystem: übernimmt `unattended-upgrades` auf dem VPS.

## Alternativen

| Alternative | Warum (noch) nicht |
|---|---|
| **Renovate** (Open-Source-Bot) | Mehr Optionen, z. B. Updates bündeln oder automatisch mergen. Muss als GitHub-App installiert werden; für ein kleines Projekt bringt Dependabot genug |
| **Nur `composer audit` in der CI** | Meldet Schwachstellen nur bei einem Push, nicht wenn wochenlang nichts committet wird. Ergänzt Dependabot, ersetzt es nicht (steht als Kandidat im Projektstand) |
| **Automatisches Mergen** von Patch-Updates | Spart Klicks, aber dann geht ungeprüfter fremder Code automatisch live. Erst sinnvoll, wenn die Tests mehr abdecken |
| **Manuell** (`composer outdated`) | Wird erfahrungsgemäß vergessen |

## Sicherheit und Best Practices – Checkliste

| Punkt | Stand |
|---|---|
| Version updates für alle Paketsysteme | ✅ Docker, Composer, Actions, wöchentlich |
| Wartezeit gegen frisch kompromittierte Versionen | ✅ `cooldown: 7` |
| PRs laufen durch die Tests, deployen aber nicht | ✅ `if:` in `ci.yaml` |
| **Dependabot alerts** aktiviert | ❌ aus. Empfehlung: einschalten (Settings → Code security). Kostet nichts, ändert nichts am Code |
| **Dependabot security updates** aktiviert | ❌ aus. Empfehlung: einschalten; Sicherheitsfixes kommen dann ohne Wartezeit |
| Importmap-Pakete im Blick | ⚠️ manuell (`importmap:outdated`, `importmap:audit`) |
| PRs nicht blind mergen | Changelog lesen, besonders bei Major-Versionen und Docker-Images (Datenbank!) |
