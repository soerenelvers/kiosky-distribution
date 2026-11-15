(function (root) {
    'use strict';
    if (new URLSearchParams(root.location.search).has('display') || !root.visualViewport)
        return;
    var scheduled = false, lastHeight = 0;
    function update() { scheduled = false; if (root.document.body && root.document.body.classList.contains('player-mode'))
        return; var view = root.visualViewport, height = Math.max(160, Math.min(root.innerHeight, view.height)), offset = Math.max(0, view.offsetTop || 0), style = root.document.documentElement.style; style.setProperty('--interactive-viewport-height', height + 'px'); style.setProperty('--interactive-viewport-offset', offset + 'px'); style.setProperty('--interactive-dialog-top', (offset + 12) + 'px'); if (Math.abs(lastHeight - height) > .5) {
        var smaller = lastHeight > height;
        lastHeight = height;
        root.dispatchEvent(new Event('resize'));
        var focused = root.document.activeElement;
        if (smaller && focused && focused.closest('dialog[open]') && /^(INPUT|TEXTAREA|SELECT)$/.test(focused.tagName))
            root.requestAnimationFrame(function () { focused.scrollIntoView({ block: 'nearest', inline: 'nearest' }); });
    } }
    function schedule() { if (!scheduled) {
        scheduled = true;
        root.requestAnimationFrame(update);
    } }
    root.visualViewport.addEventListener('resize', schedule);
    root.visualViewport.addEventListener('scroll', schedule);
    root.addEventListener('resize', schedule);
    schedule();
})(window);
