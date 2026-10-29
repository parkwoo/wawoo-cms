<?php
/**
 * @var array $vars
 *  post, posts (optional sibling list), stats, ...
 */
use Wawoo\Core\Hook;

if (!function_exists('t')) {
    function t(string $key, string $default = ''): string
    {
        return $default !== '' ? $default : $key;
    }
}

$post    = $vars['post'];
$fmt     = static fn(string $d): string => ($t = strtotime($d)) ? date('F j, Y', $t) : $d;
$postUrl = static fn(string $slug): string => htmlspecialchars(BASE_URL . 'post/' . urlencode($slug));
$catUrl  = static fn(string $cat): string => htmlspecialchars(BASE_URL . '?cat=' . urlencode(strtolower($cat)));
$tagUrl  = static fn(string $tag): string => htmlspecialchars(BASE_URL . '?tag=' . urlencode(strtolower($tag)));

$siblings = isset($vars['posts']) && is_array($vars['posts']) && $vars['posts'] !== []
    ? $vars['posts']
    : \Wawoo\Core\Posts::all();
$older = null;
$newer = null;
$idx   = null;
foreach ($siblings as $i => $p) {
    if (($p['slug'] ?? '') === $post['slug']) {
        $idx = $i;
        break;
    }
}
if ($idx !== null) {
    foreach (array_slice($siblings, $idx + 1) as $p) {
        if (($p['slug'] ?? '') !== $post['slug']) {
            $older = $p;
            break;
        }
    }
    for ($i = $idx - 1; $i >= 0; --$i) {
        if (($siblings[$i]['slug'] ?? '') !== $post['slug']) {
            $newer = $siblings[$i];
            break;
        }
    }
}

$coverCaption = (string)($post['meta']['cover_caption'] ?? '');
$coverAlt     = (string)($post['meta']['cover_alt'] ?? $post['title']);
?>
<div class="reading-progress" aria-hidden="true"><span id="reading-progress-bar"></span></div>

<nav class="crumbs" role="navigation" aria-label="<?= t('a11y.breadcrumb', 'Breadcrumb') ?>">
    <a href="<?= htmlspecialchars(BASE_URL) ?>"><?= t('nav.home', 'Home') ?></a>
    <span class="crumb-sep" aria-hidden="true">/</span>
    <a href="<?= $catUrl($post['category']) ?>"><?= htmlspecialchars($post['category']) ?></a>
</nav>

<div class="article-grid">
    <article class="post-content">
        <header class="article-head">
            <p class="post-eyebrow">
                <a class="post-cat" href="<?= $catUrl($post['category']) ?>"><?= htmlspecialchars($post['category']) ?></a>
            </p>
            <h1><?= htmlspecialchars($post['title']) ?></h1>
            <?php if ($post['excerpt'] !== ''): ?>
                <p class="article-standfirst"><?= htmlspecialchars($post['excerpt']) ?></p>
            <?php endif; ?>
            <p class="meta article-byline">
                <?php if ($post['author'] !== ''): ?>
                    <span><?= t('common.by', 'by') ?> <?= htmlspecialchars($post['author']) ?></span>
                    <span class="meta-sep" aria-hidden="true">&middot;</span>
                <?php endif; ?>
                <time datetime="<?= htmlspecialchars(date('c', strtotime($post['date']))) ?>"><?= htmlspecialchars($fmt($post['date'])) ?></time>
            </p>
        </header>

        <details class="toc-fold" id="toc-fold" data-toc-label="<?= t('post.toc', 'Table of contents') ?>" hidden>
            <summary><?= t('post.contents', 'Contents') ?></summary>
            <div id="toc-fold-list"></div>
        </details>

        <?php if (!empty($post['cover'])): ?>
            <figure class="post-cover">
                <a class="post-zoom" href="<?= htmlspecialchars($post['cover']) ?>">
                    <img src="<?= htmlspecialchars($post['cover']) ?>" alt="<?= htmlspecialchars($coverAlt) ?>"
                         loading="eager" fetchpriority="high" decoding="async">
                </a>
                <?php if ($coverCaption !== ''): ?>
                    <figcaption><?= htmlspecialchars($coverCaption) ?></figcaption>
                <?php endif; ?>
            </figure>
        <?php endif; ?>

        <div class="post-body" id="post-body">
            <?= Hook::filter('post.html', $post['html'], $post) ?>
        </div>
    </article>

    <aside class="toc-aside" id="toc-aside" hidden></aside>
</div>

<footer class="post-footer">
    <?php if (!empty($post['tags'])): ?>
        <div class="post-tags" aria-label="<?= t('post.tags', 'Tags') ?>">
            <?php foreach ($post['tags'] as $tag): ?>
                <a class="tag" href="<?= $tagUrl($tag) ?>">#<?= htmlspecialchars($tag) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($older !== null || $newer !== null): ?>
        <nav class="post-nav" role="navigation" aria-label="<?= t('post.pager', 'Post navigation') ?>">
            <div class="post-nav-item post-nav-prev">
                <?php if ($older !== null): ?>
                    <a href="<?= $postUrl($older['slug']) ?>">
                        <span class="post-nav-label"><?= t('post.older', 'Older') ?></span>
                        <span class="post-nav-title"><?= htmlspecialchars($older['title']) ?></span>
                    </a>
                <?php endif; ?>
            </div>
            <div class="post-nav-item post-nav-next">
                <?php if ($newer !== null): ?>
                    <a href="<?= $postUrl($newer['slug']) ?>">
                        <span class="post-nav-label"><?= t('post.newer', 'Newer') ?></span>
                        <span class="post-nav-title"><?= htmlspecialchars($newer['title']) ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>

    <p class="back-to-top"><a href="#wawoo-top"><?= t('post.back_top', 'Back to top') ?> &uarr;</a></p>
</footer>

<script<?= \Wawoo\Core\Csp::attr() ?>>
(function () {
    'use strict';
    var body = document.getElementById('post-body');
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var esc = function (s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    if (body) {
        var headings = body.querySelectorAll('h2, h3');
        if (headings.length) {
            var seen = {};
            var html = '';
            headings.forEach(function (h) {
                var id = h.id;
                if (!id) {
                    id = (h.textContent || 'section').toLowerCase().replace(/[^\w\s-]/g, '').trim().replace(/\s+/g, '-') || 'section';
                    var base = id;
                    var n = 2;
                    while (seen[id]) { id = base + '-' + n++; }
                    seen[id] = true;
                    h.id = id;
                }
                html += '<li class="toc-' + h.tagName.toLowerCase() + '"><a href="#' + id + '">' + esc((h.textContent || '').trim()) + '</a></li>';
            });
            var nav = '';
            var foldList = document.getElementById('toc-fold-list');
            var aside = document.getElementById('toc-aside');
            if (foldList || aside) {
                var label = (foldList || aside).parentElement.getAttribute('data-toc-label') || 'Table of contents';
                nav = '<nav class="toc-list" role="navigation" aria-label="' + esc(label) + '"><ol>' + html + '</ol></nav>';
            }
            if (foldList) { foldList.innerHTML = nav; document.getElementById('toc-fold').removeAttribute('hidden'); }
            if (aside) { aside.innerHTML = nav; aside.removeAttribute('hidden'); }
        }
    }
    var bar = document.getElementById('reading-progress-bar');
    if (bar) {
        var ticking = false;
        var update = function () {
            var doc = document.documentElement;
            var max = doc.scrollHeight - doc.clientHeight;
            var pct = max > 0 ? (window.pageYOffset || doc.scrollTop) / max : 0;
            bar.style.width = (pct * 100).toFixed(2) + '%';
            ticking = false;
        };
        window.addEventListener('scroll', function () {
            if (!ticking) {
                ticking = true;
                if (reduce || !window.requestAnimationFrame) { update(); }
                else { window.requestAnimationFrame(update); }
            }
        }, { passive: true });
        update();
    }
})();
</script>

<?php Hook::fire('theme.post.after', $vars); ?>
