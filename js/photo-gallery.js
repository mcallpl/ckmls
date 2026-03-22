/* ============================================================
   photo-gallery.js — Universal photo cycling module
   Used by: cards.js (app), p.php (public detail page)

   API:
     PhotoGallery.create(container, photos, options)
       container: DOM element to render into
       photos:    array of image URLs
       options:   { lazy: true, showCounter: true }
       Returns:   gallery instance { el, prev, next, goTo }

     PhotoGallery.step(uid, dir)
       Step gallery by +1 or -1
   ============================================================ */

var PhotoGallery = (function() {
    'use strict';

    var _store = {};

    function create(container, photos, options) {
        options = options || {};
        var lazy = options.lazy !== false;
        var showCounter = options.showCounter !== false;

        // Ensure photos is a clean array — remove nulls, empty strings, duplicates
        photos = (photos && photos.length)
            ? photos.filter(function(u) { return u && typeof u === 'string' && u.length > 5; })
            : [];
        // Remove duplicates
        photos = photos.filter(function(u, i, arr) { return arr.indexOf(u) === i; });

        var wrap = document.createElement('div');
        wrap.className = 'pc-wrap' + (photos.length === 0 ? ' no-photo' : '');
        container.appendChild(wrap);

        // No photos — placeholder
        if (photos.length === 0) {
            var icon = document.createElement('div');
            icon.textContent = '\uD83C\uDFE0'; // 🏠
            icon.className = 'np-icon';
            wrap.appendChild(icon);
            return { el: wrap };
        }

        // Image
        var img = document.createElement('img');
        img.src = photos[0];
        img.alt = 'Property photo';
        img.className = 'pc-img';
        if (lazy) img.loading = 'lazy';

        // Handle broken images gracefully
        img.onerror = function() {
            this.onerror = null;
            this.style.display = 'none';
            if (!wrap.querySelector('.np-icon')) {
                var ph = document.createElement('div');
                ph.textContent = '\uD83C\uDFE0';
                ph.className = 'np-icon';
                ph.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:3rem;';
                wrap.appendChild(ph);
            }
        };
        wrap.appendChild(img);

        // Single photo — no controls needed
        if (photos.length < 2) {
            return { el: wrap };
        }

        // Generate unique ID
        var uid = 'pg_' + Math.random().toString(36).slice(2, 10);
        _store[uid] = { photos: photos, idx: 0, img: img, counter: null };

        // Prev button
        var prev = document.createElement('button');
        prev.className = 'pc-prev';
        prev.innerHTML = '&#8249;';
        prev.title = 'Previous photo';
        prev.setAttribute('type', 'button');
        prev.onclick = function(e) { e.stopPropagation(); e.preventDefault(); step(uid, -1); };
        wrap.appendChild(prev);

        // Next button
        var next = document.createElement('button');
        next.className = 'pc-next';
        next.innerHTML = '&#8250;';
        next.title = 'Next photo';
        next.setAttribute('type', 'button');
        next.onclick = function(e) { e.stopPropagation(); e.preventDefault(); step(uid, 1); };
        wrap.appendChild(next);

        // Counter badge
        if (showCounter) {
            var ctr = document.createElement('div');
            ctr.className = 'pc-count';
            ctr.innerHTML = '<span class="pc-cur">1</span> / ' + photos.length;
            _store[uid].counter = ctr;
            wrap.appendChild(ctr);
        }

        // Touch swipe support
        var touchStartX = 0;
        wrap.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        wrap.addEventListener('touchend', function(e) {
            var diff = e.changedTouches[0].screenX - touchStartX;
            if (Math.abs(diff) > 40) {
                step(uid, diff > 0 ? -1 : 1);
            }
        }, { passive: true });

        return { el: wrap, uid: uid, prev: prev, next: next };
    }

    function step(uid, dir) {
        var s = _store[uid];
        if (!s) return;
        s.idx = (s.idx + dir + s.photos.length) % s.photos.length;
        s.img.src = s.photos[s.idx];
        s.img.style.display = '';  // restore if previously hidden by error handler
        var cur = s.counter ? s.counter.querySelector('.pc-cur') : null;
        if (cur) cur.textContent = s.idx + 1;
    }

    function goTo(uid, index) {
        var s = _store[uid];
        if (!s) return;
        s.idx = Math.max(0, Math.min(index, s.photos.length - 1));
        s.img.src = s.photos[s.idx];
        s.img.style.display = '';
        var cur = s.counter ? s.counter.querySelector('.pc-cur') : null;
        if (cur) cur.textContent = s.idx + 1;
    }

    return { create: create, step: step, goTo: goTo };
})();
