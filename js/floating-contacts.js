(() => {
    'use strict';
    function init() {
        const source = document.querySelector('#contacts .contacts-container');
        if (!source || document.getElementById('floating-contacts')) return;
        const bar = document.createElement('nav');
        bar.id = 'floating-contacts';
        bar.className = 'floating-contacts';
        bar.setAttribute('aria-label', 'Facebook, Instagram, Viber, Telegram');
        bar.hidden = true;
        document.body.appendChild(bar);
        let scheduled = false;
        function visible(element) {
            return element && getComputedStyle(element).display !== 'none' && getComputedStyle(element).visibility !== 'hidden';
        }
        function position() {
            scheduled = false;
            const focused = document.activeElement;
            const editing = focused && (focused.matches('input, textarea, select') || focused.isContentEditable);
            const modal = visible(document.getElementById('cbch_modal_callback_background')) || visible(document.getElementById('cbch_modal_callback')) || visible(document.getElementById('imageModal'));
            // Stay hidden after reaching the original row, including throughout the footer.
            bar.hidden = !bar.children.length || source.getBoundingClientRect().top < window.innerHeight - 16 || editing || modal;
            if (bar.hidden) return;
            bar.style.removeProperty('bottom');
            const callback = document.getElementById('cbch_modal_callback_button');
            if (visible(callback)) {
                const a = bar.getBoundingClientRect(), b = callback.getBoundingClientRect();
                if (b.width && b.height && a.left < b.right + 12 && a.right > b.left - 12 && a.top < b.bottom + 12 && a.bottom > b.top - 12) {
                    bar.style.bottom = Math.max(16, window.innerHeight - b.top + 12) + 'px';
                }
            }
        }
        function schedule() {
            if (!scheduled) { scheduled = true; requestAnimationFrame(position); }
        }
        function syncLinks() {
            const links = [];
            source.querySelectorAll('a[href]').forEach(original => {
                const img = original.querySelector('img');
                if (!img || !/^(Facebook|Instagram|Viber|Telegram)$/i.test(img.alt)) return;
                const link = document.createElement('a');
                link.href = original.href;
                if (original.target) link.target = original.target;
                if (original.rel) link.rel = original.rel;
                link.setAttribute('aria-label', img.alt);
                link.title = img.alt;
                const icon = document.createElement('img');
                icon.src = img.src;
                icon.alt = '';
                icon.width = 40; icon.height = 40;
                link.appendChild(icon); links.push(link);
            });
            bar.replaceChildren(...links);
            schedule();
        }
        syncLinks();
        new MutationObserver(syncLinks).observe(source, {subtree:true, childList:true, attributes:true, attributeFilter:['href','src','target','rel']});
        window.addEventListener('scroll', schedule, {passive:true});
        window.addEventListener('resize', schedule);
        document.addEventListener('focusin', schedule);
        document.addEventListener('focusout', schedule);
        if (window.visualViewport) window.visualViewport.addEventListener('resize', schedule);
        const watched = new WeakSet();
        const resize = typeof ResizeObserver === 'function' ? new ResizeObserver(schedule) : null;
        const stateObserver = new MutationObserver(schedule);
        function watchWidgets() {
            ['cbch_modal_callback_button','cbch_modal_callback','cbch_modal_callback_background','imageModal'].forEach(id => {
                const element = document.getElementById(id);
                if (!element || watched.has(element)) return;
                watched.add(element);
                stateObserver.observe(element, {attributes:true, attributeFilter:['class','style','hidden']});
                if (resize) resize.observe(element);
            });
            schedule();
        }
        // The callback provider inserts its widget asynchronously.
        new MutationObserver(watchWidgets).observe(document.body, {childList:true});
        watchWidgets();
        if (resize) { resize.observe(source); resize.observe(document.body); }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
