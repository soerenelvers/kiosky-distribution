## [3.3.9] – 2026-10-18

- WordPress fordert GitHub-Contents-Manifeste auch ohne Zugangstoken als Raw-JSON an. Öffentliche Update-Pakete benötigen keinen Token mehr; private Quellen verwenden weiterhin den konfigurierten Read-only-Token. Paket-Prüfsummen werden unverändert kontrolliert.
- Die Installation über Kiosky Updates behält die vorherige Plugin-Aktivierung einschließlich Netzwerk-Aktivierung bei.
- Der Manifest-Cache wird erneuert; nach einem abgeschlossenen Upgrade wird nur das eigene veraltete Update-Angebot entfernt.
- Echte WordPress-Tests prüfen nun native Update-Erkennung und Installation über den Plugin-Upgrader, einschließlich tokenlosem Download, statt ausschließlich das ZIP direkt zu ersetzen. Ein bereits installierter alter Updater benötigt einmal das reparierte ZIP beziehungsweise die reparierte Updater-Datei.
