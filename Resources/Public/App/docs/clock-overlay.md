# Uhr-Overlay

Unter „Slide“ im Slide-Editor kannst du das Uhr-Overlay aktivieren, seine Ecke und
Farben wählen und den Zoom von 50 bis 300 Prozent einstellen. Der Regler zeigt
Änderungen direkt an. Hintergrund, Abstände und Text werden gemeinsam vergrößert;
die gewählte Ecke bleibt verankert. Bestehende Slides verwenden weiterhin 100 Prozent.

Im Player gilt die Zeitzone des dem Display zugewiesenen Standorts. Stelle sie
unter „Displays → Standorte → Standort bearbeiten → Zeitzone am Standort“ ein,
beispielsweise `Europe/Berlin`, `Europe/London` oder `America/New_York`.
Beim Bearbeiten bleibt die Zeitzone erhalten. Neue Standorte schlagen
`Europe/Berlin` vor. Sommer-/Winterzeit wird automatisch berücksichtigt.

Ohne Standort verwendet der Player die gespeicherte Display-Zeitzone;
ist sie ungültig oder fehlt sie, gilt `Europe/Berlin`. Ein ungültiger Standortwert
fällt ebenfalls auf die Display-Zeitzone zurück. Standortänderungen werden beim
nächsten regulären Player-Abruf übernommen. Der Player verwendet seine bereits
vorhandene Synchronisierung mit der Serverzeit; zusätzliche Zeitdienste sind
nicht nötig. Die Serveruhr muss korrekt gestellt sein.

Die Standort-Zeitzone gilt auch für ältere Wegweisungs-Uhren und die Statusuhr
des Players. Editor- und Kanalvorschauen ohne konkretes Display zeigen
`Europe/Berlin`. Die Editor-Uhr läuft bei geöffnetem Dialog weiter.

Die Umsetzung ist für Standalone, WordPress und TYPO3 identisch. Die additive
Player-Eigenschaft `display.clockTimezone` stellt die aufgelöste Zeitzone bereit;
`display.timezone` behält die konfigurierte Display-Zeitzone.

Prüfungen: `pnpm test`, `pnpm test:php` und nach `pnpm build`
`python3 tests/clock-overlay-browser-smoke.py`. Der Browsertest verwendet Chromium,
Python Playwright und isolierte Datenbanken. Er prüft alle drei UI-Transporte,
eine abweichende Geräte-Zeitzone, vier Stunden Geräteuhrabweichung, einen
Standortwechsel sowie Zoom/Speicherung auf Desktop, Smartphone und Tablet.

## Countdown und abweichende PC-Uhr (ab 3.30.1)

Auch Countdown-Elemente verwenden in der tatsächlichen Ausgabe die synchronisierte
Serverzeit: feste Termine, tägliche Uhrzeiten, Veranstaltung/Ablauf mit Offset,
Countdowns in Lauftexten und Wegweisungen. Tägliche oder ohne Zeitzonenoffset
hinterlegte Zielzeiten werden in der Standort-Zeitzone ausgewertet; ohne Standort
gilt die Display-Zeitzone, andernfalls Europe/Berlin. Ein bereits mit Offset/Z
gespeicherter Veranstaltungstermin bleibt ein absoluter Zeitpunkt.

Bis 3.30.0 verwendete die regelmäßige Countdown-Aktualisierung noch die PC-Uhr,
obwohl das Uhr-Overlay bereits mit Serverzeit lief. Ein einzelner PC konnte deshalb
um mehrere Stunden abweichen. Die Zielpunktwahl eines Ablauf-Countdowns wird jetzt
auch erst mit Serverzeit festgelegt; Hochzählen/Ausblenden bleibt am gewählten
Punkt, statt nach dessen Ablauf zum nächsten Punkt zu springen.

`python tests/server-countdown-browser.py` prüft je Plattform fünf Player am selben
Standort mit unterschiedlichen Geräte-Zeitzonen und einem acht Stunden falsch
gehenden fünften PC. Die Korrektur verändert die PC-Systemuhr nicht.
