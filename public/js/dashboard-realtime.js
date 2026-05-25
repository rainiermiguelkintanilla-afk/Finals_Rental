/**
 * Polls Symfony dashboard realtime events and refreshes listings when data changes.
 */
(function () {
  let since = 0;
  /** Skip notifications for events already in cache when this page loaded. */
  let bootstrapped = false;
  const pollMs = 3000;

  function notify(message) {
    const existing = document.getElementById('realtime-banner');
    if (existing) {
      existing.remove();
    }
    const banner = document.createElement('div');
    banner.id = 'realtime-banner';
    banner.textContent = message;
    banner.style.cssText =
      'position:fixed;top:80px;right:20px;z-index:9999;background:#0f172a;color:#fff;padding:12px 16px;border-radius:8px;border:2px solid #20b2aa;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.2)';
    document.body.appendChild(banner);
    setTimeout(() => banner.remove(), 4000);
  }

  function shouldReload(type) {
    const path = window.location.pathname;
    if (type.startsWith('apartment.') && path.includes('/dashboard/apartments')) {
      return true;
    }
    if (type.startsWith('booking.') && path.includes('/rentals')) {
      return true;
    }
    return false;
  }

  async function poll() {
    try {
      const res = await fetch(`/dashboard/realtime/events?since=${since}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) {
        return;
      }
      const body = await res.json();
      const events = body.events || [];

      if (!bootstrapped) {
        for (const event of events) {
          since = Math.max(since, event.id || 0);
        }
        bootstrapped = true;
        return;
      }

      for (const event of events) {
        since = Math.max(since, event.id || 0);
        const label = (event.type || 'update').replace('.', ' ');
        notify(`Live update: ${label}`);
        if (shouldReload(event.type)) {
          window.location.reload();
          return;
        }
      }
    } catch {
      /* offline */
    }
  }

  setInterval(poll, pollMs);
  poll();
})();
