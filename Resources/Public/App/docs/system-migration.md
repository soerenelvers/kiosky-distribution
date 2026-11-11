# Vollständiger Kiosky-Systemumzug

Ab **3.39.0** stehen unter **Schnittstellen → Import & Export · Serverumzug** zwei Verfahren bereit:

- **Vollständiger Systemumzug:** ersetzt die Kiosky-Daten des Ziels nach Vorschau und Bestätigung. IDs und Verknüpfungen bleiben erhalten.
- **Ausgewählte Bereiche als neue Kopien:** bestehender Transfer, ergänzt Inhalte mit neuen IDs und neu einzurichtenden Zugängen. Weiterhin mit älteren Transferdateien kompatibel.

Beide Instanzen vor einem vollständigen Umzug auf mindestens 3.39.0 aktualisieren. Unterstützt sind alle neun Kombinationen zwischen Standalone, WordPress und TYPO3, einschließlich Umzügen innerhalb derselben Plattform.

## Ablauf

1. Ziel einschließlich Datenbank und Dateien sichern. Quelle vor dem abschließenden Export nicht weiter bearbeiten; automatische Importe und zeitgesteuerte Schreibaktionen für das Umzugsfenster im Betrieb anhalten.
2. Auf der Quelle **Transferpaket exportieren → Vollständiger Systemumzug** wählen. Eigenes Paketpasswort zweimal eingeben, mindestens 12 Zeichen (12–256 UTF-8-Bytes).
3. Optional Schnittstellen-Zugänge, API-Zugänge und Display-Schlüssel einschließen. Das Paket ist auch ohne diese Option verschlüsselt. Datei und Passwort getrennt aufbewahren.
4. Auf dem Ziel als Administrator **Transferpaket importieren**, Datei auswählen, Passwort eingeben und **Paket prüfen** ausführen. Die Vorschau ersetzt keine Daten.
5. Benutzerprofile ausdrücklich vorhandenen Zielkonten zuordnen. Rollen und Passwörter bestehender Zielkonten bleiben erhalten. Anschließend Zielbestand, Datenmengen und Hinweise prüfen.
6. Sicherung und Ersetzen bestätigen, **KIOSKY UMZIEHEN** eingeben und den Umzug durchführen. Bei zwischenzeitlich geändertem Ziel erneut prüfen. Die Ansicht lädt nach Erfolg neu.
7. Anzeigen, Zeitpläne, Medien, Warnungen, Presets, Website-Kalender und Schnittstellen auf dem Ziel prüfen. Erst danach den produktiven Verkehr umstellen und Hintergrunddienste ausschließlich auf der neuen Instanz aktiv lassen.

## Übernommene Daten

Veranstaltungen einschließlich Ablaufplänen und Status, Vorlagen, Slides einschließlich Archiv/Papierkorb und Versionen, Kanäle und Playlists, Standorte, Displays, Gruppen und Matrizen, Standardinhalte, Zeitzuweisungen, operative Zuweisungen, Presets einschließlich Timer und bereits verarbeiteten Timer-Vorkommen, Warnungen/Vorlagen/DWD-Konfiguration, Werbe-, Kalender-, Veranstaltungs- und Speisekarteneinstellungen, Menüfreigaben/Abonnements, Modulschalter, Navigation, Sortierregeln, Benutzerprofile und Inhaltsfreigaben, eigene Schriften und portable Mediendateien.

Standalone nimmt seinen Medienkatalog mit. CMS-Ausgaben exportieren in Kiosky verwendete oder von Kiosky verwaltete Dateien; fremde Website-Dateien werden nicht pauschal kopiert. Native Schriftdateien reisen über die geschützte Schriftablage. Externe Medien- und Website-URLs bleiben externe Links; der Import ruft deren Server nicht ab.

Mit Zugangsdaten-Option bleiben API-Token-Hashes und Display-Schlüssel erhalten. CrewBrain/easyjob-Zugänge werden im passwortverschlüsselten Paket übertragen und auf dem Ziel mit dessen eigenem Schlüssel erneut verschlüsselt. Bereits auf dem Ziel widerrufene API-Schlüssel werden nicht reaktiviert. Ohne Zugangsdaten-Option sind API-Zugänge gesperrt, Displays benötigen neue Kopplung und Schnittstellen/Freigabepasswörter müssen neu eingerichtet werden.

## Plattformgrenzen und manuelle Schritte

- **Native WordPress-/TYPO3-Konten und deren CMS-Rechte:** werden nicht als CMS-Benutzer angelegt. Bestehende Zielkonten ausdrücklich zuordnen und Rechte prüfen. Nicht zugeordnete Eigentümer und Inhaltsfreigaben bleiben als gesperrte Profile erhalten. Standortbeschränkte Standalone-Quellkonten dürfen nicht automatisch auf unbeschränkte CMS-Konten abgebildet werden; diese bleiben zunächst unzugeordnet.
- **Neue Standalone-Konten:** lokale Standalone-Anmeldedaten können bei eingeschlossener Zugangsdaten-Option übernommen werden. CMS-Profile oder Exporte ohne Anmeldedaten werden deaktiviert angelegt; der Zieladministrator richtet deren Zugang und Rolle ein. Zielkonten behalten ihre bisherigen Anmeldedaten. Vorhandene Standalone-Standortbeschränkungen werden mit den Quellrechten geschnitten; der Import erweitert sie nicht automatisch.
- **Lizenzverwaltung, Lizenzidentität, Aktivierungen, Signierschlüssel und Kunden-/Lizenzserver-Daten:** bleiben auf dem jeweiligen Server. Ziel separat lizenzieren; ein Mandantenumzug ist kein Umzug der Lizenzzentrale.
- **Betriebssystem, SMTP/SSO, Umgebungsvariablen, Webserver, CMS-Seiten, Plugins/Extensions und Website-Einbindungen:** separat einrichten. Es handelt sich um einen Kiosky-Datenumzug, kein Backup der gesamten CMS-Website.
- **Player und Companion:** Schlüssel bleiben optional erhalten, aber Serveradresse und Routen unterscheiden sich zwischen Plattformen. Alte Player-Konfigurationen und API-Basisadressen auf die neue URL umstellen oder passende Weiterleitungen einrichten. Ein erhaltener Schlüssel allein ändert die gespeicherte URL eines Clients nicht.
- Anmeldesitzungen, offene Fernsteuerungsbefehle/Kopplungscodes, Heartbeats, Online-Status, Ausspielnachweise und temporäre Synchronisations-/Anmeldeprotokolle werden nicht übertragen. Online-Anzeigen entstehen erst durch neue Player-Verbindungen. Menüfreigabe-Sitzungen werden ungültig; das Freigabepasswort kann erhalten bleiben.

## Grenzen und Wiederherstellung

Maximal **128 MB entschlüsselte JSON-Nutzdaten**, einschließlich Base64-Dateien. Das verschlüsselte Downloadpaket kann etwa 171 MB groß sein. Je Sammlung maximal 10.000 Einträge. Historische Versionsreste bereits endgültig gelöschter CMS-Slides werden beim Export mit Hinweis ausgelassen; Versionen vorhandener und archivierter Slides bleiben erhalten. Fehlende oder beschädigte Dateien, ungültige Verknüpfungen und nicht unterstützte native Dateitypen führen zum Abbruch statt zu einem still unvollständigen Umzug. Große Bibliotheken benötigen eine gesonderte dateibasierte Migration; das Paketformat streamt keine unbegrenzten Videoarchive.

Auf Plesk müssen PHP `memory_limit`, `post_max_size`, Ausführungszeit sowie nginx-/Apache-Anfragegrenzen zur Paketgröße passen. PHP benötigt OpenSSL; für aus Standalone übernommene Speisekarten-Freigabepasswörter zusätzlich Sodium. Die private Schriftablage muss beschreibbar sein. Bei zu wenig Speicher zuerst die Hosting-Grenzen anpassen und erneut prüfen; die Anwendung ändert diese Serverwerte nicht selbst.

Standalone ersetzt Daten innerhalb einer SQLite-Transaktion. CMS-Ausgaben speichern den neuen Zustand über ihren vorhandenen Konfliktschutz und serialisieren vollständige Importe. Bei einem Speicherfehler werden nur die während dieses Imports neu erzeugten nativen Dateien/Ordner zurückgenommen; vorhandene CMS-Dateien bleiben erhalten. Ein erfolgreich importiertes, identisches Paket wird beim Wiederholen nicht nochmals eingespielt.

Vorhandene native CMS-Medien bleiben auf dem Ziel erhalten. Deshalb ersetzt der Umzug die Kiosky-Konfiguration, löscht aber keine fremden Website-Dateien. Nach einem erfolgreichen Umzug erfolgt ein Rückweg über die zuvor erstellte Datenbank-/Dateisicherung; eine Live-Synchronisation zweier Instanzen ist nicht Bestandteil des Imports.

## Prüfnachweise

`tests/system-migration.test.ts`: alle neun Richtungen, echte SQLite-Datenbanken und PHP-Core mit nativen Medienadaptern als kontrollierten Testdoubles; zusätzliche originär im PHP-Core erstellte Daten, Dateien/Schriften, Archive/Versionen, Zeitplanung, Credentials, Fehler und Rollback.

`tests/system-migration-http.test.ts`: echte HTTP-Sitzung, Administratorpflicht, gesperrte API-Key-Verwendung, CSRF und abgemeldete Sitzung.

`tests/system-migration-browser-smoke.py`: echter Datei-Download, Passwortprüfung, Vorschau, Benutzerzuordnung, Ersetzen, erhaltene Sitzung/Kopplung und Smartphone-/Tablet-Layout. CMS-Transportmodi prüfen die gemeinsame Oberfläche; die installierten CMS-Datei-/Datenbankadapter werden separat über `tests/cms-media-smoke.php` in den nativen Installations-/Upgrade-Gates geprüft.
