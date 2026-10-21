# Speisekarten-Slides

Ab Kiosky 3.5.0 in Standalone, WordPress und TYPO3. Einstieg:
`https://deine-kiosky-adresse/speisekarten` (bei Unterverzeichnissen entsprechend
unterhalb der Installation). Unter **Slides & Kanäle → Speisekarten** führt ein
Link zum Editor.

## Einrichtung durch die Administration

1. Mit dem vorhandenen Administrator-Konto anmelden und `/speisekarten` öffnen.
2. **Neue Speisekarte anlegen**: internen Namen, Vorlage und Format wählen.
   Enthalten sind **Blau · Klar**, **Hell · Bistro** und **Dunkel · Abendkarte**,
   jeweils für Hoch- und Querformat.
3. Neue Karten werden als normale Slides mit dem Tag **Speisekarte** und Status
   **Entwurf** angelegt. In der normalen Slide-Verwaltung Layout und Status
   einstellen, einem Kanal hinzufügen und wie gewohnt zeitlich planen.
4. Unter **Speisekarten-Rechte und Zuweisung** ein vorhandenes Benutzerkonto
   auswählen, **Speisekarten bearbeiten erlauben** aktivieren und die zulässigen
   Karten markieren. **Rechte speichern** übernimmt die Zuweisung sofort.

Für Küchenkonten empfiehlt sich die bestehende Rolle **Betrachter**. Das eigene
Speisekarten-Recht erweitert diese Rolle ausschließlich um die Bearbeitung der
zugewiesenen Karten im Inhaltseditor. Bestehende allgemeine Benutzerrechte werden
nicht eingeschränkt oder ersetzt. Administratoren verwalten alle Karten und die
Zuweisungen. In Standalone gelten zusätzlich die vorhandenen Standortrechte.
Ein erteiltes Recht ohne ausgewählte Karte erlaubt keine Kartenbearbeitung.

WordPress verwendet vorhandene Benutzer mit `kiosky_access`, TYPO3 vorhandene
Backendbenutzer mit Kiosky-Rolle und Zugriff auf das Modul **System → Kiosky**.
Die Rechteverwaltung legt keine zweite CMS-Benutzerdatenbank an.

## Tägliche Bearbeitung

Nach der Anmeldung sieht die Küche ihre zugewiesenen Karten. Änderbar sind
Titel, Untertitel, Fußzeile sowie Namen, Beschreibungen und Preise der Speisen.
Speisen lassen sich hinzufügen, entfernen und verschieben. Eine Vorschau zeigt
den Text und seine Platzierung; die endgültige Darstellung erfolgt im Player.
Bis zu **acht Speisen je Slide** halten die Vorlagen lesbar. Umfangreiche Karten
auf mehrere Slides verteilen und in einem Kanal zusammenstellen. Preise werden
als eingegebener Text angezeigt, beispielsweise `12,50 €`; es gibt keine
Preisberechnung oder automatische Allergenauszeichnung.

**Änderungen speichern** erstellt eine neue Slide-Version. Layout, Format,
Gültigkeitszeitraum, Status, Tags, Standort- und Kanalzuordnung bleiben erhalten.
Aktive Player übernehmen Änderungen bei ihrer nächsten regulären Synchronisierung
(in der Regel innerhalb von 30 Sekunden; ein offline laufender Player erst nach
Wiederherstellung der Verbindung). Entwürfe werden durch die Küche nicht
veröffentlicht.

Bei parallelen Änderungen meldet das Speichern einen Konflikt und behält die
lokalen Eingaben. Diese vor **Neu laden** kopieren, mit dem aktuellen Stand
vergleichen und erneut speichern. Seitenwechsel und Verwerfen fragen bei
ungespeicherten Änderungen nach.

## Anmeldung und Schutz

Standalone verwendet die bestehende Kiosky-Anmeldung. Ist Zugangswerk exklusiv
aktiviert, gilt dies ebenfalls für `/speisekarten`; lokale Passwörter und
API-Schlüssel eröffnen keinen Ersatzweg. Ein Login-Link führt zur bestehenden
Anmeldung und danach zurück zum Editor. Ein erforderlicher Passwortwechsel muss
vorher abgeschlossen werden.

WordPress leitet unangemeldete Benutzer zur nativen WordPress-Anmeldung.
TYPO3 leitet `/speisekarten` zum geschützten Kiosky-Backendmodul mit dem
Speisekarten-Editor. Dort gelten Backend-Anmeldung, Modulfreigabe und der
vorhandene Schutz der AJAX-Routen. Eine abgelaufene CMS-Sitzung gegebenenfalls
neu anmelden und den Editor erneut öffnen.

Die API prüft das eigene Speisekarten-Recht und die Kartenzuweisung für jede
Anfrage. Sie akzeptiert beim Speichern ausschließlich Inhaltsfelder und die
aktuelle Versionsnummer. Sie verweigert fremde oder gelöschte Karten,
Layoutänderungen, Veröffentlichungsfelder und veraltete Versionen. Texte werden
als Text dargestellt. Maschinenzugänge und öffentliche Player erhalten keinen
Zugang zum Editor.

## Layout, Export und Wiederherstellung

Die Vorlagen erzeugen normale Textelemente mit stabilen `menuField`-Zuordnungen.
Administratoren können diese im Slide-Editor positionieren und gestalten.
**Alle 27 zugeordneten Textfelder erhalten**, auch die leeren Plätze für spätere
Speisen. Fehlende Felder werden beim Inhalts-Speichern als unvollständige Vorlage
abgewiesen. Zusätzliche Designelemente bleiben erhalten. Der Inhaltseditor zeigt
als einfache Vorschau Texte; zusätzliche Bilder und komplexe Designs im normalen
Slide-Editor beziehungsweise Player prüfen. Die drei Vorlagen werden beim Anlegen
ausgewählt; nachträgliche Gestaltung erfolgt im normalen Slide-Editor.

Vor Änderungen Datenbank beziehungsweise CMS-Kiosky-Zustand sichern. Standalone
legt mit Migration `036_menu_access.sql` ausschließlich eine neue Tabelle für
Recht und Kartenzuweisungen an; CMS-Ausgaben ergänzen ihren bestehenden Zustand
um `menuAccess`. Bestehende Slides werden nicht konvertiert. Inhaltsänderungen
lassen sich über die vorhandene Slide-Versionsverwaltung zurücksetzen.

Beim Export eines Slides bleiben die Kartendaten und das Layout enthalten.
Benutzerrechte und Zuweisungen werden nicht in Inhalts-Pakete übertragen und
müssen auf der Zielinstallation neu vergeben werden. Vor einem Software-Rollback
Schreibzugriffe stoppen und die Datenbank-/CMS-Sicherung gemeinsam mit der
vorherigen Softwareversion wiederherstellen; neue Migrationen nicht manuell aus
einer laufenden Datenbank entfernen.
