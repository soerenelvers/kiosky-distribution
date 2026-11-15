# Player-Gesundheit und verzögerte Warnungen

In **Displays → Player-Gesundheit und Warnungen** stehen je Display:

- Verbindung und letzte empfangene Lebensmeldung;
- gemeldete Anwendungs-/Player-Version und Hinweis auf eine ältere Anwendung;
- letzter erfolgreicher Inhaltsabruf und seine Dauer;
- aktuelle Bild-/Video-Ladefehler, fehlender Inhalt und Abrufprobleme.

Suche und Filter funktionieren auch auf Smartphone und Tablet. Pro Seite werden
höchstens 25 Karten gerendert. Ein globaler Hinweis führt zu aktiven Warnungen.
Die Anzeige wird bei sichtbarer Verwaltung höchstens alle 15 Sekunden aktualisiert;
gleichzeitige Abfragen werden vermieden. Ein Ausfall dieser Abfrage lässt den letzten
bestätigten Stand stehen und kennzeichnet den Fehler ausdrücklich.

## Wartezeiten und Wartung

Administratoren stellen im selben Bereich die Überwachung und Wartezeiten ein:

| Zustand | Vorgabe | Bedeutung |
| --- | --- | --- |
| Offline | 180 Sekunden | Keine Lebensmeldung innerhalb dieser Zeit |
| Medienfehler | 60 Sekunden | Fortlaufend gemeldeter Bild-/Videofehler |
| Inhaltsabruf fehlerhaft | 120 Sekunden | Abruffehler oder letzter Erfolg über 30 Sekunden alt |
| Keine Ausgabe | 120 Sekunden | Erfolgreicher aktueller Abruf enthält keinen ausspielbaren Inhalt |

Erlaubt sind jeweils 30 bis 86.400 Sekunden. Vor Ablauf erscheint „Wartezeit läuft“.
Erholung beendet den betreffenden Fehler. Einstellungen und das Fortsetzen nach
Wartung starten eine neue Wartezeit. Offline überlagert die übrigen Fehler; ein
abgeschaltetes/archiviertes Display oder eine gezielt pausierte Überwachung erzeugt
keine Warnung. Noch nie verbundene Geräte erzeugen keine Offline-Warnung.
Displaysteuerungsberechtigte können einzelne Überwachungen pausieren und fortsetzen.

Es handelt sich um **Hinweise innerhalb von Kiosky**, nicht um E-Mail-/Push-Versand.
Der Server berechnet die Fristen; der Player meldet Abrufalter mit einer monotonen
Uhr. Ein Player im Hintergrund meldet diesen Zustand statt einer gesunden sichtbaren
Ausgabe. Lange Beobachtungslücken beginnen die Diagnosefrist neu.

## Aussagekraft und Zugriff

Nach dem Update Player einmal neu laden. Alte Player ohne Diagnose werden als
„Diagnose fehlt“ angezeigt, nicht als gesund. Die Diagnose ergänzt die vorhandenen
Lebensmeldungen; sie belegt weder das physische Bildsignal noch eingeschaltete TVs.
Eingebettete fremde Websites können wegen Browsergrenzen nicht vollständig auf
inhaltliche Fehler untersucht werden. Anzeigezeiten stehen in Europe/Berlin.

Die Diagnose enthält nur begrenzte Statuswerte und bekannte Fehlercodes, keine
Medien-URLs, freien Fehlermeldungen oder Zugangsdaten. Sie verwendet den bestehenden
Player-Schlüssel und benötigt keine zusätzliche öffentliche Schnittstelle.
Leseberechtigte sehen die aktuelle Ausgabe über eine authentifizierte Vorschau ohne
Player-Schlüssel. Verwaltungsrechte für Geräte bleiben Voraussetzung zum Auslesen
von Kopplungs-/Startinformationen. TYPO3 berücksichtigt zusätzlich native Rechte.
Standortbeschränkte Standalone-Benutzer sehen nur freigegebene Geräte.

Die Umsetzung gilt für Standalone, WordPress und TYPO3. WordPress behandelt die
Diagnose wie Lebensmeldungen als flüchtigen Status: Sie verursacht keine unnötigen
Speicherkonflikte mit Inhaltsänderungen. Systemumzüge übertragen Einstellungen,
jedoch keine veralteten Diagnosemessungen. Migration 050 ergänzt in Standalone eine
separate Tabelle; bestehende Inhalte und Zeitzuweisungen bleiben erhalten.
