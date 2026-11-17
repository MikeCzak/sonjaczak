# 21 Passkeys: Anmeldung mit Fingerabdruck

Stand: 2026-11-17. Anlass: `umsetzung-admin.md` B5. Quellen: installierter Code von `web-auth/webauthn-symfony-bundle`
und `web-auth/webauthn-lib` 5.3.13 (`vendor/web-auth/`), `@simplewebauthn/browser` 14.0.0 (npm-Paket im Scratchpad
gelesen), Symfony `EnvVarProcessor` (`vendor/symfony/dependency-injection`). Begriffe: `begriffe.md` (Passkey,
Relying Party, User Handle, Challenge, Attestation/Assertion). Konzept und Entscheidungen: `planung/rollen.md` 5,
`umsetzung-admin.md` B5.

## 1. Was beim Einrichten und beim Anmelden passiert

**Einrichten** („Passkey hinzufügen“ in „Mein Konto“, *Attestation*):

1. Browser → `POST /admin/konto/passkeys/optionen`. Der Server erzeugt eine Challenge (32 Zufallsbytes), legt sie mit
   den Optionen in die Session und schickt: RP-ID (`sonjaczak.com`), das Konto (User Handle, E-Mail als Name), die
   schon vorhandenen Passkeys (`excludeCredentials`), „Fingerabdruck verlangt“, „auf dem Gerät speichern“.
2. Das Gerät erzeugt ein Schlüsselpaar, fragt nach Fingerabdruck oder PIN und unterschreibt. Der private Schlüssel
   verlässt das Gerät nie.
3. Browser → `POST /admin/konto/passkeys` mit öffentlichem Schlüssel und Unterschrift (dazu der eingegebene Name). Die
   Bibliothek prüft Challenge, Herkunft, RP-ID, Flags; das Bundle speichert über `PasskeyRepository`, danach setzt
   `PasskeyCreatedHandler` den Namen.

**Anmelden** (*Assertion*, Knopf auf der Login-Seite):

1. `POST /admin/login/passkey/optionen`: neue Challenge, RP-ID, „Fingerabdruck verlangt“. Keine E-Mail-Adresse: Der
   Passkey liegt auf dem Gerät („discoverable“), das Gerät bietet ihn selbst an.
2. Das Gerät unterschreibt Challenge und RP-ID-Hash nach Fingerabdruck.
3. `POST /admin/login/passkey`: Die Bibliothek sucht den Passkey per Credential-ID, prüft die Unterschrift mit dem
   gespeicherten öffentlichen Schlüssel, den Zähler und die Flags; das Konto kommt über den User Handle
   (`PasskeyUserRepository`), danach wie beim Passwort `UserChecker`, Session, Weiterleitung
   (`PasskeyLoginSuccessHandler`).

## 2. Wo was liegt

| Teil | Datei |
|---|---|
| Bundle-Konfiguration (Profile, RP-ID, Endpunkte „Mein Konto“) | `config/packages/webauthn.yaml` |
| Login-Endpunkte in der Firewall | `config/packages/security.yaml` (`webauthn`), `access_control` |
| Routen | `config/routes/webauthn.yaml` (Loader des Bundles) |
| Tabelle | `App\Entity\Passkey` (erbt von `Webauthn\CredentialRecord`, Mapping in `doctrine.yaml`) |
| Konto ↔ Passkey | `User::$webauthnHandle`, `App\Repository\PasskeyUserRepository` |
| Speichern, „zuletzt genutzt“ | `App\Repository\PasskeyRepository::saveCredentialRecord()` |
| Browser | `assets/controllers/passkey_register_controller.js`, `passkey_login_controller.js` |

## 3. Entscheidungen im Code und warum

- **Kein Fremdschlüssel** `passkey.user_id`: Das Bundle verbindet über `user_handle` (Text). Ein zusätzlicher
  Fremdschlüssel wäre dieselbe Information zweimal (Frage 5). Folge: Die Datenbank verhindert nicht, dass ein Passkey
  auf ein nicht vorhandenes Konto zeigt; Konten werden aber nie gelöscht, nur deaktiviert.
- **User Handle zufällig** (64 Hex-Zeichen): Er wird auf dem Gerät gespeichert und darf laut Spezifikation nichts über
  die Person verraten (Frage 3). Hex statt roher Bytes, weil die Spalte des Bundles Text ist (PostgreSQL lehnt
  ungültiges UTF-8 ab). Bestehende Konten bekamen ihn in der Migration per `gen_random_uuid()`.
- **RP-ID aus `DEFAULT_URI`** (`%env(string:key:host:url:DEFAULT_URI)%`): `url` zerlegt die Adresse, `key:host` nimmt
  den Host, `string` macht den Typ eindeutig (sonst lehnt der Konfigurationsbaum des Bundles den Wert ab, weil `key`
  auch ein Array liefern könnte). Lokal `localhost`, in Produktion `sonjaczak.com`.
- **Eigene Stimulus-Controller** statt `@web-auth/webauthn-stimulus`: Der fertige Controller kann keinen Namen
  mitschicken. Der Name reist als Zusatzfeld `passkeyName` im JSON; die Bibliothek ignoriert unbekannte Felder
  (`PublicKeyCredentialDenormalizer`).
- **Registrierung über die Firewall aus** (Standard des Bundles): Sonst könnte sich jemand selbst ein Konto mit
  Passkey anlegen. Passkeys gibt es nur für angemeldete Konten.
- **`user_verification: required`**: Ohne Fingerabdruck oder PIN gilt die Anmeldung nicht (Frage 2). Das Bundle lässt
  den Browser diesen Wert nicht überschreiben (`client_override_policy.user_verification.enabled` ist standardmäßig
  aus).

## 4. Sicherheit

- **Phishing:** Der Browser unterschreibt nur für die RP-ID der Seite, auf der er gerade ist. Eine nachgemachte Seite
  bekommt keine brauchbare Unterschrift. Der Server prüft zusätzlich Herkunft (HTTPS, Host = RP-ID).
- **Wiederholung:** Jede Challenge gilt einmal und nur für den Endpunkt, der sie ausgegeben hat (in 5.3.13 nachgebessert,
  GHSA-g9ff-w9h6-7ff3). Der Zähler erkennt geklonte Schlüssel, soweit das Gerät zählt (Apple und Google zählen bei
  synchronisierten Passkeys meist nicht, dann bleibt er 0).
- **CSRF:** Für die JSON-Endpunkte kein Token nötig: Eine fremde Seite kann keine gültige Unterschrift für unsere
  Herkunft erzeugen. Das Löschen ist ein normales Formular mit Token.
- **Passwort bleibt:** Das Konto ist weiter nur so sicher wie Passwort und Postfach (`rollen.md` 5, „Fallstrick“).
- **Backups:** Die Tabelle `passkey` ist im Backup. Sie enthält nur öffentliche Schlüssel, nichts Geheimes.
- **Logs:** Das Bundle protokolliert in einen `NullLogger` (`webauthn.logger.default`), es landen keine Adressen im
  Log.

## 5. Fallstricke (gefunden beim Einbau)

- **Flex-Rezept übersprungen:** Rezepte aus `recipes-contrib` sind nicht freigeschaltet, das Bundle stand nicht in
  `config/bundles.php`. Von Hand eingetragen.
- **Neue Version am selben Tag:** Composer nahm 5.3.13 statt der geprüften 5.3.12. Release-Notes gelesen:
  Sicherheits-Release, daher behalten.
- **Das Bundle speichert ein `CredentialRecord`, nicht unsere Entity:** Nach dem Einrichten bekommt das Repository ein
  einfaches Objekt; `saveCredentialRecord()` baut daraus einen `Passkey`. Nach dem Login kommt dagegen das gefundene
  Objekt zurück (Zähler geändert) – daran erkennt es „wurde gerade benutzt“.
- **`rp` fehlte in den Optionen:** Ohne RP-ID lässt die Bibliothek `rp` ganz weg, und Chrome bricht ab („Required
  member is undefined“). Die PHP-Tests hatten das nicht bemerkt (sie prüften `rp` nicht); erst der Versuch im Browser
  mit dem virtuellen Authenticator. Jetzt prüft der Test `rp` mit.
- **Foundry nach einem Request:** Ein Objekt aus der Factory hängt danach an keinem Entity-Manager mehr; `save()`
  würde es neu anlegen (Unique-Fehler). Im Test vor dem ersten Request ändern.

## 6. Wie getestet wird

- `tests/Security/PasskeyRepositoryTest.php`: Konto per E-Mail und Handle, deaktiviert nicht, Speichern und
  „zuletzt genutzt“, Binärwerte über die Spaltentypen des Bundles.
- `tests/Admin/PasskeyTest.php`: Liste, nur eigene löschen, Optionen (RP, Handle, Fingerabdruck, vorhandene
  Passkeys), ungültiges Ergebnis, Name.
- `tests/Admin/PasskeyLoginTest.php`: Der Test spielt das Gerät mit einem echten P-256-Schlüssel (COSE-Schlüssel mit
  `spomky-labs/cbor-php`, Unterschrift mit OpenSSL) und läuft durch die ganze Prüfung der Bibliothek.
- Von Hand (B5d): Headless-Chrome mit virtuellem Authenticator über das DevTools-Protokoll
  (`WebAuthn.addVirtualAuthenticator`), Ablauf Hinzufügen → zweites Mal am selben Gerät → Abmelden → Passkey-Login.
  Echte Geräte (Touch ID, Android) erst in Produktion mit Sonja.

## 7. Alternativen

- **Nur die Bibliothek** `web-auth/webauthn-lib` mit eigenen Controllern und eigenem Authenticator: alles sichtbar,
  aber Kryptografie-Details (CBOR, COSE, Zähler, Flags) und die Firewall-Anbindung selbst schreiben.
- **Bedingte Anzeige im Login-Feld** (*conditional mediation*, „Passkey im Autofill“): Das Feld hat schon
  `autocomplete="username webauthn"`; dafür bräuchte es `useBrowserAutofill` beim Laden der Seite. Bewusst nicht
  eingebaut, ein Knopf ist für Sonja eindeutiger.
- **TOTP** (Einmalcodes aus einer App): sicherer als nur ein Passwort, aber umständlicher und nicht phishingsicher
  (`rollen.md` 5).
