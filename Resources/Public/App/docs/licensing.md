# Zentrale Lizenzverwaltung – ab Kiosky 3.9.0

Eine Standalone-Installation kann als zentraler Lizenzserver dienen. Andere
Standalone-, WordPress- und TYPO3-Installationen registrieren sich mit einem
Lizenzschlüssel und erhalten ausschließlich die zentral freigegebenen Module.
Der Lizenzmanager listet **registrierte** Installationen auf, nicht automatisch
alle irgendwo betriebenen Kiosky-Installationen.

## Verhalten

- In verwalteten Installationen sind ohne erfolgreiche Erstaktivierung alle
  Fachmodule gesperrt. Anmeldung, Benutzerverwaltung, Einstellungen,
  Lizenzaktivierung und Datensicherung bleiben zur Administration erreichbar.
- Der lokale Modulschalter kann eine zentrale Freigabe einschränken, aber keine
  fehlende Freigabe ersetzen. Direkte API-Aufrufe werden ebenfalls geprüft.
- Der Server fragt alle 15 Minuten nach einer neuen signierten Freigabe. In den
  Einstellungen ist ein sofortiger Abgleich möglich. Der Standalone-Timer prüft
  die Fälligkeit alle 30 Sekunden; CMS benötigen einen laufenden Scheduler.
- Bei Netzfehlern, ungültigen Signaturen und nicht bestätigten Antworten gilt die
  letzte gültige Freigabe **ohne zeitliches Ablaufdatum** weiter. Bestehende
  Inhalte, Benutzerrechte und lokale Modulschalter bleiben gespeichert.
- Ein ausdrücklich bestätigter Entzug einer Lizenz oder Instanz liefert eine
  signierte Sperre. Sie sperrt alle Fachmodule **und die Player**. Diese Sperre
  bleibt auch bei anschließendem Serverausfall erhalten.
- Ein einzelner Modulentzug entfernt die betroffenen Inhalte aus der Ausspielung.
  Ohne Display-Freigabe wird die gesamte Ausspielung gesperrt. Der Player erhält
  erfolgreich einen leeren Zustand, überschreibt seinen bisherigen Cache und
  zeigt „Lizenzfreigabe fehlt“. Die Browserseite wird dabei nicht neu geladen.
- Eine Sperre erreicht eine Instanz beim nächsten erfolgreichen Lizenzabgleich
  und deren verbundenen Player bei der nächsten Zustandsabfrage. Vollständig
  offline befindliche Geräte können einen neuen Entzug erst nach Wiederverbindung
  erfahren. Das ist die notwendige Folge des gewünschten Offlinebetriebs.

## 1. Zentralen Standalone-Server einrichten

Die bisherige Standalone-Instanz kann diese Rolle übernehmen. Zuerst eine
öffentliche HTTPS-Adresse mit gültigem Zertifikat bereitstellen, Datenbank und
Verschlüsselungsschlüssel sichern und serverseitig konfigurieren:

```dotenv
KIOSKY_PUBLIC_URL=https://kiosky.example.org
KIOSKY_ENCRYPTION_KEY=<bestehenden sicheren Schlüssel beibehalten>
KIOSKY_LICENSE_MASTER_KEY=<zufälliges Geheimnis mit mindestens 32 Zeichen>
```

Ein neues Bootstrap-Geheimnis kann zum Beispiel mit `openssl rand -hex 32`
erzeugt und im eigenen Secret-Manager hinterlegt werden. Keine Beispielwerte
übernehmen und keine Geheimnisse in Git committen. Den Prozess neu starten.

Als Administrator unter **Einstellungen → Lizenzverwaltung → Zentrale
Lizenzverwaltung auf diesem Server aktivieren** denselben Master-Key eingeben.
Kiosky erzeugt einmalig ein RSA-Schlüsselpaar mit 3072 Bit. Der private
Signierschlüssel und das Lizenzregister liegen mit dem vorhandenen
`KIOSKY_ENCRYPTION_KEY` verschlüsselt in der Datenbank. Wiederholte Aktivierung
erzeugt keinen neuen Schlüssel. Der zentrale Server hat selbst alle Freigaben.

Die Oberfläche zeigt den öffentlichen Prüfschlüssel an. Er ist kein Geheimnis,
muss aber über einen vertrauenswürdigen Weg an die verwalteten Server gelangen.
Der Master-Key wird ausschließlich auf der zentralen Instanz benötigt und
niemals an Kundeninstallationen verteilt. Die zentrale Rolle ist nur in
Standalone verfügbar; WordPress und TYPO3 sind Lizenznehmer.

## 2. Lizenz anlegen und Instanzen verwalten

Unter **Einstellungen → Lizenzverwaltung** Namen, zulässige Installationszahl und
Module wählen und speichern. Der vollständige zufällige Lizenzschlüssel wird
**nur einmal** angezeigt. Sicher kopieren und getrennt weitergeben. Im Register
bleiben nur Schlüsselhash und kurze Schlüsselkennung; ein verlorener Schlüssel
kann nicht ausgelesen werden. Eine neue Lizenz ausstellen und die alte nach der
Migration sperren.

Nach der Aktivierung einer Kundeninstallation zeigt das Register Instanzname,
Instanz-ID, gemeldete Adresse, Plattform, Kiosky-Version, letzten Kontakt und
Sperrstatus. Diese Metadaten sind Angaben der Instanz und kein unabhängiger
Nachweis ihrer Domain. Die Anzahl gleichzeitig freigegebener Installationen ist
begrenzt. Eine gesperrte Instanz bleibt in der Historie und gibt ihren Platz frei.
Ihre erneute Freigabe benötigt einen freien Platz. Eine Lizenz kann vollständig
gesperrt oder bezüglich ihrer Module geändert werden. Änderungen verwenden eine
Revisionsprüfung gegen versehentliches Überschreiben paralleler Bearbeitungen.

## 3. Verwaltete Instanz verbinden

Die Vertrauensbasis wird durch den Serverbetreiber außerhalb der normalen
Kiosky-Einstellungen gesetzt. Ein lokaler Kiosky-Administrator kann weder die
Lizenzpflicht abschalten noch einen eigenen Signierschlüssel hinterlegen.

### Standalone

```dotenv
KIOSKY_LICENSE_SERVER_URL=https://kiosky.example.org
KIOSKY_LICENSE_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
KIOSKY_PUBLIC_URL=https://kunde.example.org
```

Die URL ist die Basisadresse der zentralen Installation, ohne abschließenden
API-Pfad. Nur HTTPS ohne Benutzerinformationen, Query oder Fragment ist zulässig.
Der Prüfschlüssel muss der vollständige öffentliche RSA-Schlüssel des Masters
sein. Echte Zeilenumbrüche oder die Zeichenfolge `\n` werden unterstützt. Danach
den Prozess neu starten. Bereits ein teilweise gesetztes Vertrauen aktiviert die
Lizenzpflicht; eine ungültige Konfiguration schaltet keine Module frei.

### WordPress

Im geschützten `wp-config.php` vor dem WordPress-Bootstrap Konstanten setzen
(alternativ echte Umgebungsvariablen des PHP-Prozesses):

```php
define('KIOSKY_LICENSE_SERVER_URL', 'https://kiosky.example.org');
define('KIOSKY_LICENSE_PUBLIC_KEY', file_get_contents('/geschuetzt/kiosky-license-public.pem'));
define('KIOSKY_PUBLIC_URL', 'https://kunde.example.org');
```

Der PEM-Pfad ist ein Beispiel und muss durch eine lesbare, vom Betreiber
verwaltete Datei mit dem öffentlichen Schlüssel ersetzt werden. PHP benötigt
OpenSSL und cURL. Das bestehende minütliche Kiosky-Cron-Ereignis prüft auch
Lizenzen. Für Installationen ohne regelmäßige Seitenaufrufe WP-Cron über einen
System-Cron betreiben; sonst gibt es keine verlässliche automatische Prüfung.

### TYPO3

Dieselben drei Werte als PHP-Prozess-Umgebungsvariablen oder Konstanten in der
serverseitigen TYPO3-Konfiguration hinterlegen. Private Schlüssel und der
Master-Key gehören nicht in die Extension oder in TypoScript. PHP benötigt
OpenSSL und cURL. Im TYPO3-Scheduler **Kiosky: Lizenzfreigaben abgleichen**
minütlich ausführen lassen. Alternativ:

```sh
vendor/bin/typo3 kiosky:licenses:sync
```

Ein minütlicher System-Cron für diesen Befehl genügt; die Anwendung prüft selbst,
ob die 15 Minuten abgelaufen sind. Auch die bestehenden Kiosky-Import- und
Speisekarten-Scheduler übernehmen einen fälligen Lizenzabgleich.

### Aktivierung in allen drei Ausgaben

Als Administrator **Einstellungen → Lizenzverwaltung** öffnen, einen eindeutigen
Instanznamen und den Lizenzschlüssel eintragen und **Lizenz aktivieren / jetzt
abgleichen** wählen. Dort sind Freigaben, Lizenzserver, Fingerabdruck und letzter
Erfolg sichtbar. Für spätere manuelle Abgleiche das Schlüsselfeld leer lassen.
Zum Wechseln einer Lizenz den neuen Schlüssel eintragen. Fehlgeschlagene Wechsel
ersetzen die bisherige gültige Freigabe nicht. Die alte Registrierung kann danach
im Master gesperrt werden.

## Module und sinnvolle Kombinationen

| Schlüssel | Umfang |
| --- | --- |
| `dashboard` | Übersicht |
| `events` | Veranstaltungen und öffentlicher Website-Kalender |
| `channels` | Slides, Vorlagen, Kanäle und Inhaltspakete |
| `menus` | Speisekarten, Küchenzugänge, PDF-Weblink-Abonnements |
| `advertising` | Werbung und automatische Werbeerzeugung |
| `media` | Kiosky-Mediendatenbank |
| `displays` | Displays, Gruppen, Standorte, Matrix und Player-Freigabe |
| `schedule` | Zeitplanung |
| `operations` | Presets, Warnhinweise und Betriebsfunktionen |
| `integrations` | Allgemeine Schnittstellenverwaltung |
| `easyjob` | Easyjob-Verbindung und Import, zusätzlich `integrations` erforderlich |
| `crewbrain` | CrewBrain-Verbindung und Import, zusätzlich `integrations` erforderlich |
| `api` | Maschinenzugänge; angesprochene Fachmodule müssen ebenfalls freigegeben sein |
| `updates` | Integrierte Kiosky-Updatefunktionen |

Die Lizenzen ändern keine Benutzerrollen und keine Zugangswerk-/CMS-Anmeldung.
Der hier beschriebene Modul-Lizenzschlüssel ist unabhängig von gegebenenfalls
bereits verwendeten Zugangsdaten des Paket-Update-Dienstes. Bestehende Download-
und GitHub-Token werden dadurch nicht ersetzt.
Ein typischer Signage-Betrieb benötigt `channels` und `displays`, für Zeitpläne
zusätzlich `schedule`. Ein Speisekartenbetrieb benötigt `menus`, `channels` für
Kanalbearbeitung sowie `displays`; für Medienuploads zusätzlich
`media`. Ein Veranstaltungsimport benötigt `events`, `integrations` und den
jeweiligen Anbieter. Zeitpläne und aktive Inhaltszuweisungen (auch aus Presets
oder der externen API) benötigen `schedule`; ohne diese Freigabe wird wieder die
Standardzuweisung des Displays verwendet. Ohne `channels` bleiben vorhandene Speisekarten im eigenen
Modul bearbeitbar; die allgemeine Slide-/Kanalverwaltung ist gesperrt.

## Übergang, Sicherung und Wiederherstellung

Dieses Update schaltet bestehende Installationen nicht ungefragt ab: Solange
**beide** Root-Werte `KIOSKY_LICENSE_SERVER_URL` und `KIOSKY_LICENSE_PUBLIC_KEY`
fehlen, zeigt die Lizenzverwaltung ausdrücklich „Übergangsbetrieb“ und behält das
bisherige Verhalten bei. Die Lizenzpflicht wird bei der geregelten Einrichtung
der jeweiligen Instanz eingeschaltet. Deshalb bestehende Installationen zuerst
aktualisieren, eine Lizenz anlegen, dann die Root-Vertrauensbasis konfigurieren
und aktivieren. Zentral lassen sich nur so eingerichtete Instanzen kontrollieren.

Vollständige Datenbanksicherungen und die jeweiligen Verschlüsselungsgeheimnisse
gemeinsam, aber getrennt geschützt aufbewahren. Beim Master sind das Datenbank,
`KIOSKY_ENCRYPTION_KEY` und Bootstrap-Konfiguration; beim CMS zusätzlich dessen
unveränderte Salts beziehungsweise TYPO3-Systemschlüssel. Ein verlorener
Signierschlüssel lässt sich nicht aus dem öffentlichen Schlüssel zurückgewinnen.
Ein Wechsel des Signierschlüssels verlangt einen geplanten Austausch der
Vertrauensbasis und neue Aktivierungen aller Clients.

Normale Inhalts-/Systemübertragungen exportieren oder importieren keine
Lizenzschlüssel, Instanznachweise und privaten Signierschlüssel. Vollständige
Datenbankkopien enthalten dagegen auch die verschlüsselte Instanzidentität:
solche Kopien nicht als eigenständige neue Kundeninstanz ausrollen. Neue Instanzen
frisch installieren und separat registrieren. Bestehende Registrierungen nach
einer Migration kontrollieren und bei Bedarf sperren.

## Protokoll und Sicherheitsgrenzen

- `POST /api/license-server/lease`: Lizenzschlüssel, feste Instanz-ID, zufälliger
  Instanznachweis, Name, URL, Plattform, Version und frische Nonce; HTTPS, keine
  Weiterleitungen. Der zentrale Endpunkt begrenzt Anfragen und Eingabegröße.
- Antwort: `{payload, signature}`; `payload` ist Base64url-kodiertes JSON,
  signiert wird genau dessen Text mit RSA-SHA256. Der Inhalt bindet Version 1,
  Instanz-ID, Lizenz-ID, Revision, Module, Sperrstatus, Ausgabezeit und Nonce.
- Clients prüfen Signatur gegen den Root-Prüfschlüssel, Instanz, Live-Nonce,
  Modulkatalog und rückläufige Revision/Ausgabezeit derselben Lizenz. Ungültige
  oder unbestätigte Antworten können keine Offlinefreigabe überschreiben.
- Die erfolgreiche Antwort auf einen bestätigten Entzug enthält `revoked:true`
  und `modules:[]`. Ein HTTP-Fehler allein gilt nie als bestätigter Entzug.
- `GET /api/license-server/public-key` liefert ausschließlich den öffentlichen
  Schlüssel. Verwaltungsendpunkte unter `/api/settings/licensing/master` sind
  angemeldeten Administratoren vorbehalten; Maschinenzugänge sind ausgeschlossen.
- Keys und Instanznachweise liegen verschlüsselt im Client, Schlüssel im Master
  nur gehasht. Die Oberfläche liefert gespeicherte Geheimnisse nicht zurück;
  Audit-Einträge protokollieren die Verwaltungsaktion ohne Formularinhalt.
- Diese Kontrolle schützt gegen normale lokale Administratoren und direkte
  API-Aufrufe. Wer Server-Root, Datenbank samt Verschlüsselungsgeheimnis oder den
  Anwendungscode kontrolliert, kann eigene Software verändern. Ein dauerhaft
  erlaubter Offlinebetrieb kann technisch nicht gleichzeitig sofortige entfernte
  Sperrung garantieren.

## Validierung

`pnpm test` prüft Master, Platzlimit, Instanznachweis, Revisionen, signierte
Sperren, manipulierte Antworten und Offlineverhalten. Ein echter Node-signierter
Datensatz wird im PHP-Client geprüft. `pnpm test:php` ergänzt verschlüsselte
PHP-Caches sowie API-/Rollenprüfungen für WordPress und TYPO3.

Nach `pnpm build` prüft `python tests/licensing-browser-smoke.py` zwei isolierte
Standalone-Server über einen echten lokalen HTTPS-Proxy: Einrichtung, Aktivierung,
Registry, Modulwechsel, Ausfall, bestätigter Entzug eines laufenden Players ohne
Navigation und Mobilansicht. Benötigt werden Python Playwright, Chromium unter
`/usr/bin/chromium` und OpenSSL. `KIOSKY_BROWSER_ROOT` kann auf ein entpacktes
Release-Paket zeigen, `KIOSKY_BROWSER_OUT` legt den Ergebnisordner fest. Keine
externen Kundenserver oder echten Lizenzschlüssel werden dafür benötigt.
