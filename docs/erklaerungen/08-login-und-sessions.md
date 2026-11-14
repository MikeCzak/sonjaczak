# 08 – Login, Rollen und Sessions

Dateien: `config/packages/security.yaml`, `config/packages/framework.yaml` (Abschnitt `session`), `config/services.yaml`
(`PdoSessionHandler`), `src/Entity/User.php`, `src/Repository/UserRepository.php`,
`src/Controller/Admin/SecurityController.php`, `src/Command/CreateUserCommand.php`, `src/Security/PasswordRules.php`,
`templates/admin/`, Migrationen `Version20261113004345` (Konten) und `Version20261113004621` (Sessions).
Planung und Entscheidungen: `docs/planung/rollen.md`. Umgesetzt in den Schritten B1 und B2 (2026-11-13).
Referenzen: Symfony-Doku *Security* und *Sessions* (symfony.com/doc/current, abgerufen 2026-11-13) und der installierte
Code (Symfony 8.1.11 laut `composer.lock`).

## Konzepte

### Was beim Login passiert

1. Sonja ruft `/admin` auf. Die Firewall `admin` ist zuständig (Pfad passt auf `^/admin`), `access_control` verlangt
   `ROLE_ADMIN`. Sie ist nicht angemeldet, also leitet Symfony auf `/admin/login` um.
2. Der `SecurityController` zeigt nur das Formular. Verarbeitet wird das Absenden nicht von unserem Code, sondern vom
   Authenticator `form_login` der Firewall. Er läuft **vor** dem Controller, sobald ein POST auf `check_path` kommt.
3. `form_login` prüft das CSRF-Token, lädt den User über den Provider (`UserRepository::loadUserByIdentifier()`),
   vergleicht das Passwort mit dem Hash (`password_hashers`) und legt bei Erfolg ein Token in die Session. Danach
   leitet er auf `default_target_path` um (`/admin`).
4. Bei jeder weiteren Anfrage schickt der Browser das Session-Cookie mit. Symfony holt das Token aus der Session,
   lädt den User neu aus der Datenbank (`refreshUser`) und prüft die Rollen.
5. Schlägt der Login fehl, landet der Fehler in der Session und der Controller zeigt ihn über
   `AuthenticationUtils::getLastAuthenticationError()` an. Die Meldung ist immer dieselbe („Fehlerhafte
   Zugangsdaten“), egal ob die Adresse unbekannt oder das Passwort falsch ist. So kann niemand herausfinden, welche
   Adressen ein Konto haben. Die deutschen Texte liefert Symfony mit (`security.de.xlf`, Domain `security`).

### Warum nur eine Firewall für `/admin`

Öffentliche Seiten passen auf keine Firewall. Dort gibt es keine Anmeldung, keine Session und kein Cookie, und
`PublicPagesTest` prüft für jede Seite, dass kein `Set-Cookie` kommt. Die Kehrseite: Auf öffentlichen Seiten weiß
Symfony nicht, wer angemeldet ist (kein „Zum Admin“-Link). Sonja nutzt ein Lesezeichen auf `/admin`.

`lazy: true` heißt: Die Session wird erst gestartet, wenn ein Recht geprüft wird. Die Login-Seite selbst prüft kein
Recht (`PUBLIC_ACCESS`), und das CSRF-Token ist zustandslos (Token-ID `authenticate` in `config/packages/csrf.yaml`).
Das Formular erzeugt also noch kein Cookie, erst das erfolgreiche Anmelden (`LoginTest::testLoginPageSetsNoCookie`).

**Zustandsloses CSRF:** Statt eines geheimen Tokens in der Session prüft Symfony, ob die Anfrage von unserer eigenen
Seite kommt (Header `Origin` bzw. `Referer`). Im Formular steht deshalb nur ein Platzhalter (`csrf-token`). Für ein
Formular, das nur von Menschen im Browser abgeschickt wird, reicht das; ein `curl` ohne `Origin`-Header wird
abgelehnt.

### Drei Ebenen der Zugriffsprüfung

| Ebene | Wo | Beispiel |
|---|---|---|
| `access_control` | `security.yaml` | alles unter `/admin` braucht `ROLE_ADMIN`, `/admin/benutzer` `ROLE_SUPER_ADMIN` |
| `#[IsGranted]` | am Controller | `DashboardController` |
| Voter | eigene Klasse, objektabhängig | ab Schritt C1 |

`access_control` ist das Netz: Vergisst ein neuer Controller `#[IsGranted]`, greift es trotzdem. Der Test
`AdminRoutesRequireLoginTest` liest alle Routen unter `/admin` aus dem Router und prüft, dass jede ohne Login auf die
Login-Seite umleitet. Ein neuer Controller ist damit automatisch mitgeprüft.

**Fallstrick Reihenfolge:** In `access_control` gilt nur die **erste** passende Regel. `^/admin/login$` muss vor
`^/admin` stehen, sonst bräuchte die Login-Seite selbst schon `ROLE_ADMIN` (Endlosschleife).

**Fallstrick Rollenhierarchie:** Mike hat in der Datenbank nur `ROLE_SUPER_ADMIN`. `ROLE_ADMIN` bekommt er über
`role_hierarchy`, aber nur bei `isGranted()`. `in_array('ROLE_ADMIN', $user->getRoles())` wäre bei ihm `false`.

### Passwörter

- **Hash:** `auto` ist im Container bcrypt mit Kostenfaktor 13 (`PasswordHasherFactory` probiert zuerst `native`,
  `NativePasswordHasher` nimmt `PASSWORD_BCRYPT`, `cost` 13). In Tests ist der Faktor 4, damit sie schnell bleiben
  (`when@test` in `security.yaml`).
- **Rehash:** `UserRepository` implementiert `PasswordUpgraderInterface`. Ändert sich der empfohlene Algorithmus,
  speichert Symfony beim nächsten Login einen neuen Hash.
- **Session ohne Hash:** `User::__serialize()` legt statt des Hashes nur eine CRC32C-Prüfsumme in die Session. Symfony
  bemerkt damit trotzdem eine Passwortänderung und meldet andere Sitzungen ab (seit Symfony 7.3, von `make:user`
  erzeugt).
- **Regeln:** `PasswordRules`: mindestens 12 Zeichen und `PasswordStrength` (Standard „medium“). Keine Prüfung gegen
  Leak-Datenbanken (entschieden, `rollen.md` Frage 3).

### Groß- und Kleinschreibung der E-Mail-Adresse

PostgreSQL vergleicht Texte mit Groß- und Kleinschreibung: `Music@sonjaczak.com` ≠ `music@sonjaczak.com`. Ein
Provider mit `property: email` fände Sonja nicht, wenn sie die Adresse mit großem M tippt. Deshalb:

- `User::setEmail()` speichert immer klein und ohne Leerzeichen am Rand (`User::normalizeEmail()`).
- Der Provider hat **kein** `property`. Dann fragt Symfony das Repository über `UserLoaderInterface`
  (`vendor/symfony/doctrine-bridge/Security/User/EntityUserProvider.php`), und `loadUserByIdentifier()` normalisiert
  die Eingabe genauso.

### Tabelle `app_user` statt `user`

`user` ist in PostgreSQL ein reserviertes Wort. `make:user` setzt deshalb `` `user` `` mit Anführungszeichen, die
Doctrine in jede Abfrage einbauen muss; in eigenem SQL (`psql`, Backups) vergisst man sie leicht. `app_user` braucht
das nicht.

### Sessions in PostgreSQL

Zur Frage, was Session und Datenbank miteinander zu tun haben: `docs/planung/rollen.md` Abschnitt 6. Kurz: Die
Datenbank ist nur der Ablageort der Session-Daten. Dateien im Container wären nach jedem Deploy weg, die Datenbank
läuft weiter. Lokal geprüft: angemeldet, `docker compose up -d --force-recreate php`, weiterhin angemeldet.

- `PdoSessionHandler` nutzt Doctrines PDO-Verbindung mit Advisory Locks (Begründung unten im Abschnitt „Sessions und
  Doctrine teilen sich eine Verbindung“). Die Symfony-Doku zeigt `DATABASE_URL` als Argument; das war unser erster
  Stand und scheiterte in Tests.
- Die Tabelle `sessions` kam über `make:migration`: `PdoSessionHandlerSchemaListener` (symfony/doctrine-bridge)
  meldet sie dem Doctrine-Schema, wenn der Handler dieselbe Datenbank nutzt. Deshalb meldet auch
  `doctrine:schema:validate` keine „fremde“ Tabelle.
- **Ablauf nach 8 Stunden ohne Aktivität:** `gc_maxlifetime: 28800`. `PdoSessionHandler` speichert pro Session
  „gültig bis“ (`sess_lifetime`) und setzt den Wert bei jedem Zugriff neu (`updateTimestamp()`). Abgelaufene Zeilen
  gelten beim Lesen als leer und werden beim gelegentlichen Aufräumen gelöscht.
- **Cookie höchstens 12 Stunden:** `cookie_lifetime: 43200`. Ohne Angabe verschwände das Cookie beim Beenden des
  Browsers. Laut Symfony-Doku gehört die eigentliche Ablaufprüfung auf den Server (der Browser kann das Cookie
  verlängern), das übernimmt `gc_maxlifetime`.
- **Nur für `/admin`:** `cookie_path: /admin`. `HttpOnly`, `Secure` (bei HTTPS) und `SameSite=Lax` sind Symfonys
  Standard. Lokal mit `curl` geprüft:
  `Set-Cookie: PHPSESSID=…; Max-Age=43200; path=/admin; secure; httponly; samesite=lax`.
- **In Tests** ersetzt `framework.session.storage_factory_id: session.storage.factory.mock_file` die echte Ablage;
  das Cookie heißt dort `MOCKSESSID`.

### Logout

`POST /admin/logout` aus dem Formular im Kopf des Admin-Bereichs, mit CSRF-Token (`enable_csrf: true`, Token-ID
`logout`). Ein Link per GET wäre bequemer, aber dann könnte jede fremde Seite Sonja mit einem eingebetteten Bild
abmelden. Die Route im `SecurityController` wird nie erreicht: Der Logout-Listener der Firewall fängt den Pfad vorher ab.

### Login-Bremse

`login_throttling: ~` mit Standardwerten: 5 Fehlversuche pro Minute für jede Kombination aus IP und E-Mail-Adresse,
25 pro IP. Gespeichert im Cache (`cache.rate_limiter`), nicht in der Session.

### Das erste Konto

Ohne Konto kann sich niemand anmelden, um eines anzulegen. Dafür gibt es `app:user:create` (`rollen.md` 4.1). Das
Passwort wird verdeckt und zweimal abgefragt, nie als Argument übergeben: Argumente landen in der Shell-History und
sind für andere Prozesse sichtbar (`ps`). In Produktion: `docs/deployment.md` S7.

### Passwort vergessen (B3)

Dateien: `src/Controller/Admin/PasswordController.php`, `src/Security/PasswordLinkMailer.php`,
`src/Entity/ResetPasswordRequest.php`, `config/packages/reset_password.yaml`,
`templates/email/account/password_link.txt.twig`, Migration `Version20261113005058`. Paket
`symfonycasts/reset-password-bundle` 1.26.1; das Gerüst kam von `make:reset-password` und ist umgebaut (Routen unter
`/admin`, deutsche Texte, Mail als reiner Text, keine Leak-Prüfung, Adresse ohne Rücksicht auf Groß-/Kleinschreibung).

1. `/admin/passwort-vergessen`: Adresse eingeben. Die Antwort ist **immer** dieselbe Seite („Falls es zu dieser
   Adresse ein Konto gibt …“), auch wenn es kein Konto gibt oder der letzte Link zu jung ist. Für unbekannte Adressen
   erzeugt das Bundle ein Schein-Token, damit sogar die angezeigte Ablaufzeit gleich aussieht.
2. Die Mail enthält `/admin/passwort-setzen/<token>`. Das Token besteht aus einem „Selector“ (zum Finden der Zeile)
   und einem geheimen Teil, von dem in der Tabelle `reset_password_request` nur ein Hash steht. Wer die Datenbank
   liest, kann damit kein Passwort setzen.
3. Beim Aufruf schiebt der Controller das Token aus der URL in die Session und leitet auf `/admin/passwort-setzen`
   ohne Token um. So bleibt es nicht in der Adressleiste stehen und geht nicht über den `Referer`-Header an andere
   Seiten. (Das legt die erste Session an, noch vor einem Login; das Cookie gilt wie immer nur für `/admin`.)
4. Neues Passwort zweimal eingeben (gleiche Regeln wie überall, `PasswordRules`), der Link wird gelöscht und gilt
   damit nur einmal.

**Fristen** (`reset_password.yaml`, Standardwerte des Bundles ausgeschrieben): ein Link gilt 1 Stunde, ein neuer
kann frühestens 1 Stunde nach dem letzten angefordert werden (`throttle_limit`). Einladungen (B4) gelten 3 Tage
(`PasswordLinkMailer::INVITATION_LIFETIME`, eigene Annahme, leicht änderbar).

**Löschen:** Die Zeile in `reset_password_request` hängt mit `ON DELETE CASCADE` am Konto. Das ist die eine Ausnahme
von „keine Kaskaden“ (`datenmodell.md` 2): Ein Link ohne Konto ist wertlos, und Fachdaten hängen nicht daran.

**Mein Konto** (`/admin/konto`): Passwort ändern mit Abfrage des aktuellen Passworts (`UserPassword`-Constraint). Ein
neuer Hash meldet alle anderen Sitzungen dieses Kontos ab, weil ihre gespeicherte Prüfsumme nicht mehr passt.

**Sessions und Doctrine teilen sich eine Verbindung:** Der erste Stand (Commit `0636184`) gab `PdoSessionHandler` die
`DATABASE_URL`. In Tests nutzt Doctrine aber `app_test` (Suffix in `doctrine.yaml`), der Handler `app`; der
Schema-Listener erkannte „nicht dieselbe Datenbank“ und `doctrine:schema:validate` schlug in der CI fehl. Jetzt bekommt
der Handler Doctrines PDO-Verbindung (`config/services.yaml`, `app.session_pdo`). Weil der Handler im Standardmodus
`LOCK_TRANSACTIONAL` von `read()` bis zum Speichern eine Transaktion offen hält (`PdoSessionHandler::getSelectSql()`),
würde das auf der geteilten Verbindung mit Doctrines Transaktionen kollidieren. Deshalb `LOCK_ADVISORY`: PostgreSQL-
Advisory-Locks (`pg_advisory_lock`) sperren die Session, ohne eine Transaktion offen zu halten.

### Benutzerverwaltung und der erste Voter (B4)

Dateien: `src/Controller/Admin/UserController.php`, `src/Security/Voter/UserVoter.php`, `src/Security/UserChecker.php`,
`src/Form/Admin/UserType.php`, `templates/admin/user/`, Migration `Version20261113091408` (`app_user.active`).

- **Nur für `ROLE_SUPER_ADMIN`**, doppelt abgesichert: `access_control` (`^/admin/benutzer`) und
  `#[IsGranted(User::ROLE_SUPER_ADMIN)]` am Controller. Der Menüpunkt erscheint nur mit dieser Rolle
  (`is_granted()` in `templates/admin/_nav.html.twig`).
- **Neues Konto ohne Passwort:** Das Formular hat kein Passwortfeld. Das Konto wird mit leerem Hash gespeichert und
  bekommt eine Einladung (`PasswordLinkMailer::sendInvitation()`, Link 3 Tage gültig, derselbe Mechanismus wie
  „Passwort vergessen“). So kennt nur die Person selbst ihr Passwort, und es geht nie per Mail oder Chat herum.
- **Deaktivieren statt löschen:** `User::deactivate()` setzt `active = false` und leert den Hash. Der `UserChecker`
  (Firewall-Option `user_checker`) lässt deaktivierte Konten und Konten ohne Passwort nicht anmelden, mit derselben
  Meldung wie bei falschem Passwort. Offene Sitzungen enden beim nächsten Aufruf, weil die Prüfsumme des Hashes in der
  Session nicht mehr passt. Wieder einladen aktiviert das Konto und schickt einen neuen Link.

**Der Voter `UserVoter`** beantwortet „darf dieses Konto den Zugang **dieses** Kontos ändern?“. Das hängt vom Objekt
ab, deshalb reicht keine Rolle:

```php
protected function supports(string $attribute, mixed $subject): bool
{
    return self::CHANGE_ACCESS === $attribute && $subject instanceof User;
}

protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
{
    // nur Super-Admins, und nie das eigene Konto
}
```

- `supports()` entscheidet, ob der Voter überhaupt mitredet. Ist es nicht sein Attribut, enthält er sich. Ein zu
  großzügiges `supports()` (etwa nur `return true`) würde über fremde Attribute abstimmen.
- `voteOnAttribute()` antwortet ja oder nein. Mit `$vote?->addReason()` kann er eine Begründung mitgeben (neu in
  Symfony 7.3/8; hier der Übersetzungsschlüssel `user.flash.own_account`).
- **Warum nie das eigene Konto?** Sonst könnte Mike sich selbst die Super-Admin-Rolle nehmen oder sich aussperren, und
  niemand könnte mehr Konten verwalten. Die einfachste Regel ohne Sonderfälle („letzter Super-Admin“) ist: Den eigenen
  Zugang ändert immer ein anderes Konto.
- **Verwendet** in Twig (`is_granted('USER_CHANGE_ACCESS', user)` blendet Knöpfe aus), im Controller
  (`isGranted()` sperrt das Rollenfeld) und als `#[IsGranted(UserVoter::CHANGE_ACCESS, 'user')]` an den POST-Aktionen.
  Das Attribut am Controller ist die eigentliche Sperre; ausgeblendete Knöpfe allein schützen nicht, ein selbst
  gebauter Request käme sonst durch (`UserManagementTest::testOwnAccessCannotBeChanged`).
- **CSRF bei den Knöpfen:** Einladen und Deaktivieren sind POST-Formulare mit Token je Konto
  (`csrf_token('user-access-<id>')`). Diese Token-IDs sind nicht in der zustandslosen Liste, liegen also in der
  Session; im Admin-Bereich gibt es die ohnehin.

**Fallstrick beim Formular:** `User` hat einen Konstruktor mit Pflicht-Argument (`new User($email)`). Ein Formular
mit `data_class` ruft ohne Daten `new User()` auf und scheitert. `empty_data` in `UserType` erzeugt das Objekt
deshalb selbst.

## Alternativen

| Statt | Hätte man | Warum nicht |
|---|---|---|
| Firewall nur für `/admin` | eine Firewall für alles (`main`, `lazy`) | Auch mit `lazy` hängt das Cookie dann am ganzen Pfad `/`; der Browser schickt es an jede öffentliche Seite. Wir bräuchten die Anmeldung dort nicht |
| `form_login` | eigener Authenticator | `form_login` deckt CSRF, Fehlermeldung, Weiterleitung und Login-Bremse ab; ein eigener lohnt sich bei Passkeys (B5) |
| Sessions in PostgreSQL | Dateien in einem Volume, Redis | Volume: noch ein Volume und eigene Backups; Redis: eigener Container (RAM) |
| `app:user:create` | Konto per Migration oder Fixture | Passwort-Hash und echte Adresse im Repo |
| Remember-Me | 12-Stunden-Cookie | Langlebiges Cookie für einen Login pro Unterrichtstag unnötig (`rollen.md` 6) |

## Sicherheit und Best Practices

- Keine Passwörter und keine Hashes im Repo, in Logs oder in der Session (nur CRC32C-Prüfsumme).
- Gleiche Fehlermeldung bei unbekannter Adresse und falschem Passwort.
- CSRF bei Login und Logout; Login-Bremse gegen Durchprobieren.
- Cookie nur für `/admin`, `HttpOnly`, `Secure`, `SameSite=Lax`.
- Admin-Seiten mit `noindex, nofollow`.
- Zugriff dreifach abgesichert (`access_control`, `#[IsGranted]`, ab C1 Voter) und per Routen-Test überwacht.
