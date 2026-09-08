/* wawoo-cms image lightbox (vanilla, no deps) */
(function () {
    'use strict';
    var sel = '.post-content a.post-zoom, .post-cover a.post-zoom';
    var els = document.querySelectorAll(sel);
    if (!els.length || typeof HTMLDialogElement === 'undefined') {
        return;
    }
    var dlg = document.getElementById('wawoo-lightbox');
    if (!dlg) {
        dlg = document.createElement('dialog');
        dlg.id = 'wawoo-lightbox';
        dlg.addEventListener('click', function (e) {
            if (e.target === dlg) { dlg.close(); }
        });
        document.body.appendChild(dlg);
    }
    els.forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var img = document.createElement('img');
            img.src = a.getAttribute('href') || a.querySelector('img').src;
            img.alt = '';
            dlg.innerHTML = '';
            dlg.appendChild(img);
            var cap = a.parentElement.querySelector('figcaption');
            if (cap) {
                var p = document.createElement('p');
                p.className = 'lb-caption';
                p.textContent = cap.textContent;
                dlg.appendChild(p);
            }
            dlg.showModal();
        });
    });
})();
