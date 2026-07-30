(() => {
  const runtime = window.KioskyPlayerRuntime || {};
  const apiUrl = path => {
    if (!runtime.apiEndpoint) return path;
    const endpoint = new URL(runtime.apiEndpoint, window.location.href);
    endpoint.searchParams.set('path', path);
    return endpoint.toString();
  };
  const keys = {
    playerUrl: 'kiosky-player-url',
    displayName: 'kiosky-display-name',
    pairing: 'kiosky-browser-pairing'
  };
  const code = document.querySelector('#pairing-code');
  const status = document.querySelector('#pairing-status');
  const error = document.querySelector('#error');
  let pollTimer = 0;

  function read(key) {
    try { return localStorage.getItem(key); } catch { return null; }
  }

  function write(key, value) {
    try { localStorage.setItem(key, value); } catch { /* Der Player funktioniert bis zum nächsten Browserstart weiter. */ }
  }

  function remove(key) {
    try { localStorage.removeItem(key); } catch { /* Keine Aktion nötig. */ }
  }

  function showError(message) {
    error.textContent = message;
    error.hidden = false;
  }

  function savedPairing() {
    try {
      const pairing = JSON.parse(read(keys.pairing) || 'null');
      return pairing && pairing.id && pairing.secret && Date.parse(pairing.expiresAt) > Date.now() ? pairing : null;
    } catch {
      return null;
    }
  }

  function openPlayer(playerUrl) {
    write(keys.playerUrl, playerUrl);
    status.textContent = 'Verbunden. Player wird gestartet …';
    window.setTimeout(() => window.location.replace(playerUrl), 500);
  }

  async function poll(pairing) {
    window.clearTimeout(pollTimer);
    try {
      const response = await fetch(apiUrl(`/api/player-pairings/${encodeURIComponent(pairing.id)}`), {
        headers: { 'X-Pairing-Secret': pairing.secret, 'X-Player-Key': pairing.secret, Accept: 'application/json' },
        cache: 'no-store'
      });
      if (!response.ok) throw new Error('Kopplung konnte nicht abgefragt werden.');
      const result = await response.json();
      if (result.status === 'paired' && result.playerUrl) {
        write(keys.displayName, result.display?.name || '');
        remove(keys.pairing);
        openPlayer(result.playerUrl);
        return;
      }
      if (result.status === 'expired') {
        remove(keys.pairing);
        status.textContent = 'Code ist abgelaufen. Neuer Code wird erzeugt …';
        pollTimer = window.setTimeout(createPairing, 900);
        return;
      }
      status.textContent = 'Code ist 15 Minuten gültig · wartet auf Zuordnung …';
    } catch {
      status.textContent = 'Verbindung unterbrochen · neuer Versuch …';
    }
    pollTimer = window.setTimeout(() => poll(pairing), 3000);
  }

  async function createPairing() {
    window.clearTimeout(pollTimer);
    error.hidden = true;
    code.textContent = '••••••';
    status.textContent = 'Kopplungscode wird erzeugt …';
    try {
      const response = await fetch(apiUrl('/api/player-pairings'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ platform: 'Browser / Desktop Player', playerBaseUrl: runtime.playerBaseUrl || `${window.location.origin}/` })
      });
      if (!response.ok) throw new Error('Der Kopplungsdienst ist momentan nicht erreichbar.');
      const pairing = await response.json();
      write(keys.pairing, JSON.stringify(pairing));
      code.textContent = pairing.code;
      status.textContent = 'Code ist 15 Minuten gültig · wartet auf Zuordnung …';
      poll(pairing);
    } catch (reason) {
      showError(reason instanceof Error ? reason.message : 'Code konnte nicht erzeugt werden.');
      status.textContent = 'Bitte Netzwerkverbindung prüfen.';
    }
  }

  document.querySelector('#fullscreen').addEventListener('click', async () => {
    try {
      if (!document.fullscreenElement) await document.documentElement.requestFullscreen();
      document.querySelector('#fullscreen').textContent = 'Vollbild aktiv';
    } catch {
      showError('Der Browser erlaubt Vollbild nur über sein eigenes Menü.');
    }
  });

  document.querySelector('#new-code').addEventListener('click', () => {
    remove(keys.pairing);
    createPairing();
  });

  const parameters = new URLSearchParams(window.location.search);
  if (parameters.get('reset') === '1') {
    remove(keys.playerUrl);
    remove(keys.displayName);
    remove(keys.pairing);
    window.history.replaceState({}, '', runtime.browserUrl || window.location.pathname);
  }

  const playerUrl = read(keys.playerUrl);
  if (playerUrl) {
    openPlayer(playerUrl);
  } else {
    const pairing = savedPairing();
    if (pairing) {
      code.textContent = pairing.code;
      status.textContent = 'Code ist 15 Minuten gültig · wartet auf Zuordnung …';
      poll(pairing);
    } else {
      createPairing();
    }
  }
})();
