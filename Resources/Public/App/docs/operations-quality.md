# Betriebswerkzeuge ab 3.41.0

Die Funktionen stehen in Standalone, WordPress und TYPO3 bereit. Vor dem Update ein konsistentes Backup erstellen; bei CMS-Installationen die gesamte CMS-Datenbank einschließlich der neuen Ausspielnachweistabelle sichern. TYPO3 benötigt den üblichen Datenbank-Schemaabgleich. Die automatischen Player laden die gemeinsame Webanwendung beim nächsten regulären Abruf; bereits geöffnete ältere Webanwendungen gegebenenfalls über „Player neu laden“ aktualisieren.

## Änderungsverlauf

„Slides & Kanäle“, Programmverwaltung und Zeitplanung bieten einen Änderungsverlauf. Im Slide-Editor öffnet „Verlauf“ die Versionen des aktuellen Slides. Suche nach Name, Person oder Datum; eine Version zeigt die geänderten Felder. „Wiederherstellen“ schreibt eine neue Änderung über die normalen Validierungen und Berechtigungen. Hat jemand den Inhalt inzwischen geändert, wird die Wiederherstellung mit einem Konflikthinweis abgewiesen.

Der Verlauf beginnt mit diesem Update; frühere Bearbeitungen können nicht rekonstruiert werden. Höchstens 20 Versionen pro Inhalt, 1.000 Versionen insgesamt und 8 MiB komprimierte Daten werden aufbewahrt. Zu große Einzelstände bleiben als Metadaten sichtbar, ohne Wiederherstellungsfunktion. Kanäle enthalten die zugehörige Abfolge. Entfernte Zeitzuweisungen sind wiederherstellbar, wenn Inhalte und Ziele weiterhin gültig sind. Endgültiges Löschen von Slides, Kanälen oder Veranstaltungen entfernt auch deren historische Inhaltsdaten; dies ist kein Papierkorb.

Systemumzüge übertragen weiterhin die dokumentierten Konfigurations- und Inhaltsdaten. Betriebsverlauf und Ausspielnachweise sind keine Bestandteile dieses Austauschformats. Vorhandene Verlaufsdaten am Ziel werden durch einen Systemimport nicht zusätzlich gelöscht. Für eine vollständige Sicherung einschließlich Diagnostik das Datenbankbackup verwenden.

## Zeitplansimulation

Unter Displays oder Zeitplanung „Zeitplansimulation“ öffnen. Display, Datum und Uhrzeit wählen und optional einen Zeitraum von bis zu 24 Stunden anzeigen. Die Uhrzeiteingabe folgt Europe/Berlin, einschließlich Sommerzeit. Ein zusätzliches Preset lässt sich als ausdrücklich ausgewähltes Testszenario auslösen.

Die Simulation verwendet denselben Ausgabeentscheid wie der Player: Warnungen, Zuweisungen und Presets, Zeitplanung und Standardinhalt einschließlich Leerkanal-Rückfall. Sie berücksichtigt vorhandene geplante Preset-Termine. Angezeigt werden Auswahlgrund, Inhaltsvorschau und eine Zeitleiste der Wechsel. Sie verändert keine realen Zuweisungen, schickt keine Playerbefehle und erzeugt keine Ausspielnachweise. Bis zu 128 Auswertungszeitpunkte und 100 Preset-Ausführungen begrenzen den Aufwand.

Die Berechnung verwendet den aktuellen gespeicherten Zustand. Künftige manuelle Eingriffe, neue Warnungen und noch unbekannte Änderungen externer Veranstaltungsquellen kann sie nicht vorhersagen. Bei Kanälen zeigt die Vorschau den ersten gültigen Slide; sie rekonstruiert nicht die Rotationsphase eines real laufenden Players. Eine Simulation ersetzt keine Empfangsbestätigung des Geräts.

## Ausspielnachweise

Unter „Slides & Kanäle“ oder in den Werbeeinstellungen „Ausspielnachweise“ öffnen. Zeitraum und optional Display auswählen, Bericht laden und bei Bedarf CSV herunterladen. Der Zeitraum ist auf 93 Tage begrenzt; gespeicherte Meldungen bleiben 90 Tage erhalten.

Als Werbung gelten die automatischen Werbekanäle und Slides mit Werbe-Tags. Der Player misst sichtbare, bereit zur Wiedergabe befindliche Intervalle; versteckte Seiten, fehlgeschlagene Medien, Vorschauen und Simulationen zählen nicht. Meldungen werden signiert an die zugewiesenen Inhalte und das Display gebunden. Wiederholte Übertragungen derselben Meldung und identischer Zeitintervalle werden dedupliziert. Bis zu 500 Intervalle werden lokal für einen späteren Übertragungsversuch gepuffert. Lücken werden nicht hochgerechnet.

Der Bericht zeigt vom Player gemeldete Sekunden, Wiedergaben und Intervalle. Er prüft weder die Stromversorgung des physischen Fernsehers noch dessen Eingangssignal und ist kein manipulationssicheres Abrechnungsaudit eines fremdkontrollierten Geräts. Nach Schlüsselwechsel können ältere noch nicht übertragene Meldungen ungültig sein. Ohne lokal verfügbare Speicherung ist der Offlinepuffer nur während der Sitzung vorhanden.

## Veröffentlichungsprüfung

Im Slide-Editor „Prüfen“ wählen. Beim Wechsel vom Entwurf zur Veröffentlichung wird bei Problemen zusätzlich eine Freigabe angezeigt. Fehler müssen korrigiert werden; Hinweise lassen sich nach Prüfung ausdrücklich übergehen. Der Slide kann als Entwurf erhalten bleiben.

Geprüft werden beide gespeicherten Formate: fehlende Medien, ungültige Quellen, ungültige Zeiträume und Größen, mögliche Überläufe, kleine Schrift und QR-Codes. Fehlender Ticket-Link ist bei bedingter Anzeige zulässig und wird als Information angezeigt. Die Prüfung ruft keine externen URLs ab und bestätigt daher nicht deren Erreichbarkeit oder Einbettbarkeit. Textüberläufe sind eine Heuristik; die Vorschau bleibt maßgeblich. Bestehende API-Veröffentlichungsabläufe werden durch diese beratende Prüfung nicht nachträglich gesperrt.

## Rechte und Nachweise

API und Oberfläche prüfen Rechte und Lizenzmodule separat. Designer erhalten Verlauf und Prüfung für ihre eigenen Inhalte; Berichte und Simulation erfordern die entsprechenden Betriebsrechte. TYPO3 verwendet zusätzlich die native Rechtematrix. Restore verändert keine Besitzverhältnisse und akzeptiert keine veraltete Ausgangsversion.

Regressionen: `tests/quality-domain.test.ts`, `tests/quality-http.test.ts`, `tests/quality-cms-test.php` und `tests/quality-browser.py`. Browserprüfungen laufen gegen isolierte Fixtures in Chromium und WebKit für alle drei Oberflächentransporte; native CMS-Speicherung und Datenbankschema werden zusätzlich durch die verpflichtende CMS-CI geprüft. Smartphone-/Tablet-Layouts sind Browseremulation, keine Zertifizierung jedes physischen Endgeräts.
