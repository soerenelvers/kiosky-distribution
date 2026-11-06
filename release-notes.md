## [3.22.0] – 2026-11-06

### Added
- Windows-/Linux-Player-Instanzen mit eigener Kopplung, Monitorzuordnung, Autostart und unabhängigen automatischen Updates; vorhandene Standardprofile bleiben erhalten.
- Native Windows-Instanzverwaltung (Startmenü/F10) und lokale Linux-Instanzverwaltung im Anwendungsmenü; Wartezustand bei fehlendem Monitor.
- Externe API für echte Zeitzuweisungen an Displays/Gruppen mit Slides/Kanälen, Uhrzeit- oder Datumsmodus und Kalenderwiederholungen.
- Companion 1.3.0 mit Zeitplanaktionen, Zeitvariablen und gezieltem Löschen von Zeitzuweisungen.

### Fixed
- Standalone berücksichtigt Wiederholungen bei der Wiedergabe; beide CMSs beachten Wiederholungsgrenzen, Wochentage und Intervalle.
- Neue API-Zeitpläne behalten ihre lokalen Uhrzeiten bei Sommerzeitwechseln. Nach Ablauf greift die bestehende Zeitplan-/Standardauswahl.
