# Mehrere Player pro Display

Unter **Displays → Display bearbeiten → Player koppeln** kann jeder weitere Player mit seinem eigenen Zahlen-/Buchstabencode oder QR-Code demselben Display zugeordnet werden. Alle erhalten dieselbe konfigurierte Ausspielung, einschließlich Zeitplanung, Matrixzuordnung, Warnungen und Fernbefehlen. Die getrennten Profile der Multi-Display-Installation bleiben eigenständige Player.

**Gekoppelte Player** zeigt Kennung, Plattform und letzten Heartbeat. Über **Status aktualisieren** wird die Liste neu geladen. **Entkoppeln** sperrt nur den ausgewählten Player; die übrigen bleiben verbunden. Beim nächsten Serverkontakt kehrt dieser Player zum Kopplungscode zurück und entfernt seinen lokalen Inhaltscache. Ein offline befindlicher Player erfährt den Widerruf erst bei erneuter Verbindung. Bereits heruntergeladene Mediendateien lassen sich nicht aus der Ferne auf einem offline befindlichen Gerät löschen.

**Alle Player entkoppeln** widerruft zusätzlich den bisherigen gemeinsamen Display-Schlüssel. Damit werden auch manuell kopierte alte Player-URLs und direkt gebundene Launcher ungültig. Das Display, seine Inhalte und Zeitzuweisungen werden erhalten. Gewünschte Player danach ausdrücklich per Code neu koppeln. Individuelles Entkoppeln widerruft nicht eine separat aufbewahrte alte direkte Display-URL; bei verlorenem Gerät mit solchem Zugriff „Alle Player entkoppeln“ wählen.

## Vorhandene Installationen

Aktuelle Playeroberflächen registrieren beim nächsten Online-Start eine eigene Kennung. Die Registrierung ist pro Browserprofil und Display wiederholbar, ohne zusätzliche Player anzulegen. Wird ein früher direkt gebundener Player entkoppelt und anschließend ausdrücklich einem anderen Display zugeordnet, folgt auch der Start seines ursprünglichen Launchers dieser neuen Kopplung. Sie wird lokal gespeichert; die Kopplung bleibt nach Neustarts bestehen. Alte Playeroberflächen müssen zunächst über F5/Neustart den neuen Softwarestand laden. Vorher sind ihre gemeinsam verwendeten Display-URLs nicht einzeln unterscheidbar.

Die Liste ist kein Beweis für den aktuellen Bildinhalt; Online bedeutet Heartbeat innerhalb der letzten 60 Sekunden. Geräte ohne Heartbeat werden als offline angezeigt. Die Verwaltung verwendet dieselben Benutzer- und Standortrechte wie das Koppeln/Bearbeiten der Displays. Player-Kennwörter werden weder in dieser Liste noch in den Ausspielantworten offengelegt.

## API und Umzüge

- `GET /api/displays/:id/players`: Metadaten der aktiven Kopplungen, keine Schlüssel/Hashes.
- `DELETE /api/displays/:id/players/:playerId`: einzelne Kopplung widerrufen.
- `DELETE /api/displays/:id/players`: sämtliche Kopplungen und den bisherigen Display-Schlüssel widerrufen.

Diese Verwaltungsrouten erfordern eine berechtigte Benutzersitzung; die External Control API wird dadurch nicht erweitert. Neue Player-Schlüssel gelten nur für genau ein Display und dessen bisherige Player-Endpunkte, einschließlich zugewiesener Schriften. Ein entkoppelter Schlüssel kann sich nicht selbst erneut registrieren. Neue und bereits beanspruchte Kopplungen verfallen nicht nach dem 15-Minuten-Zeitraum ihres ursprünglichen Codes.

Verschlüsselte vollständige Systemumzüge übernehmen die aktiven Kopplungen ausschließlich mit aktivierter Zugangsdatenübernahme. Pakete ohne Zugangsdaten enthalten keine Player-Kennwörter oder Kopplungshashes. Bereits am Ziel widerrufene Schlüssel werden beim Import eines älteren Pakets nicht wieder gültig. Geänderte Serveradressen müssen wie bisher am Player umgestellt werden. Inhalte-/Slideexporte enthalten keine Player-Zugänge.

Standalone-Migration 055 ergänzt bestehende Tabellen und die individuellen Befehlsbestätigungen; sie entfernt keine Inhalte. Bei einem Downgrade versteht die frühere Anwendung die individuellen Schlüssel nicht. Vor einem Downgrade konsistente Datenbanksicherung wiederherstellen und die betroffenen Player neu koppeln.
