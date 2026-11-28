# Spam über die Anfrageformulare

Stand: 2026-11-27. Freigegeben (Mike, 2026-11-27); SP1–SP4 umgesetzt. Grundlage: `formulare.md` Abschnitt 3 (Honeypot, Rate Limiter,
stateless CSRF, kein CAPTCHA).

## 1. Ausgangslage

Seit Kurzem kommen laufend Spam-Mails über das Kontaktformular. Zwei Beispiele (Mike, 2026-11-27):

- Englisch, Gmail-Adresse, Angebot einer „SEO“- bzw. „digital marketing“-Firma („first page of Google“, „organic
  traffic“, „SEO Report for sonjaczak.com“).
- Keine Links nach außen. Eine Mail beginnt mit „Hi http://sonjaczak.com“, also mit der Domain statt eines Namens.
- Eine hat eine Telefonnummer (`2102102101`), die andere keine.

Solche Mails schreiben meist Menschen, die Listen abarbeiten, oder Werkzeuge, die einen echten Browser steuern. Sie
lassen den Honeypot leer, brauchen einige Sekunden und führen JavaScript aus.

**Logs:** In Produktion steht nichts über den Honeypot oder das Rate-Limit im Log. Monolog ist nicht installiert,
der eingebaute Logger schreibt nach `php://stderr` und verwirft dann alles unter `error`
(`vendor/symfony/http-kernel/Log/Logger.php` Zeile 62–67, symfony/http-kernel 8.1.11). Der Honeypot loggt mit `info`,
das Rate-Limit mit `warning`. Wir wissen also nicht, ob es auch Bots versuchen.

## 2. Optionen

| Maßnahme | Wirkt gegen diese Mails? | Bewertung |
|---|---|---|
| A. Mindest-Ausfüllzeit (signierter Zeitstempel) | Kaum, Menschen und echte Browser brauchen ohnehin Sekunden | Günstig, nur gegen einfache Bots. Erst, wenn die Logs viele Honeypot-Treffer zeigen |
| B. Feld, das erst JavaScript füllt | Kaum, die Werkzeuge führen JavaScript aus | Sperrt Besucher:innen ohne JavaScript aus. Nicht jetzt |
| C. Regel gegen Links | Nein, die Mails haben keine | Nicht jetzt |
| **D. Regel für den Inhalt (Wortliste)** | **Ja**, die Mails haben immer dieselben Begriffe | **Gewählt** |
| E. Selbst gehostetes Proof-of-Work-CAPTCHA (z. B. ALTCHA) | Teilweise, ein Mensch löst es trotzdem | Neues Paket, braucht JavaScript. Erst, wenn D nicht reicht |
| reCAPTCHA, hCaptcha, Turnstile | – | Drittanbieter und Cookies, schon in `formulare.md` ausgeschlossen |

### Was passiert mit einem Treffer?

| Variante | Pro | Contra |
|---|---|---|
| **Markieren** (gewählt, Mike 2026-11-27): Die Mail geht raus, mit „[Spam?]“ vor dem Betreff | Eine echte Anfrage mit einem Wort aus der Liste geht nicht verloren | Die Mails kommen weiter an; ein Filter im Postfach verschiebt sie |
| Still verwerfen (wie beim Honeypot) | Gar kein Spam mehr | Ein Fehlalarm verschwindet ohne Spur |

### Logging

| Variante | Pro | Contra |
|---|---|---|
| Mindeststufe `info` | Alles sichtbar | Symfony loggt dann z. B. „Matched route …“ bei jeder Anfrage, die Logs (3 × 10 MB je Container) wachsen unnötig |
| **Mindeststufe `warning`, unsere drei Fälle mit `warning`** (gewählt, Mike 2026-11-27) | Kein neues Paket; dazu kommen nur PHP-Warnungen, die wir ohnehin sehen wollen | Eigene Service-Definition für `logger` |
| Nur unsere Meldungen auf `error` | Keine Konfiguration | Spam ist kein Fehler; echte Fehler gehen darin unter |
| Monolog mit eigenem Kanal | Sauber, pro Kanal steuerbar | Neues Paket für ein paar Zeilen |

Hinweis: 404 und andere Client-Fehler loggt Symfony schon heute mit `error` (`ErrorListener::resolveLogLevel()`),
die stehen also bereits im Log und ändern sich nicht.

## 3. Umsetzung

**SP1 – Logging in Produktion ab `warning`.** In `config/services.yaml` unter `when@prod` einen eigenen Service
`logger` mit `Symfony\Component\HttpKernel\Log\Logger`, Argumente `'warning'`, `null` (stderr wie bisher), `null`,
`@request_stack`, Tag `kernel.reset` (Methode `clear`). Damit überspringt `LoggerPass` seine Standard-Definition
(`vendor/symfony/http-kernel/DependencyInjection/LoggerPass.php`: „if ($container->has('logger')) return;“). Dev und Test bleiben
unverändert. Prüfen: `bin/console -e prod debug:container logger` zeigt die Argumente.

**SP2 – Honeypot mit `warning` loggen.** `InquiryProcessor`: `info` → `warning`. Das Rate-Limit loggt schon mit
`warning`. Weiterhin nur der Typ des Formulars, kein Inhalt.

**SP3 – Wortliste und Markierung.**

- Neuer Service `App\Inquiry\SpamDetector` mit `isSuspicious(Inquiry): bool`. Er prüft alle Textfelder der Anfrage
  (Name, E-Mail, Nachricht, Ort, Vorerfahrung usw., also alle String-Eigenschaften) ohne Unterscheidung von
  Groß- und Kleinschreibung und nur auf ganze Wörter (`\b…\b`), damit z. B. „Seong“ nicht als „seo“ zählt.
- Liste als Parameter `app.inquiry_spam_patterns` in `config/services.yaml`, damit sie sich ohne Code ändern lässt.
  Vorschlag zum Start: `seo`, `backlink(s)`, `ranking(s)`, `organic traffic`, `digital marketing`, `search engine`,
  `first page of google`, `google search`, dazu die eigene Domain `sonjaczak.com` (eine echte Anfrage schreibt nicht
  „Hi sonjaczak.com“). **Nicht** „Google“ allein, denn „über Google gefunden“ ist eine normale Anfrage.
- `InquiryProcessor`: bei Treffer Betreff mit Präfix aus der Übersetzung `mail.subject.spam_prefix` („[Spam?] “),
  Log `warning` „Inquiry marked as spam“ mit dem Typ. Die Person sieht dieselbe Bestätigung wie sonst.
- Die Prüfung kommt **nach** dem Rate-Limit, damit markierte Mails auch mitzählen.

**SP4 – Tests.** `tests/Inquiry/InquiryProcessorTest.php` bzw. ein neuer `SpamDetectorTest`: die beiden echten
Beispiele (ohne Adressen) werden markiert; eine normale deutsche Anfrage mit „über Google gefunden“ nicht; „Seong“
nicht; markierte Mail hat den Präfix im Betreff und geht trotzdem raus. PHPStan, CI.

**SP5 – Filter im Postfach (Mike, optional).** Bei IONOS im Postfach `music@sonjaczak.com` eine Regel „Betreff
beginnt mit [Spam?] → Ordner Spam“. Das braucht keinen Code.

## 4. Danach

Nach etwa einer Woche in Produktion:

```bash
sudo docker logs --since 168h $(sudo docker ps -qf name=php) 2>&1 \
  | grep -oE "discarded by honeypot|rate limit exceeded|marked as spam" | sort | uniq -c
```

- Viele Honeypot-Treffer → Mindest-Ausfüllzeit (A) als günstige Ergänzung.
- Spam ohne Markierung → Wörter ergänzen.
- Echte Anfragen markiert → Wörter streichen.

## 5. Ressourcen und Datenschutz

Kein neues Paket, kein Container, kein Cookie. Die Datenschutzerklärung bleibt unverändert: Inhalte werden
weiterhin nur gemailt und nicht gespeichert, ins Log kommt nur der Typ des Formulars.
