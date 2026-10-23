# Speisekarten: Standalone und WordPress, 3.11.2

Der WordPress-Untermenüpunkt **Kiosky → Speisekarten** lud bisher `menus.html`.
Dieses Dokument startet absichtlich nur den Kücheneditor; ihm fehlen die
Einbettung und die Rückrufe zum visuellen Editor. Ab 3.11.2 lädt der Backendweg
`index.html` mit der Startansicht `menus`, wie die gemeinsame Verwaltung in
Standalone. Der öffentliche Weg `/speisekarten` lädt weiterhin den Inhaltseditor.

| Funktion für Administratoren | Standalone und WordPress |
| --- | --- |
| Vorgaben | Standardvorlage, Format, Titel, Untertitel und Fußzeile |
| Karten erstellen | Drei mitgelieferte Vorlagen sowie eigene Vorlagen; Hochformat, Querformat und Responsive |
| Inhalte | Titel, Hinweise, bis zu acht Speisen mit Beschreibung und Preis; Reihenfolge, Vorschau, Versionskonflikte |
| Karten verwalten | Umbenennen, Vorlage/Format wechseln, visuell gestalten und in den Papierkorb verschieben |
| Vorlagen | Anlegen, im visuellen Editor bearbeiten und archivieren; alle 27 Inhaltsbindungen erhalten |
| Lokale PDFs | Seiten importieren und vorhandene PDF-Karten ersetzen; vollständige proportionale Darstellung |
| PDF-Weblinks | Abonnieren, Intervalle/Tags/Seitennamen bearbeiten, manuell abgleichen, pausieren und beenden; automatisch erzeugter Kanal |
| Benutzerzuweisungen | Explizites Speisekartenrecht und ausgewählte Karten; Verwaltung durch Administratoren |
| Passwortzugang | Eigenständige Freigabe ausgewählter Karten; ausschließlich Inhalte, keine Veröffentlichung oder Verwaltung |
| Modulschalter | Navigation und Bearbeitungs-API sperren; Karten, Vorgaben und Zuweisungen erhalten |
| Ausspielung | Gewöhnliche Slides, Veröffentlichung, Kanäle, Displays und Zeitplanung über die vorhandenen Arbeitsbereiche |

WordPress verwendet seine bestehenden Benutzerkonten, REST-Anmeldung und
Mediathek. Standalone verwendet seine Benutzer-/Medienverwaltung und zusätzliche
Standortrechte. Signierte Modulfreigaben und vorhandene Rollen bleiben bindend.
Küchenkonten erhalten durch diese Korrektur keine Verwaltungsrechte.

Web-PDFs benötigen in beiden Laufzeiten die dokumentierte PDF-Konvertierung.
WordPress prüft fällige Abonnements über WP-Cron; für regelmäßige Prüfungen ohne
Seitenaufrufe muss der vorhandene Cron von einem System-Cron gestartet werden.
Standalone prüft mit seinem laufenden Server alle 30 Sekunden. Diese
Laufzeitvoraussetzungen werden durch die Oberflächenkorrektur nicht installiert.

## Nachweise und Grenzen

- `wordpress-app-document-test.php` führt die tatsächlichen Backend-, Küchen-
  und Freigabeeinstiege mit URL-/Sitzungsfixtures aus und prüft die gewählten
  Dokumente, Startansicht, REST-Nonce und Unterverzeichnis-URLs.
- `wordpress-navigation-test.php` prüft native Menüzuordnung, signierte
  Lizenzfreigaben, Modulschalter und explizite Küchenrechte.
- `wordpress-media-smoke.php` prüft dieselben Dokumente und den echten
  WordPress-Menü-Hook im installierten Plugin während der CMS-Upgrade-CI.
- `menus.test.ts`, `cms-menus-test.php` und die beiden Subscription-Testreihen
  prüfen Inhalts-/Layoutoperationen, Vorlagen, PDFs, Konflikte, Zuweisungen,
  Passwortzugänge, Modulschalter und Abonnementabgleich in beiden Backends.

Dies ist ein Abgleich des Speisekarten-Moduls. Eine konkrete Kundeninstallation
oder deren Cron-/Poppler-Konfiguration wird damit nicht geprüft. Anleitung:
[Speisekarten](speisekarten.md).
