## [3.14.0] – 2026-11-04

- Windows-64-Bit-Player installiert sich beim ersten Start unter `%LOCALAPPDATA%\Kiosky\Player` und richtet Autostart für den angemeldeten Windows-Benutzer ein. Nach Anmeldung und verfügbarer Serververbindung startet Edge beziehungsweise Chrome im Vollbild mit einem eigenen dauerhaften Profil; die Kopplung bleibt nach Browser-/PC-Neustart erhalten. Autostart lässt sich entfernen und wieder aktivieren.
- Player-Center und Kopplungsseite verwenden versionsgebundene Skript-/CSS-Adressen, damit vorhandene Browser nach dem Update den neuen Stand laden.
- Windows-32-Bit-Angebot einschließlich `.url`-Alternative aus Player Center, Displaymenü und Download-Routen aller drei Ausgaben entfernen.
- Kopplungsseite und Player wiederholen fehlgeschlagene initiale API-Anfragen mit begrenztem Wiederholungsintervall. Ein beim Start noch nicht erreichbarer Dienst erfordert kein manuelles Neuladen.
- Windows-Integrationstest führt die ausgelieferte EXE mit isolierter Browserfixture aus und prüft Benutzerinstallation, Autostart, Netzwerkverzögerung, Vollbild-/Profilargumente, Neustart und Opt-out. Browserregression prüft dauerhafte Kopplung und automatische Wiederverbindung für die gemeinsamen Standalone-/CMS-Oberflächen.
