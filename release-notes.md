## [3.51.3]

- Raspberry-Pi-Images starten über die tatsächlichen MBR-PARTUUIDs statt firmwareabhängiger `by-slot`-Aliase. Behebt das Warten auf `/dev/disk/by-slot/system` bei Pi-4-Bootloadern, die Partition 0 melden.
- Image-Prüfung kontrolliert Kernel-Startzeile, Root-/Boot-Einträge in fstab, Partitionstabelle und Bytegleichheit der geprüften Dateisysteme mit dem ausgelieferten Disk-Image.
- Player-Center und Anleitung verweisen auf Image-Beta 3 für alle vier Modelle. Betroffene Karten müssen neu beschrieben werden; ein reines Backend-Update kann einen Fehler vor dem Systemstart nicht reparieren.
