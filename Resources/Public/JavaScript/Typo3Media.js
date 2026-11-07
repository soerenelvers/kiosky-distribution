/* Native TYPO3 13/14 element browser; FAL access remains enforced by TYPO3. */
(() => {
  'use strict';
  let pending = false;
  const images = 'jpg,jpeg,png,gif,webp,avif,bmp,tif,tiff,apng,ico,heic,heif';
  const videos = 'mp4,webm,ogv,ogg,mov,m4v,mkv,avi';
  window.KioskyTypo3MediaPicker = Object.freeze({
    open({ type = '', title = 'TYPO3-Dateibrowser', multiple = false } = {}) {
      if (pending) return Promise.reject(new Error('Die TYPO3-Dateiauswahl ist bereits geöffnet.'));
      const endpoint = document.querySelector('meta[name="kiosky-native-media-browser-url"]')?.content;
      const host = window.top.TYPO3?.Modal;
      if (!endpoint || typeof host?.advanced !== 'function') return Promise.reject(new Error('Bitte die Dateiauswahl im Kiosky-Backendmodul von TYPO3 öffnen.'));
      const field = `kiosky-media-${crypto.randomUUID()}`;
      const extensions = type === 'video' ? videos : type === 'image-only' ? images : type === 'image' ? images + ',svg' : images + ',svg,' + videos;
      const url = new URL(endpoint, window.location.href);
      if (url.origin !== window.location.origin) return Promise.reject(new Error('Ungültige TYPO3-Dateiauswahl.'));
      url.searchParams.set('mode', 'file');
      url.searchParams.set('bparams', `${field}|||allowed=${extensions}`);
      // TYPO3's element browser resolves its target through the native modal's
      // iframe and the backend list_frame. Standalone popup windows do not
      // implement that contract in supported TYPO3 releases.
      const modal = host.advanced({ type: 'iframe', size: 'full', title, content: url.href });
      pending = true;
      return new Promise((resolve, reject) => {
        const ids = new Set();
        let finished = false;
        const finish = (error, hide = false) => {
          if (finished) return;
          finished = true;
          pending = false;
          window.removeEventListener('message', onMessage);
          window.removeEventListener('pagehide', onUnload);
          modal.removeEventListener('typo3-modal-hidden', onClose);
          if (hide) modal.hideModal();
          if (error) reject(error);
          else resolve(ids.size ? (multiple ? [...ids] : [...ids][0]) : null);
        };
        const onClose = () => finish();
        const onUnload = () => finish(undefined, true);
        const onMessage = event => {
          const data = event.data, source = modal.querySelector('iframe.t3js-modal-iframe')?.contentWindow;
          if (!source || event.origin !== window.location.origin || event.source !== source || data?.actionName !== 'typo3:elementBrowser:elementAdded' || data.fieldName !== field) return;
          const value = String(data.value ?? '').match(/^(?:sys_file_)?([1-9]\d*)$/);
          const id = value ? Number(value[1]) : NaN;
          if (!Number.isSafeInteger(id)) { finish(new Error('Ungültige TYPO3-Dateiauswahl.'), true); return; }
          ids.add(id);
          if (ids.size > 100) { finish(new Error('Bitte höchstens 100 Dateien auswählen.'), true); return; }
          // Native batch import waits for an acknowledgement after each file.
          source.postMessage({ actionName: 'typo3:foreignRelation:inserted' }, window.location.origin);
          // Let TYPO3 dismiss its own modal before resolving: dismissing it here
          // can race the core's deferred close and accidentally close its parent.
        };
        window.addEventListener('message', onMessage);
        window.addEventListener('pagehide', onUnload, { once: true });
        modal.addEventListener('typo3-modal-hidden', onClose, { once: true });
      });
    },
  });
})();
