## [3.21.0] – 2026-11-06

- Automatische Playerupdates über den verbundenen Kiosky-Server für Windows, Linux/Mint und macOS ergänzen. HTTPS, feste Plattformzuordnung, begrenzte Downloads, SHA-256-Prüfung und Versionssperre gegen Downgrades verwenden. Nach geprüftem Austausch automatisch neu starten; Kopplung, Profile und Autostart erhalten. Bei fehlgeschlagenem Start die vorherige Version wiederherstellen.
- Raspberry-Pi-Images um einen rootseitigen Timer für geprüfte Updates der festgelegten Player-/Dienstdateien ergänzen. WLAN, Displaykopplung und Browserprofil bleiben erhalten. Die SD-Karte wird nicht neu beschrieben; Systempakete nutzen weiterhin ihre bestehende Aktualisierung. Neue Images als Beta 1.0.0-beta.2 anbieten; bisherige Player/Images benötigen einmalig die neue Installation.
- Browser-Player nach einem neueren Kiosky-Release automatisch neu laden. Den Updatekanal ohne GitHub-Anmeldung aus Standalone-, WordPress- und TYPO3-Paketen bereitstellen.
- Native Windows- und macOS-Prüfungen sowie Linux- und Pi-Regressionen für gültige Updates, Daten-/Autostarterhalt, beschädigte Downloads, Downgrades und Rollback ergänzen. Große Image-Artefakttransfers zeitlich begrenzen.
