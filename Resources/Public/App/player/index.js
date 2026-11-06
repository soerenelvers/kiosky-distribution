(() => {
  const runtime=window.KioskyPlayerCenterRuntime||{};
  const browserUrl=runtime.browserUrl||'/player/browser.html';
  const downloadBase=runtime.downloadBase||'/player/downloads/';
  const downloads=runtime.downloads||{};
  const serverAddress = document.querySelector('#server-address');
  const status = document.querySelector('#catalog-status');
  const grid = document.querySelector('#player-grid');
  const piRelease='https://github.com/soerenelvers/kiosky/releases/download/raspberry-pi-v1.0.0-beta.2';
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
      <p>${detail} Beim ersten Start erscheint der Kopplungscode; danach startet der Player automatisch im Vollbild und prüft bei HTTPS-Verbindung regelmäßig auf Playerupdates.</p>
      <div class="availability ready"><i></i>Universal Player verfügbar</div>
      <div class="actions"><a class="download" href="${downloads[platform]||`${downloadBase}${platform}`}"><span>Player herunterladen<small>Noch keinem Display zugeordnet</small></span><b>↓</b></a></div>
    </article>`;
  }

  function loadCatalog() {
    grid.innerHTML = `${browserCard()}${desktopCard('windows-x64','Windows Player','⊞','Für Windows 10 und 11, 64 Bit. Eigenes Playerfenster mit eingebetteter WebView2-Engine, Vollbild und Benutzerautostart. Eine fehlende Microsoft-Laufzeit wird beim ersten manuellen Start nach Bestätigung eingerichtet.')}${desktopCard('linux-x64','Linux Player','⌘','Für Ubuntu und Debian, 64 Bit. Mit Autostart und dauerhaftem Profil; nach dem Download ausführbar machen.')}${desktopCard('linux-mint-x64','Linux Mint Player','⌘','Für Linux Mint und LMDE, 64 Bit: Cinnamon, MATE und Xfce. Mit Autostart und dauerhafter Kopplung. Firefox, Chromium oder Chrome werden unterstützt.')}${desktopCard('macos-universal','macOS Player','⌘','Als App für Apple Silicon und Intel-Macs ab macOS 11. Mit Benutzerautostart, dauerhaftem Profil und automatischen Playerupdates. Chrome, Edge oder Chromium erforderlich.')}`;
    status.textContent = '5 Player-Angebote';
    document.querySelector('#pi-grid').innerHTML = [
      ['zero2w','Raspberry Pi Zero 2 W','Für einfache Bild- und Textslides mit 512 MB RAM.'],
      ['pi3','Raspberry Pi 3','Für Raspberry Pi 3 mit 64-Bit-Prozessor.'],
      ['pi4','Raspberry Pi 4','Empfohlen für Videos und aufwendigere Inhalte.'],
      ['pi5','Raspberry Pi 5','Für anspruchsvolle Inhalte und leistungsfähige Displays.']
    ].map(([model,name,detail]) => `<article class="player-card">
      <div class="platform-icon">▦</div><h3>${name}</h3>
      <p>${detail} Schlankes System mit WLAN, Browser und dauerhaftem Playerprofil.</p>
      <div class="availability"><i></i>Vorabversion · Hardwaretest ausstehend</div>
      <div class="actions"><a class="download" href="${piRelease}/kiosky-player-${model}.img.xz"><span>Image herunterladen<small>Komprimiertes SD-Karten-Image · 64 Bit</small></span><b>↓</b></a></div>
    </article>`).join('');
  }

  document.querySelector('#pi-config').addEventListener('click', () => {
    const config={player_url:new URL(browserUrl,window.location.href).href};
    const url=URL.createObjectURL(new Blob([JSON.stringify(config,null,2)+'\n'],{type:'application/json'}));
    const link=document.createElement('a');
    link.href=url;link.download='kiosky-player.json';
    document.body.append(link);link.click();link.remove();
    setTimeout(() => URL.revokeObjectURL(url),1000);
    document.querySelector('#pi-config-status').textContent='kiosky-player.json enthält die Browser-Player-Adresse dieser Installation. Für WLAN den wifi-Block aus der Anleitung ergänzen. Anschließend auf die Boot-Partition der SD-Karte kopieren.';
  });

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
