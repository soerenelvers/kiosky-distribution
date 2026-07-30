# Kiosky – Bestandsanalyse und Zielarchitektur

Stand: 21. Juli 2026

## Bestand

Kiosky verbindet den bestehenden Frontend-Prototyp aus `index.html`, `styles.css` und `app.js` inzwischen mit einem Node.js-/TypeScript-Server und SQLite. Serverseitige Anmeldung, gehashte Sitzungen und ein Rollenmodell sind vorhanden; die Beispielinhalte des Display- und Editorbereichs werden während der weiteren Migration noch im `localStorage` des Browsers gehalten.

Vorhanden sind Dashboard, Veranstaltungsansicht, Display- und Gruppenverwaltung, feste Player-URLs, ein Basis-Slide-Editor, Kanal-Playlist, Zeitplanung, Player-Monitoring und Notfallübernahme. Proof of Play wurde zugunsten eines schlanken strukturierten Betriebslogs deaktiviert.

## Risiken

- Browserdaten sind weder geräteübergreifend noch revisionssicher.
- Player-URLs besitzen noch keine serverseitig prüfbaren Display-Tokens.
- Inhaltliche Schreibrechte greifen vollständig, sobald auch Display-, Slide- und Kanaldaten vom lokalen Prototyp in die Server-API migriert sind.
- Der bestehende Editor ist ein guter Interaktionsprototyp, aber noch keine vollständige Canvas-Engine.
- Eine direkte CrewBrain-Anbindung im Browser wäre ein Sicherheitsverstoß und wird nicht umgesetzt.

## Additive Zielarchitektur

- Node.js/TypeScript-Backend, ohne den bestehenden Prototyp zu ersetzen
- SQLite für lokale Entwicklung; Migrationen bilden die spätere relationale Datenbank sauber vor
- providerunabhängige Veranstaltungsquellen-Schnittstelle; CrewBrain ist die aktuell aktive Quelle
- ausschließlich serverseitiger CrewBrain-Client
- verschlüsselte Speicherung externer Zugangsdaten
- interaktive Benutzeranmeldung mit serverseitigen Sitzungen sowie optionaler API-Key für Maschinenzugriffe
- Audit-Log für Konfiguration, Verbindungstests, Vorschau und spätere Imports
- OpenAPI-generierte CrewBrain-Typen

## Editorentscheidung

Für die kontrollierte nächste Editorphase ist Konva.js vorgesehen. Konva unterstützt Ebenen, Transformationen, Gruppierung, Hit-Testing und performantes Canvas-Rendering. Es lässt sich hinter einer Rendering-Abstraktion einsetzen, sodass Editor-Vorschau und Display-Player dasselbe strukturierte Slide-Dokument verwenden können. Der aktuelle Editor wird erst ersetzt, wenn Laden, Speichern und Rendering abgedeckt sind.

## Importstrategie

Standard ist „Projekt als Veranstaltung“: CrewBrain-Events mit `Type=PROJECT` beziehungsweise `Subtype=PROJECT` werden zu Kiosky-Veranstaltungen. Die OpenAPI unterscheidet zusätzlich `MAINJOB`, `SUBJOB` und `SOLOJOB`; diese werden als alternative Strategien vorbereitet. Unterobjekte werden nicht ohne mandantenspezifische Mapping-Entscheidung als öffentliche Zeitangaben interpretiert.
