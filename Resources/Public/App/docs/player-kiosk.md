# Vollbild und Vordergrund der Player

Die über `/player` heruntergeladenen Desktop-Player starten ohne Browserrahmen,
Menü- oder Scrollleisten. Kopplung und Profile bleiben bei Updates erhalten;
Multi-Display-Instanzen behalten ihre eigene Monitorzuordnung.

- **Windows:** Der eigene WebView2-Host belegt den gesamten Monitor einschließlich
  des Taskleistenbereichs. Er meldet sein Fenster beim Windows-Shell als Vollbild
  an und hält es im Vollbildmodus über normalen Anwendungsfenstern. Alle zwei
  Sekunden stellt er eine verlorene Topmost-Einstellung bzw. ein minimiertes
  Vollbild wieder her, auch nach einem Explorer-Neustart. F11 wechselt bewusst
  in ein normales Fenster und entfernt dabei Topmost; F11 bzw. der API-Befehl
  `fullscreen` stellt den Kioskmodus wieder her. F10 öffnet die Instanzverwaltung
  über dem Player, Alt+F4 beendet ihn.
- **Linux, Ubuntu und Mint:** Chromium bzw. Firefox laufen im Kioskmodus mit
  eigenem Profil. Auf X11/XWayland setzt der Controller zusätzlich Vollbild,
  Vordergrund, Ausblenden aus Taskleiste und Arbeitsflächenumschalter und prüft
  diese Fensterzustände jede Sekunde. Das gilt auch ohne feste Monitorzuordnung
  auf dem primären Monitor. Fenster werden anhand einer installations- und
  instanzspezifischen Klasse sowie bei nativen Browsern der eigenen Prozessgruppe
  erkannt. Bei Snap/Flatpak wird wegen der PID-Namensräume die eindeutige Klasse
  verwendet. Bei reinem Wayland steuert der Browser seinen Kioskmodus; globale
  Fensterprioritäten bestimmt der Compositor. Alt+F4 beendet das Playerfenster;
  anschließend kann die Instanzverwaltung zur Wartung geöffnet werden.
- **macOS:** Ein dediziertes Chromium-Kioskfenster blendet Dock und Menüleiste aus.
  Dies nutzt die [native Kiosk-Präsentation von Chromium](https://chromium.googlesource.com/chromium/src/+/HEAD/chrome/browser/app_controller_mac.mm),
  ohne globale Dock-Einstellungen zu verändern. Scrollleisten werden deaktiviert.
- **Raspberry Pi / Zero 2 W:** Cage betreibt Chromium als einzelne
  Vollbildanwendung ohne Desktop, Taskleiste oder Menüleiste; Chromium blendet
  zusätzlich Scrollleisten aus.

Die gemeinsame Webausgabe fordert Vollbild mit ausgeblendeter Browsernavigation
an und unterstützt die Safari-Vollbildschnittstelle. Nach Rückkehr zur sichtbaren
Seite wird die Anforderung erneut versucht. In einem gewöhnlichen Browser-Tab
kann der Browser dafür weiterhin eine lokale Benutzeraktion verlangen. Der
native Windows-Host bestätigt die tatsächliche Fensterumschaltung separat,
auch wenn die Webseite bereits DOM-Vollbild meldet.

Betriebssystem-Sicherheitsdialoge, Anmelde-/Sperrbildschirm und bewusst geöffnete
Wartungsoberflächen werden nicht unterdrückt. Andere Anwendungen und die globalen
Taskleisteneinstellungen werden nicht verändert.

Nach Installation des Kiosky-Updates beziehen bereits automatisch aktualisierbare
Player die neue Laufzeit über ihre bestehende Update-Routine. Alternativ den
aktuellen Download vom eigenen `/player` starten. Den Player neu starten, um die
Fenster- und Browserargumente sicher auf die neue Laufzeit umzustellen.
