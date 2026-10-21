(() => {
  const current = window.KioskyRuntime || {};
  const meta = name => document.querySelector(`meta[name="${name}"]`)?.content || '';
  const requestHeaders = { ...(current.requestHeaders || {}) };
  const nonce = meta('kiosky-request-header-x-wp-nonce');
  if (nonce) requestHeaders['X-WP-Nonce'] = nonce;
  window.KioskyRuntime = Object.freeze({
    platform: meta('kiosky-platform') || 'standalone',
    apiBaseUrl: meta('kiosky-api-base-url'),
    apiEndpoint: meta('kiosky-api-endpoint'),
    externalApiBaseUrl: meta('kiosky-external-api-base-url'),
    assetBaseUrl: meta('kiosky-asset-base-url'),
    playerBaseUrl: meta('kiosky-player-base-url'),
    menuBaseUrl: meta('kiosky-menu-base-url'),
    playerCenterUrl: meta('kiosky-player-center-url'),
    initialView: meta('kiosky-initial-view'),
    requestHeaders,
    ...current
  });
})();
