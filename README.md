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
