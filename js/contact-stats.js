(() => {
    'use strict';
    const lastClicks = new Map();
    function record(event) {
        if (!event.isTrusted || event.defaultPrevented || (event.type === 'auxclick' && event.button !== 1) || (event.type === 'click' && event.button !== 0)) return;
        const link = event.target.closest && event.target.closest('a[href]');
        if (!link) return;
        const place = link.closest('#floating-contacts') ? 'floating' : link.closest('footer') ? 'footer' : link.closest('#contacts') ? 'contacts' : null;
        if (!place) return;
        let url;
        try { url = new URL(link.href, location.href); } catch (_) { return; }
        const host = url.hostname.toLowerCase().replace(/^www\./, '');
        const web = url.protocol === 'https:' || url.protocol === 'http:';
        const service = url.protocol === 'viber:' ? 'viber' : url.protocol === 'tg:' ? 'telegram' : web && ['t.me', 'telegram.me'].includes(host) ? 'telegram' : web && ['facebook.com', 'm.facebook.com', 'fb.com'].includes(host) ? 'facebook' : web && host === 'instagram.com' ? 'instagram' : null;
        if (!service) return;
        const key = service + ':' + place, now = Date.now();
        if (lastClicks.has(key) && now - lastClicks.get(key) < 1000) return;
        lastClicks.set(key, now);
        const body = new URLSearchParams({service, place});
        // Never prevent navigation or wait for analytics before opening the contact.
        try {
            if (navigator.sendBeacon && navigator.sendBeacon('/contact-click.php', body)) return;
        } catch (_) {}
        try { fetch('/contact-click.php', {method:'POST', body, keepalive:true, credentials:'omit'}).catch(() => {}); } catch (_) {}
    }
    document.addEventListener('click', record);
    document.addEventListener('auxclick', record);
})();
