# Benutzerrollen und Kanalfreigaben ab 3.37.0

| Rolle | Inhalte und Betrieb | Einstellungen und Zugänge |
|---|---|---|
| Administrator | Vollzugriff einschließlich endgültigem Löschen | Vollzugriff |
| Redakteur | Veranstaltungen, Slides, Vorlagen, Kanäle, Displays, Zeitplanung, Presets und Warnungen; Mediengestaltern Kanäle freigeben | Kein Zugriff auf Systemeinstellungen, Benutzer-/Tokenverwaltung oder Integrationszugänge |
| Mediengestalter | Veranstaltungen und Veranstaltungsvorlagen bearbeiten; eigene Slides erstellen, bearbeiten, duplizieren, archivieren und wiederherstellen; eigene Slides freigegebenen Kanälen hinzufügen | Kein Zugriff |
| Betrachter | Freigegebene Ansichten lesen | Kein Zugriff |

Endgültiges Löschen bleibt beim Administrator. Redakteure können keine Konten,
Rollen, Passwörter anderer Benutzer oder API-Zugänge verwalten. Der eigene
Passwortwechsel bleibt in Standalone mit aktuellem Passwort möglich; CMS-Konten
verwenden die native Anmeldung und Kontoverwaltung.

## Einrichtung

1. Administrator legt das Konto als **Mediengestalter** an. In WordPress die
   native Rolle **Kiosky Mediengestalter**, in TYPO3 das Feld **Kiosky-Rolle**
   verwenden. Native Mediathek-/FAL-Rechte gelten zusätzlich; insbesondere
   Dateifreigaben und Storage-Mounts in TYPO3 gezielt erteilen.
2. Unter **Slides & Kanäle → Kanalfreigaben** wählt Administrator oder Redakteur
   das vorhandene Konto und die manuell verwalteten Kanäle.
3. Mediengestalter wählen eigene Slides und **Zu Kanal hinzufügen** oder ziehen
   sie in den freigegebenen Kanal. Die API fügt sie hinzu, ohne fremde Einträge,
   deren Reihenfolge, Laufzeiten oder Kanaleinstellungen zu überschreiben.

Wiederholtes Hinzufügen desselben Slides erzeugt keine zusätzlichen Einträge.
Freigaben können sofort entzogen werden; die nächste Zuweisung wird abgewiesen,
auch wenn eine ältere Oberfläche den Kanal noch anzeigt. Bereits zugewiesene
Inhalte werden dadurch nicht gelöscht. Eigentum an eigenen Slides bleibt
erhalten; deren Änderungen wirken weiterhin überall, wo sie bereits verwendet
werden. Veranstaltungsänderungen wirken auf alle zugehörigen dynamischen Ausgaben.

Automatische Werbekanäle können Mediengestaltern nicht freigegeben werden.
Werbe-Tags und geschützte Menü-/Automationsmarker dürfen die Kanalfreigabe nicht
umgehen. Normale Medien können ausgewählt oder hochgeladen werden; Änderungen
an der gemeinsamen Medienorganisation sind nicht Teil dieser Rolle.
Ausdrücklich erteilte Küchen-/Speisekartenrechte bleiben separat bestehen.

## Technischer Vertrag

- `GET /api/content-access`: kleine Benutzer-/Kanalübersicht für Administratoren
  und Redakteure, ohne Zugangsdaten.
- `PUT /api/content-access/users/{id}` mit `{"channelIds":["…"]}`: nur bestehende
  Mediengestalter und bekannte, aktive, manuelle Kanäle; keine Rollenänderung.
- `POST /api/content-access/channels/{id}/slides` mit `{"slideIds":["…"]}`:
  eigener, aktiver Inhalt und aktuelle Kanalfreigabe erforderlich. Allgemeines
  Bearbeiten oder Löschen des Kanals bleibt gesperrt.
- Eigentum stammt aus der authentifizierten Identität, niemals aus `ownerId`
  des Request-Payloads. Versionszugriff, Kopien und Archiv werden ebenfalls geprüft.
- Fremde Slides und nicht freigegebene Kanäle erscheinen nicht in der eigenen
  Bibliothek. Für Mediengestalter unbekannte Schreibendpunkte bleiben gesperrt.

Standalone-Migration 046 ergänzt Spalten und eine Freigabetabelle. Benutzer,
Passwort-Hashes, Sitzungen und API-Token bleiben erhalten. Intern wird die neue
Rolle mit der alten Basisrolle `viewer` und `content_role=designer` gespeichert,
damit ältere direkte Leser keine Schreibrechte erteilen. Historische Slides
werden nur anhand des Erstellers ihrer ersten Version zugeordnet. Fehlender
Erstellernachweis bleibt ohne Eigentümer. Bestehende CMS-Slides ohne Eigentümer
bleiben durch Administratoren/Redakteure verwaltbar und werden nicht automatisch
einem Mediengestalter zugeschrieben. CMS-Freigaben und neue Eigentümerfelder
werden im bestehenden Zustand gespeichert.

TYPO3 behält `manager`, `event_editor` und `display_operator` als kompatible
Fachrollen. `editor` entspricht nun dem breiten Redakteur für Inhalte und Betrieb;
für reine Veranstaltungsarbeit weiter `event_editor` verwenden. Einstellungen
bleiben durch die native Prüfung geschützt, auch bei intern gröberer Zuordnung.

Vor einem Rollback Datenbank und passenden Paketstand zusammen sichern. Die
additive Migration nicht durch Löschen von Benutzern oder Neuanlegen von Tabellen
rückgängig machen.
