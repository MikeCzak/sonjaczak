# Phase 2: Rollen und Zugriff (Entwurf)

Stand: 2026-11-13, **freigegeben**. Antworten aus Abschnitt 9 eingearbeitet (Passkeys in Abschnitt 5, Sessions in
Abschnitt 6), Nachfragen zu Cookies und Android in Abschnitt 10. Umsetzung: `docs/planung/umsetzung-admin.md`.

Grundlage: `anforderungen.md` A (Rollen `super_admin`, `admin`, `student`), `datenmodell.md` (freigegeben 2026-11-13).
Quellen: Symfony-Doku „Security“ (symfony.com/doc/current/security.html, abgerufen 2026-11-13) und der installierte Code
in `vendor/`. `symfony/security-bundle` ist noch **nicht** installiert. Schon da sind als Abhängigkeiten
`symfony/security-core` 8.1.11, `symfony/security-csrf` 8.1.0, `symfony/password-hasher` 8.1.0 und
`symfony/rate-limiter` 8.1.11 (laut `composer.lock`).

## 1. Überblick

| Rolle | Wer | Darf |
|---|---|---|
| `ROLE_ADMIN` | Sonja | Den ganzen Admin-Bereich: Kurse, Schüler:innen, Einheiten, Honorarnoten, Zahlungen, Einstellungen |
| `ROLE_SUPER_ADMIN` | Mike | Alles wie `ROLE_ADMIN`, dazu Benutzerverwaltung; sieht alle Daten (Antwort 1) |
| `ROLE_STUDENT` | Schüler:innen, **später** | Nur die eigenen Einheiten und Honorarnoten (Abschnitt 6) |
| – | Besucher:innen | Öffentliche Seiten; weiterhin ohne Cookie und ohne Session |

Rollenhierarchie in `config/packages/security.yaml`:

```yaml
role_hierarchy:
    ROLE_ADMIN: ROLE_USER
    ROLE_SUPER_ADMIN: ROLE_ADMIN
    ROLE_STUDENT: ROLE_USER
```

`ROLE_SUPER_ADMIN` bekommt damit automatisch `ROLE_ADMIN` und `ROLE_USER`. In der Datenbank steht bei Mike nur
`["ROLE_SUPER_ADMIN"]`.

**Fallstrick Hierarchie:** Sie wirkt nur bei `isGranted()`/`is_granted()`/`#[IsGranted]`, nicht bei
`$user->getRoles()`. Ein `in_array('ROLE_ADMIN', $user->getRoles())` wäre für Mike falsch, weil dort nur
`ROLE_SUPER_ADMIN` steht. Deshalb nie `getRoles()` für Prüfungen verwenden (Symfony-Doku, Abschnitt „Hierarchical
Roles“). Die Hierarchie steht in der Konfiguration, nicht in der Datenbank; dynamische Rollen bräuchten einen Voter.

## 2. Drei Ebenen der Prüfung

Symfony prüft Zugriff an drei Stellen. Jede hat ihre Aufgabe, und die typischen Fehler entstehen, wenn man nur eine
benutzt.

1. **`access_control` (URL-Ebene, in `security.yaml`):** grobes Netz. Alles unter `/admin` braucht `ROLE_ADMIN`.
   Vergisst man an einem neuen Controller die Prüfung, greift das Netz trotzdem.
2. **`#[IsGranted]` am Controller oder an der Action:** die Rolle oder das Recht für genau diese Aktion, direkt am Code
   lesbar (`#[IsGranted('ROLE_SUPER_ADMIN')]` an der Benutzerverwaltung).
3. **Voter (Objekt-Ebene):** Darf **dieser** User das mit **diesem** Objekt? Etwa „diese Schülerin löschen“ (nur
   ohne Honorarnoten) oder später „diese Honorarnote ansehen“ (nur die eigene). Aufruf über
   `#[IsGranted('STUDENT_DELETE', 'student')]` oder `$this->denyAccessUnlessGranted(...)`, in Twig
   `is_granted('STUDENT_DELETE', student)` zum Ein- und Ausblenden von Buttons.

**Was nicht in einen Voter gehört:** Regeln, die für jeden gelten, egal wer angemeldet ist. „Eine Honorarnote ist nach
dem Erstellen unveränderlich“ ist eine Eigenschaft der Entity: Invoice bekommt keine Setter für Betrag und Empfänger.
Der Voter blendet den Button aus, die Entity verhindert die Änderung. Ein Voter ließe sich umgehen, etwa durch einen
Konsolenbefehl oder einen vergessenen `denyAccessUnlessGranted()`. Die Entity nicht.

**`access_control`-Fallstrick:** Es gilt nur die **erste** passende Regel. Die speziellere Regel (`^/admin/login`)
muss vor der allgemeinen (`^/admin`) stehen.

```yaml
access_control:
    - { path: ^/admin/login, roles: PUBLIC_ACCESS }
    - { path: ^/admin/benutzer, roles: ROLE_SUPER_ADMIN }
    - { path: ^/admin, roles: ROLE_ADMIN }
```

## 3. Firewall: Admin mit Session, öffentliche Seiten ohne

Die öffentlichen Seiten setzen heute kein Cookie, und ein Test prüft das. Das soll so bleiben: kein Cookie-Hinweis
nötig, und die Datenschutzerklärung stimmt weiter. Deshalb gibt es **nur eine Firewall für `/admin`**:

```yaml
firewalls:
    dev:
        pattern: ^/(_profiler|_wdt|assets)/
        security: false
    admin:
        pattern: ^/admin
        lazy: true
        provider: app_user_provider
        form_login:
            login_path: admin_login
            check_path: admin_login
            enable_csrf: true
        login_throttling: ~
        logout:
            path: admin_logout
```

- Anfragen außerhalb von `/admin` passen zu keiner Firewall. Dort gibt es keine Anmeldung, keine Session, kein
  Cookie. **Konsequenz:** Auf öffentlichen Seiten weiß Symfony nicht, dass Sonja angemeldet ist, es gibt also keinen
  Link „Zum Admin“ im öffentlichen Header. Sonja nutzt ein Lesezeichen auf `/admin`. Die Symfony-Doku sagt dazu:
  Alles, was den angemeldeten User kennen soll, muss unter derselben Firewall liegen.
- **Zusätzlich das Session-Cookie auf `/admin` beschränken** (`framework.session.cookie_path: /admin`, Option in
  `vendor/symfony/framework-bundle/DependencyInjection/Configuration.php`). Der Browser schickt es dann gar nicht an
  öffentliche Seiten, auch nicht nach einem Login.
- `lazy: true` startet die Session erst, wenn tatsächlich ein Recht geprüft wird. Für die Login-Seite selbst heißt das:
  Die Session entsteht erst beim Absenden.
- **CSRF beim Login:** Die Token-ID `authenticate` steht bereits in `config/packages/csrf.yaml` unter
  `stateless_token_ids` (vom Rezept angelegt). Login und Logout sind damit gegen fremd ausgelöste Anfragen geschützt.
- **`login_throttling`:** Standard laut Doku 5 Fehlversuche pro Minute je Kombination aus IP und Benutzername, 25 je IP.
  Braucht `symfony/rate-limiter` (da). Gegen Passwort-Raten genügt das bei zwei Konten; dazu Passkeys (Abschnitt 5).

URLs bleiben unter `/admin` (nicht übersetzt, nicht verlinkt, `noindex`), alle Texte im Admin trotzdem über
Übersetzungsschlüssel (Domain `admin`, Antwort 6).

## 4. User und Passwörter

- **Entity `User`** wie im Datenmodell: `email` (Login, eindeutig), `password` (Hash), `roles` (JSON). Erzeugt mit
  `make:user` (Maker-Bundle als `require-dev`); `getRoles()` fügt dort immer `ROLE_USER` hinzu.
- **Passwort-Hash:** `algorithm: auto`. Im Container ist das **bcrypt mit Kostenfaktor 13**:
  `PasswordHasherFactory` probiert bei `auto` zuerst `native`, und `NativePasswordHasher` nimmt `PASSWORD_BCRYPT`
  mit `cost` 13 (`vendor/symfony/password-hasher/Hasher/`). Ändert Symfony das später, rechnet der
  `MigratingPasswordHasher` alte Hashes beim nächsten Login um (`PasswordUpgraderInterface` am Repository, erzeugt
  `make:user` mit).
  - **Fallstrick bcrypt:** bcrypt selbst wertet nur die ersten 72 Byte eines Passworts aus. Symfony fängt das ab:
    Längere Passwörter werden vorher mit SHA-512 gehasht (`NativePasswordHasher::hash()`), es zählt also das ganze
    Passwort. Wichtig nur, falls Hashes je außerhalb von Symfony geprüft werden (direkt mit `password_verify()`):
    Das würde bei langen Passwörtern scheitern.
- **Passwortregeln:** `#[Assert\PasswordStrength]` (Standard: mindestens „medium“) und eine Mindestlänge von 12
  Zeichen. `NotCompromisedPassword` würde gegen haveibeenpwned.com prüfen (es geht nur ein Teil des SHA-1-Hashes
  hinaus). Entschieden: nicht verwenden (Antwort 3), kein externer Dienst beim Passwortsetzen.
- **Login-Adresse:** `User.email` ist der Benutzername beim Login und die Adresse für den Link „Passwort vergessen“.
  Sonja meldet sich mit `music@sonjaczak.com` an, Mike mit seiner eigenen Adresse (steht nur in der Datenbank, nicht im
  Repo). Die Erinnerungs-Mails zu Honorarnoten gehen davon unabhängig an die Adresse in den Einstellungen, also an
  `music@sonjaczak.com`, nie an Schüler:innen (Antwort 7).

### 4.1 Wer legt welche Konten an?

Hier gab es ein Missverständnis (deine Zusatzfrage). „Schüler:in anlegen“ und „Konto anlegen“ sind zwei verschiedene
Dinge:

- **Schüler:innen** (Entity `Student`, mit Adresse, Geburtsdatum, Elternteil) legt Sonja **in der App** an, über die
  Admin-Masken. Ein Konto zum Anmelden haben sie dadurch nicht. Das ist der Normalfall und hat mit der Konsole nichts
  zu tun.
- **Konten von Sonja und Mike** (Entity `User` mit `ROLE_ADMIN`/`ROLE_SUPER_ADMIN`): Das allererste Konto kann nicht
  über die App entstehen, weil sich ohne Konto niemand anmelden kann, um es anzulegen (Henne-Ei-Problem). Dafür, und
  nur dafür, gibt es einen Konsolenbefehl `app:user:create`. Er läuft einmal in Produktion per `docker exec` am Server
  (vorher abgesprochen) und dann nur im Notfall wieder. Das Passwort wird verdeckt abgefragt und landet nicht in der
  Shell-History. Alternativ: Mike legt nur sein eigenes Konto per Konsole an und Sonjas danach in der
  Benutzerverwaltung der App.
- **Konten für Schüler:innen** (später, `ROLE_STUDENT`): Sonja schaltet sie in der App frei. Die Person bekommt einen
  Einladungslink per Mail und setzt ihr Passwort selbst. Technisch derselbe Mechanismus wie „Passwort vergessen“.

### 4.2 Passwort vergessen

Per Mail-Link (Antwort 4) mit `symfonycasts/reset-password-bundle` (laut Packagist v1.26.1 vom 2026-10-30, verlangt
`symfony/http-kernel ^8.0`, passt also zu Symfony 8.1). Der Ablauf:

1. Auf der Login-Seite „Passwort vergessen“, E-Mail-Adresse eingeben.
2. Die App antwortet **immer** „Falls es ein Konto gibt, kommt eine Mail“. Sonst könnte man ausprobieren, welche
   Adressen ein Konto haben.
3. Die Mail enthält einen Link mit einem Token, das nur einmal gilt und nach kurzer Zeit abläuft. In der Datenbank
   steht nur ein Hash des Tokens (Tabelle `reset_password_request`). Wer die Datenbank liest, kann damit also kein
   Passwort zurücksetzen.
4. Neues Passwort setzen (gleiche Regeln wie oben), danach Login.

Versand über den bestehenden Mailer (Absender `anfrage@sonjaczak.com`). Die genauen Standardwerte (Gültigkeit,
Wartezeit zwischen zwei Anfragen) prüfe ich beim Einbau im Code des Bundles.

## 5. Passkeys (Anmeldung mit Fingerabdruck)

Antwort 2: Sonja soll sich mit dem Fingerabdruck am Laptop anmelden. Das geht mit **Passkeys** (Standard WebAuthn).

**Wie es funktioniert:** Beim Einrichten erzeugt Sonjas Mac ein Schlüsselpaar. Der private Schlüssel bleibt im Gerät
(bzw. im iCloud-Schlüsselbund), der Server speichert nur den öffentlichen. Beim Login schickt der Server eine
Zufallszahl, der Mac unterschreibt sie nach Touch ID mit dem privaten Schlüssel, der Server prüft die Unterschrift.
Es geht nie ein Geheimnis über die Leitung, das man abfangen könnte.

**Warum das besser ist als Passwort + Code-App:**

- Zwei Faktoren in einem Schritt: Besitz (das Gerät) und Fingerabdruck. Das ist mindestens so stark wie Passwort + TOTP
  und für Sonja nur ein Fingertipp.
- Schutz vor Phishing: Ein Passkey gilt nur für `sonjaczak.com`. Auf einer nachgemachten Seite funktioniert er nicht,
  ein Passwort würde man dort eintippen.
- Mit dem iCloud-Schlüsselbund ist der Passkey auch auf Sonjas iPhone verfügbar. Ein verlorener Laptop sperrt sie also
  nicht aus.

**Umsetzung:** Symfony selbst bringt keine Passkey-Anmeldung mit (Security-Doku, abgerufen 2026-11-13). Üblich ist
`web-auth/webauthn-symfony-bundle` (Spomky-Labs). Laut Packagist ist 5.3.12 vom 2026-11-03 aktuell und verlangt
`symfony/security-bundle ^6.4|^7.0|^8.0`. Es braucht eine zusätzliche Tabelle für die öffentlichen Schlüssel (je
User beliebig viele Passkeys, z. B. Laptop und Handy) und etwas JavaScript für den Browser-Dialog. Die Doku des
Bundles ist an einigen Stellen dünn (Abschnitt „Registration“ zur Firewall: „TO BE WRITTEN“). Konfiguration und
Datenmodell prüfe ich beim Einbau im Code des Bundles, nicht aus der Doku allein.

**Vorschlag für den Ablauf:**

- Login-Seite: Knopf „Mit Fingerabdruck anmelden“ (Passkey) und darunter E-Mail + Passwort.
- Das Passwort bleibt als Rückfallweg (anderes Gerät, Passkey gelöscht) und für das Einrichten des ersten Passkeys:
  einmal mit Passwort anmelden, dann in „Mein Konto“ „Passkey hinzufügen“, Touch ID.
- Wer das Passwort vergessen hat, setzt es per Mail-Link zurück (4.2).
- **Fallstrick:** Solange das Passwort funktioniert, ist das Konto nur so sicher wie das Passwort plus Postfach. Der
  Passkey macht den Login bequemer und phishingsicher, schließt die Passwort-Tür aber nicht. Möglich wäre später:
  Passwort-Login für Konten mit Passkey abschalten. Für den Start schlage ich vor, beides zu erlauben, mit starkem
  Passwort und `login_throttling`.
- Lokal gilt ein Passkey für `localhost`, nicht für `sonjaczak.com`. Zum Testen richtet man also lokal einen eigenen
  ein; in den automatischen Tests wird der Passkey-Teil nicht im Browser geprüft, sondern nur die Konfiguration.

Falls ein Gerät keine Passkeys kann (sehr alter Browser), bleibt das Passwort. TOTP brauchen wir damit nicht.

**Reihenfolge:** zuerst Login mit Passwort, Sessions und „Passwort vergessen“. Passkeys als eigener Schritt
unmittelbar danach, noch bevor echte Daten eingetragen werden.

## 6. Sessions

### Was hat die Session mit der Datenbank zu tun?

Deine Frage aus Antwort 5. Eine Session besteht aus zwei Teilen:

1. Im **Browser** liegt nur ein Cookie mit einer zufälligen ID, z. B. `PHPSESSID=k3j9…`. Darin stehen keine Daten.
2. Auf dem **Server** liegt zu dieser ID ein kleiner Datensatz: wer angemeldet ist (das Security-Token), das
   CSRF-Token und so weiter.

Bei jeder Anfrage schickt der Browser die ID mit, und Symfony holt den passenden Datensatz. **Wo** der Datensatz
liegt, ist austauschbar: PHP nimmt standardmäßig eine Datei pro Session im Container (`/tmp` o. Ä.). Genauso gut kann
es eine Zeile in einer Tabelle sein, ein Eintrag in Redis oder Memcached. Session und Datenbank sind also
tatsächlich getrennte Dinge. Die Datenbank wäre nur der **Ablageort**, so wie ein Ordner auf der Platte.

**Warum der Ablageort hier wichtig ist:** Bei jedem Deploy ersetzt Docker den PHP-Container durch einen neuen, und
Dateien darin sind dann weg. Mit Session-Dateien im Container wäre Sonja nach jedem Push abgemeldet, mitten im
Unterrichtstag, und eine gerade getippte Eingabe im Kursraster ginge verloren. Die Datenbank läuft beim Deploy
weiter (Rollback-Test im Projektstand: nur php wird neu gestartet), ihre Daten liegen in einem Volume.

**Vorschlag: Sessions in PostgreSQL** mit `PdoSessionHandler` aus `symfony/http-foundation`. Die Tabelle
(`sessions`: ID, Daten, Ablaufzeit) kommt über eine normale Doctrine-Migration
(`PdoSessionHandlerSchemaListener` in `vendor/symfony/doctrine-bridge/SchemaListener/` meldet sie dem Schema, also
erzeugt `doctrine:migrations:diff` sie mit). Kein zusätzlicher Container, kein zusätzlicher RAM, und die
Datenbank-Backups enthalten sie gleich mit (unkritisch, Sessions sind ohnehin kurzlebig).
Alternativen:

- Dateien in einem Docker-Volume (`var/sessions`): übersteht Deploys auch, ist aber noch ein Volume.
- Redis: eigener Container, für zwei Konten unnötig (RAM).

### Dauer

Antwort 5: angemeldet bleiben über den Unterrichtstag. Vorschlag:

- **Abmeldung nach 8 Stunden ohne Aktivität** (`framework.session.gc_maxlifetime: 28800`). Jede Anfrage verschiebt die
  Ablaufzeit nach hinten: `PdoSessionHandler` speichert pro Session „gültig bis“ und schreibt sie bei jedem Zugriff
  neu (`vendor/symfony/http-foundation/Session/Storage/Handler/PdoSessionHandler.php`, `updateTimestamp()`).
- **Cookie gilt höchstens 12 Stunden** (`cookie_lifetime: 43200`). Damit übersteht die Anmeldung auch ein Beenden
  von Safari am Nachmittag, ist aber am nächsten Morgen sicher weg. Ohne diese Angabe verschwände das Cookie beim
  Schließen des Browsers.
- Kein Remember-Me: Mit 8/12 Stunden und Passkey (ein Fingertipp am nächsten Tag) wäre es kaum bequemer, aber ein
  langlebiges Cookie mehr.

## 7. Später: Zugang für Schüler:innen

Nicht Teil der ersten Umsetzung, aber das Modell soll es nicht verbauen.

- Login nur für Personen, die Sonja freischaltet (kein Registrieren). Sonja legt den User an, die Person setzt ihr
  Passwort über einen Einladungslink (dann braucht es doch einen Mail-Link mit Ablaufzeit).
- `StudentVoter`, `LessonVoter`, `InvoiceVoter` mit `…_VIEW`: erlaubt für `ROLE_ADMIN` oder wenn das Objekt zur
  eigenen Schülerin gehört (`$lesson->getEnrollment()->getStudent()->getUser() === $user`).
- **Offene Frage für später:** Bei Kindern bekäme wohl der Elternteil den Login, nicht das Kind. Dann braucht auch
  `Guardian` eine optionale Beziehung zu `User`, und die Voter prüfen „eigene Schülerin oder eigenes Kind“. Das ist eine
  zusätzliche Spalte, kein Umbau. Entscheiden wir, wenn der Schüler:innen-Zugang drankommt.

## 8. Voter für den Admin-Bereich

Für Sonja und Mike genügen meist Rollen. Voter braucht es dort, wo die Antwort vom Objekt abhängt:

| Voter | Attribut | Erlaubt, wenn |
|---|---|---|
| `StudentVoter` | `STUDENT_DELETE` | `ROLE_ADMIN` und keine Honorarnoten (Aufbewahrungspflicht) |
| `EnrollmentVoter` | `ENROLLMENT_DELETE` | `ROLE_ADMIN` und weder Honorarnote noch gehaltene Einheit |
| `InvoiceVoter` | `INVOICE_CREDIT` | `ROLE_ADMIN` und die Honorarnote ist selbst keine Gutschrift |
| `CourseBreakVoter` | `COURSE_BREAK_DELETE` | `ROLE_ADMIN` (Ferien löschen erzeugt wieder Einheiten, kein weiterer Zustand nötig; Voter erst, wenn eine Regel dazukommt) |

- **Aufbau:** Klasse erbt von `Symfony\Component\Security\Core\Authorization\Voter\Voter`, implementiert
  `supports(string $attribute, mixed $subject): bool` und
  `voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool`
  (Signatur aus `vendor/symfony/security-core/Authorization/Voter/Voter.php`, 8.1.11). Der Parameter `$vote` ist neu:
  Darüber kann ein Voter eine Begründung mitgeben („Schülerin hat Honorarnoten“), die wir dann als Hinweis anzeigen.
- **Attribute als Konstanten** am Voter (`StudentVoter::DELETE = 'STUDENT_DELETE'`), nicht als lose Strings. PHPStan
  findet dann Tippfehler.
- **Entscheidungsstrategie:** Standard ist `affirmative`: ein einziges „Ja“ genügt. Ein Voter, der ein Attribut nicht
  kennt, enthält sich (`supports()` liefert `false`). **Fallstrick:** Ein zu großzügiges `supports()` (etwa
  `return true`) lässt einen Voter über fremde Attribute abstimmen und kann so Zugriff gewähren.

## 9. Fragen (beantwortet 2026-11-13)

1. **Mike und die Daten der Schüler:innen:** Soll `ROLE_SUPER_ADMIN` alles sehen, was Sonja sieht (Hierarchie wie oben),
   oder nur Technik (Benutzer, Einstellungen) ohne Personendaten? Technisch hast du über die Datenbank ohnehin Zugriff;
   für die DSGVO zählt aber, wer im Alltag zugreift. Vorschlag: volle Sicht, weil du Sonja beim Eintragen hilfst. - ja, volle Sicht
   → Hierarchie wie in Abschnitt 1.
2. **Zwei-Faktor-Anmeldung** (TOTP-App, z. B. über `scheb/2fa-totp`): jetzt, später oder gar nicht? Es geht um Daten
   von Kindern; dagegen steht ein zusätzlicher Schritt bei jedem Login für Sonja. Vorschlag: später, aber vor dem
   Schüler:innen-Zugang. Bis dahin starke Passwörter und `login_throttling`. - Können wir das mit Passkey über ihren Laptop-Fingerabdruck-Scan lösen? OTP ist mühsam, wäre aber auch möglich, falls Fingerabdruck nicht möglich. Dann aber auf jeden Fall ohne Session-Logout, sodass sie sich an einem Unterrichtstag nur einmal anmelden muss.
   → Ja, mit Passkeys (Abschnitt 5), TOTP nicht nötig. Sessions siehe Abschnitt 6.
3. **Prüfung gegen bekannte Passwort-Leaks** (`NotCompromisedPassword`, haveibeenpwned.com): ja oder nein? - nein
4. **Passwort vergessen:** vorerst nur per Konsolenbefehl durch Mike (Vorschlag), oder gleich ein Mail-Link? - Mail-Link
5. **Sessions:** in PostgreSQL (Vorschlag)? Abmeldung nach 2 Stunden ohne Aktivität, oder „Angemeldet bleiben“ für
   Sonja? - Angemeldet bleiben, wenn dann erst nach 8 Stunden abmelden, falls sie während dem Unterrichtstag keine Zeit hat, etwas einzutragen. Erkläre mir, was das mit PostgreSQL zu tun hat. Session und DB sind in meinem Kopf zwei vollkommen getrennte Dinge.
6. **Sprache des Admin-Bereichs:** Übersetzungsschlüssel in einer eigenen Domain `admin` (Vorschlag), nur Deutsch
   gepflegt. Einverstanden? - ja
   → Domain `admin` (`translations/admin.de.yaml`).
7. **E-Mail-Adressen für die Konten:** Sonja mit `music@sonjaczak.com`, du mit deiner eigenen? Die Erinnerungs-Mails
   (Honorarnoten) gehen an die Adresse aus den Einstellungen, nicht an das Login. - Ich verstehe die Frage nicht. Die Erinnerungsmail soll NUR an Sonja gehen, nicht an Schüler:innen. Also nur an music@sonjaczak.com.
   → Gemeint war: Mit welcher Adresse meldet sich wer an? Übernommen in Abschnitt 4: Sonja mit `music@`, du mit
   deiner eigenen; Erinnerungen nur an `music@`.

Was meinst du damit, dass ich Konten per Konsolenbefehl anlege? Sonja muss in der Lage sein, Schüler (User) in der App anzulegen. Oder sprichst du von etwas anderem?
→ Ja, von etwas anderem: nur vom allerersten Admin-Konto. Siehe Abschnitt 4.1.

## 10. Nachfragen nach der Freigabe (2026-11-13)

### Brauchen wir jetzt doch Cookies?

Ja, aber nur im Admin-Bereich und nur nach dem Anmelden. Ohne Cookie wüsste der Server bei der nächsten Anfrage nicht
mehr, dass Sonja angemeldet ist; HTTP selbst merkt sich nichts zwischen zwei Anfragen. Ein Cookie mit der Session-ID
ist der übliche Weg, das zu überbrücken. Die Alternativen (Anmeldedaten bei jeder Anfrage mitschicken, Token im
JavaScript-Speicher) sind für eine normale Web-Oberfläche umständlicher und nicht sicherer.

Was sich für wen ändert:

- **Besucher:innen der öffentlichen Seiten:** nichts. Keine Firewall, keine Session, kein Cookie (Abschnitt 3). Die
  bestehenden Tests (`assertResponseNotHasHeader('Set-Cookie')`) bleiben und werden um die Startseite ergänzt.
- **Login-Seite ohne Absenden:** kein Cookie. Das CSRF-Token für den Login ist zustandslos (`authenticate` in
  `config/packages/csrf.yaml`), die Firewall ist `lazy`.
- **Nach dem Anmelden:** ein Session-Cookie, gültig nur für Pfade unter `/admin` (`cookie_path`), `HttpOnly`
  (JavaScript kann es nicht lesen), `Secure` (nur über HTTPS), `SameSite=Lax`, höchstens 12 Stunden. Die Werte für
  `HttpOnly`, `Secure` und `SameSite` sind Symfonys Standard (`cookie_httponly: true`, `cookie_secure: auto`,
  `cookie_samesite: lax` in `vendor/symfony/framework-bundle/DependencyInjection/Configuration.php`).
- **„Passwort vergessen“** liegt ebenfalls unter `/admin`, damit eine Session, die das Bundle dabei braucht, denselben
  Regeln folgt.

**Rechtlich:** Ein Cookie, das für einen vom Nutzer ausdrücklich gewünschten Dienst unbedingt nötig ist (hier: der
Login), braucht keine Einwilligung und keinen Cookie-Banner. Die Rechtsgrundlage in Österreich (TKG 2021, Umsetzung
von Art. 5 Abs. 3 der ePrivacy-Richtlinie) prüfe ich vor der Textänderung in der Primärquelle. **Die
Datenschutzerklärung muss angepasst werden:** Sie sagt derzeit „Diese Website setzt keine Cookies“. Neu etwa:
„Die öffentlichen Seiten setzen keine Cookies. Nur der passwortgeschützte Verwaltungsbereich setzt nach der Anmeldung
ein technisch notwendiges Session-Cookie.“ Das kommt mit dem Login-Schritt.

### Passkey und Android-Handy

Sonja hat einen Mac und ein Samsung-Handy. Passkeys aus dem iCloud-Schlüsselbund (Mac/Safari) kommen nicht auf
Android, umgekehrt auch nicht. Das ist kein Problem, weil ein User **mehrere Passkeys** haben kann (Abschnitt 5):

1. **Passkey auf dem Mac** (Touch ID, iCloud-Schlüsselbund).
2. **Passkey auf dem Samsung-Handy:** einmal am Handy anmelden (Passwort oder über den Mac, siehe 3.) und in „Mein
   Konto“ „Passkey hinzufügen“. Gespeichert wird er laut Google standardmäßig im Google-Passwortmanager; ab Android 14
   kann man in den Systemeinstellungen einen anderen Anbieter wählen. Voraussetzung ist Android 9 oder neuer.
   Entsperrt wird mit Fingerabdruck oder Bildschirmsperre des Handys.
3. **Handy als Schlüssel für einen fremden Rechner:** Chrome auf allen Plattformen unterstützt die geräteübergreifende
   Anmeldung. Der Rechner zeigt einen QR-Code, das Handy scannt ihn und bestätigt per Fingerabdruck.

Quelle: developers.google.com/identity/passkeys/supported-environments (abgerufen 2026-11-13). Ob Samsung Pass als
Anbieter funktioniert, sagt die Seite nicht; mit dem Google-Passwortmanager geht es auf jeden Fall.

**Für den Fall „später Passwort abschalten“** heißt das: Bevor wir den Passwort-Login für Sonjas Konto abschalten,
muss sie auf beiden Geräten einen Passkey haben, und „Mein Konto“ zeigt die Liste ihrer Passkeys (mit Gerät und
Datum der letzten Nutzung). Fällt ein Gerät aus, meldet sie sich mit dem anderen an. Fallen beide aus, setzt Mike das
Konto per Konsolenbefehl zurück. Der Mail-Link aus 4.2 dürfte dann **keinen** Passwort-Login mehr öffnen, sonst wäre
die Passkey-Pflicht über das Postfach umgehbar.
