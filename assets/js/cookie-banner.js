// Cookie notice banner — dismissible, remembers dismissal via localStorage.
(function () {
  'use strict';
  const STORAGE_KEY = 'cookie_notice_v1_dismissed';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(() => {
    const banner = document.getElementById('cookie-banner');
    const dismiss = document.getElementById('cookie-banner-dismiss');
    if (!banner || !dismiss) return;

    try {
      if (localStorage.getItem(STORAGE_KEY) === 'true') return;
    } catch (e) {
      // localStorage blocked — show banner as fallback
    }

    banner.style.display = 'block';

    dismiss.addEventListener('click', () => {
      try { localStorage.setItem(STORAGE_KEY, 'true'); } catch (e) {}
      banner.remove();
    });
  });
})();
