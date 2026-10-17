# Kiosky for TYPO3

TYPO3 13.4 LTS/14 extension for digital signage. The extension contains
Oberfläche, Player, API und lokale Datenhaltung. Benutzer und Anmeldung werden vom
TYPO3-Backend übernommen; ein Kiosky-Standalone- oder Node.js-Server ist nicht nötig.

The backend-user field **Kiosky role** provides administrator, manager, editor,
event editor, display control and read-only roles. TYPO3 administrators have full
access. Module, page and table access must additionally be granted through normal
TYPO3 backend groups.

In a Classic Mode installation, the release archive
`kioski_VERSION.zip` direkt unter **System → Extensions → Upload Extension**
hochgeladen und aktiviert werden. Das Backendmodul erscheint anschließend unter
**Content → Kiosky**. Öffentliche Player laufen unter `/kiosky-player/`.

For Composer installation:

```bash
composer require casesound/kiosky
vendor/bin/typo3 extension:setup
vendor/bin/typo3 cache:flush
```

Kiosky entities are relational TYPO3 records. Media uses FAL. The legacy state table
is read only and exists solely for migration analysis.

CrewBrain-Verbindungstest, Tokenabruf, Vorschau und Import laufen direkt in TYPO3.
Für automatische Importe kann zusätzlich die TYPO3-System-Extension `scheduler`
aktiviert werden. Danach steht die Aufgabe **Kiosky: automatischer
CrewBrain-Import** bereit; alternativ kann
`vendor/bin/typo3 kiosky:crewbrain:sync` ausgeführt werden.

The legacy External Control API is available under `/kiosky-api/?path=/api/v1`. Before
it is used, configure `externalApiKey`. New display endpoints live under
`/kiosky/api/v1/displays/{uuid}/...` and require a per-display bearer token.

The player overview is available at `/player`; `/kiosky-player-center/` is retained
as a compatibility alias.

## Website-Veranstaltungskalender

Im Seitenmodul steht das Inhaltselement **Kiosky Veranstaltungskalender** zur
Verfügung. Es zeigt ausschließlich freigegebene Kiosky-Veranstaltungen und erbt
Schrift, Farben und Grundgestaltung des TYPO3-Sitepackages. Suche, Monats-, Jahres-
und Tagesnavigation sowie die Liste der nächsten Veranstaltungen sind enthalten.
Die zentrale Kiosky-Konfiguration kann pro Inhaltselement überschrieben werden.

## Externe Gebäudeautomation und Integrationen

Die External Control API läuft unter `/kiosky-api/v1`. Unter **Schnittstellen → Externe API** werden eigene Maschinenzugänge angelegt und widerrufen; die Oberfläche zeigt die vollständige Basisadresse an. Die Clients senden `Authorization: Bearer <Schlüssel>` oder `X-API-Key`. Ein TYPO3-Backend-Login ist für Maschinenzugänge nicht erforderlich. Der bisherige Konfigurationsschlüssel `externalApiKey` und der Query-Einstieg `/kiosky-api/?path=/api/v1` bleiben unterstützt. Die API ist für aktive Schlüssel verfügbar und verweigert den Zugriff ohne gültige Authentifizierung.

## Einrichtung und Home Assistant

Die [Einrichtungsanleitung](Resources/Public/App/docs/setup-guide.md) im Paket
beschreibt Updates, FAL-Medien, transparente Lauftexte und API-Zugänge.
Öffentlich lesbar: [Einrichtung](https://github.com/soerenelvers/hacs-kiosky/blob/main/docs/kiosky-setup.md).

Die [HACS-Integration](https://github.com/soerenelvers/hacs-kiosky) als
benutzerdefiniertes Repository vom Typ **Integration** hinzufügen, herunterladen,
Home Assistant neu starten und Kiosky unter **Geräte & Dienste** einrichten.
Die angezeigte externe API-Basisadresse und einen eigenen Kiosky-API-Schlüssel
verwenden, keinen GitHub-Update-Token. Voraussetzungen: Kiosky ab 3.3.1 und
Home Assistant ab 2026.3.0.

## PDF-Handbuch

Das [deutsche Kiosky-Handbuch für Version 3.3.5](docs/Kiosky_Handbuch_3.3.5_DE.pdf)
enthält auf 20 Seiten die Funktionsübersicht, Bedienung, Installation, Updates,
Datensicherung und Fehlerbehebung für Standalone, WordPress, TYPO3 und Home Assistant.
Dokumentationsstand: 17. Oktober 2026; Home-Assistant-Integration 1.0.0.

[PDF direkt herunterladen](https://github.com/soerenelvers/kiosky-distribution/raw/refs/heads/main/docs/Kiosky_Handbuch_3.3.5_DE.pdf).
