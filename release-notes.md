## [3.52.1]

- Kopplungsseiten und anschließende Playeraufrufe umgehen alte CMS-/Proxy-HTML-Caches durch einen rein navigationsbezogenen Startparameter. Windows, Linux/Mint, macOS und Raspberry Pi behalten gespeicherte Serveradresse, Profile und Kopplung; alte Kopplungsseiten ohne QR-Element werden einmalig frisch geöffnet.
- Matrix-Standardinhalt zwischen Kanal und festem Slide umschaltbar, einschließlich API, Wiedergabe, Duplizieren und Systemumzug in Standalone, WordPress und TYPO3. Zuschnitt, Blenden, Versatz und Skalierung bleiben beim Bearbeiten erhalten.
- Inaktive/gelöschte Matrixkopien blockieren oder überlagern keine aktiven Displayzuordnungen. Standalone ersetzt den zu strikten eindeutigen Index durch aktive Zuordnungssperren; es werden keine Matrix-, Inhalts- oder Zeitplandaten gelöscht.
- Fehlender CMS-Matrixstatus verwendet Aktiv als Standard; ungültige neue Größen werden abgewiesen und frühere leere Zuschnittgrößen fallen auf sichtbare Displaymaße zurück. Matrix-Player laden nur ihre aktive Matrix statt den gesamten Katalog.
