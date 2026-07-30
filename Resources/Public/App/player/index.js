(() => {
  const runtime=window.KioskyPlayerCenterRuntime||{};
  const browserUrl=runtime.browserUrl||'/player/browser.html';
  const downloadBase=runtime.downloadBase||'/player/downloads/';
  const downloads=runtime.downloads||{};
  const serverAddress = document.querySelector('#server-address');
  const status = document.querySelector('#catalog-status');
  const grid = document.querySelector('#player-grid');
  serverAddress.textContent = window.location.origin;

  function browserCard() {
    return `<article class="player-card featured">
      <div class="platform-icon">⌁</div>
      <h3>Browser-Player</h3>
      <p>Direkt auf diesem Gerät starten. Beim ersten Aufruf erscheint der Kopplungscode; eine Installation ist nicht nötig.</p>
      <div class="availability ready"><i></i>Sofort verfügbar</div>
      <div class="actions"><a class="download" href="${browserUrl}"><span>Browser-Player starten<small>Mit Kopplungscode</small></span><b>↗</b></a></div>
    </article>`;
  }

  function desktopCard(platform, name, icon, detail) {
    return `<article class="player-card">
      <div class="platform-icon">${icon}</div>
      <h3>${name}</h3>
      <p>${detail} Beim ersten Start erscheint der Kopplungscode; danach startet der Player automatisch im Vollbild.</p>
      <div class="availability ready"><i></i>Universal Player verfügbar</div>
      <div class="actions"><a class="download" href="${downloads[platform]||`${downloadBase}${platform}`}"><span>Player herunterladen<small>Noch keinem Display zugeordnet</small></span><b>↓</b></a></div>
    </article>`;
  }

  function loadCatalog() {
    grid.innerHTML = `${browserCard()}${desktopCard('windows-x64','Windows Player','⊞','Für Windows 10 und 11, 64 Bit.')}${desktopCard('windows-x86','Windows Player 32 Bit','⊞','Für ältere Windows-Systeme mit 32 Bit.')}${desktopCard('linux-x64','Linux Player','⌘','Für Ubuntu und Debian, 64 Bit.')}${desktopCard('macos-universal','macOS Player','⌘','Als App für Apple Silicon und Intel-Macs ab macOS 11.')}`;
    status.textContent = '5 Player-Angebote';
  }

  document.querySelector('#copy-server').addEventListener('click', async event => {
    try {
      await navigator.clipboard.writeText(window.location.origin);
      event.currentTarget.textContent = 'Adresse kopiert';
      setTimeout(() => { event.currentTarget.textContent = 'Adresse kopieren'; }, 1800);
    } catch {
      event.currentTarget.textContent = window.location.origin;
    }
  });

  loadCatalog();
})();
