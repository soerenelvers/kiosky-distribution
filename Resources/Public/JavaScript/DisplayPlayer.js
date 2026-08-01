(() => {
  'use strict';
  const runtime = window.KioskyDisplayRuntime || {};
  const uuid = String(runtime.uuid || '');
  const root = document.querySelector('#kiosky');
  const tokenKey = `kiosky-display-token-${uuid}`;
  const cacheKey = `kiosky-display-content-${uuid}`;
  let token = localStorage.getItem(tokenKey) || '';
  let content = null;
  let index = 0;
  let timer = 0;

  function setup() {
    root.innerHTML = `<form id="setup" style="max-width:36rem;margin:auto;padding:2rem;font:16px system-ui">
      <h1>Display verbinden</h1><p>Fügen Sie den einmalig im TYPO3-Backend erzeugten Display-Token ein.</p>
      <input name="token" type="password" autocomplete="off" required style="width:100%;padding:.8rem;box-sizing:border-box">
      <button style="margin-top:1rem;padding:.8rem 1.2rem">Verbinden</button><p id="error" role="alert"></p></form>`;
    root.querySelector('#setup').addEventListener('submit', async event => {
      event.preventDefault();
      token = new FormData(event.currentTarget).get('token').trim();
      if (await load()) {
        localStorage.setItem(tokenKey, token);
        play();
      } else {
        root.querySelector('#error').textContent = 'Token ungültig oder Server nicht erreichbar.';
      }
    });
  }

  async function request(action, options = {}) {
    const response = await fetch(`/kiosky/api/v1/displays/${encodeURIComponent(uuid)}/${action}`, {
      ...options,
      headers: {'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json', ...(options.headers || {})},
      cache: 'no-store',
    });
    if (response.status === 401) {
      localStorage.removeItem(tokenKey);
      throw new Error('unauthorized');
    }
    if (!response.ok) throw new Error(`http-${response.status}`);
    return response.json();
  }

  async function load() {
    try {
      const response = await request('content');
      content = response.data;
      localStorage.setItem(cacheKey, JSON.stringify(content));
      return true;
    } catch (error) {
      if (error.message === 'unauthorized') return false;
      try {
        content = JSON.parse(localStorage.getItem(cacheKey));
        return Boolean(content);
      } catch {
        return false;
      }
    }
  }

  function safeText(value) {
    const node = document.createElement('div');
    node.textContent = String(value || '');
    return node.innerHTML;
  }

  function render(item) {
    const documentData = item.document || {};
    const text = safeText(documentData.text || documentData.content || item.name);
    if (item.type === 'image' && item.mediaUrl) {
      return `<img src="${encodeURI(item.mediaUrl)}" alt="${safeText(item.name)}" style="width:100%;height:100%;object-fit:contain">`;
    }
    if (item.type === 'video' && item.mediaUrl) {
      return `<video src="${encodeURI(item.mediaUrl)}" autoplay muted playsinline style="width:100%;height:100%;object-fit:contain"></video>`;
    }
    if (item.type === 'html') {
      return `<iframe sandbox srcdoc="${safeText(documentData.sanitizedHtml || '')}" style="border:0;width:100%;height:100%"></iframe>`;
    }
    return `<section style="display:grid;place-items:center;width:100%;height:100%;font:clamp(2rem,6vw,7rem)/1.1 system-ui;text-align:center;padding:5vw;box-sizing:border-box">${text}</section>`;
  }

  function play() {
    clearTimeout(timer);
    const items = Array.isArray(content?.items) ? content.items : [];
    if (!items.length) {
      root.innerHTML = '<div id="status">Kein aktiver Inhalt – Standardanzeige bleibt aktiv.</div>';
      timer = window.setTimeout(refresh, 15000);
      return;
    }
    const item = items[index % items.length];
    root.innerHTML = render(item);
    index = (index + 1) % items.length;
    timer = window.setTimeout(play, Math.max(1, Number(item.duration || 10)) * 1000);
  }

  async function refresh() {
    const valid = await load();
    if (!valid && !content) return setup();
    play();
  }

  async function heartbeat() {
    if (!token) return;
    await request('heartbeat', {
      method: 'POST',
      body: JSON.stringify({
        playerVersion: 'TYPO3 Web Player 3.1.3',
        viewport: {width: innerWidth, height: innerHeight, pixelRatio: devicePixelRatio || 1},
      }),
    }).catch(() => {});
  }

  if (!uuid || !token) setup();
  else refresh();
  window.setInterval(refresh, 60000);
  window.setInterval(heartbeat, 30000);
  heartbeat();
})();
