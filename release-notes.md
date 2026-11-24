## [3.53.0]

- Mehrere Player pro Display mit eigenen, einzeln widerrufbaren Zugangsschlüsseln. Neue Kopplungen geben keinen gemeinsamen Display-Schlüssel mehr aus; bestehende direkte Player werden beim nächsten Online-Start mit aktueller Oberfläche ohne erneute Codeeingabe registriert.
- Gekoppelte Player in den Display-Einstellungen anzeigen, Status aktualisieren, einzeln oder gemeinsam entkoppeln. „Alle entkoppeln“ sperrt auch bisherige direkte Display-URLs. Das Display, Inhalte und Zeitzuweisungen bleiben erhalten. Ein entkoppelter Player kehrt bei der nächsten erfolgreichen Serververbindung zum Kopplungscode zurück und verwirft seinen Inhaltscache.
- Fernbefehle werden pro Player zugestellt und bestätigt. Die Bestätigung eines Players verbraucht keinen Befehl für die anderen Player desselben Displays.
- Standalone schreibt abgeschlossene Befehlsbestätigungen bei normalen oder wiederholten Heartbeats nicht erneut; die Erledigt-Zeitpunkte bleiben erhalten.
- WordPress hält unabhängige Player-Statusdaten in der kleinen Laufzeitablage, ohne alle Inhalte pro Heartbeat neu zu speichern. Verschlüsselte Systemumzüge mit Zugangsdaten erhalten die neuen Kopplungen; bereits widerrufene Ziel-Zugangsdaten werden nicht reaktiviert.
