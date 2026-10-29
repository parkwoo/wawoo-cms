<?php
/**
 * @var array $vars q, posts
 */
use Wawoo\Core\Hook;

if (!function_exists('t')) {
    function t(string $key, string $default = ''): string
    {
        return $default !== '' ? $default : $key;
    }
}

$q     = $vars['q']     ?? '';
$posts = $vars['posts'] ?? [];
?>
<header class="page-head">
    <h1><?= t('search.title', 'Search') ?></h1>
</header>

<form class="search-form" method="get" action="<?= htmlspecialchars(BASE_URL) ?>" role="search">
    <label class="visually-hidden" for="search-q"><?= t('search.label', 'Search query') ?></label>
    <input id="search-q" class="search-input" type="search" name="q" value="<?= htmlspecialchars($q) ?>"
           placeholder="<?= t('search.placeholder', 'Search...') ?>">
    <button class="btn" type="submit"><?= t('search.submit', 'Search') ?></button>
</form>

<?php if ($q === ''): ?>
<?php elseif (empty($posts)): ?>
    <div class="clay-card"><p><?= sprintf(t('search.empty', 'No results for <strong>%s</strong>.'), htmlspecialchars($q)) ?></p></div>
<?php else: ?>
    <p class="search-summary"><?= sprintf(t('search.results', '%1$d result(s) for <strong>%2$s</strong>:'), count($posts), htmlspecialchars($q)) ?></p>
    <?php foreach ($posts as $post): ?>
        <article class="clay-card post-card">
            <div class="post-card-body">
                <div class="meta">
                    <?php if (($post['category'] ?? '') !== ''): ?>
                        <a class="cat" href="<?= htmlspecialchars(BASE_URL) ?>?cat=<?= urlencode(strtolower($post['category'])) ?>"><?= htmlspecialchars($post['category']) ?></a>
                    <?php endif; ?>
                    <time datetime="<?= htmlspecialchars((string)$post['date']) ?>"><?= htmlspecialchars($post['date']) ?></time>
                </div>
                <h2 class="post-card-title"><a href="<?= htmlspecialchars(BASE_URL) ?>post/<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php $__pgnav = \Wawoo\Core\Hook::filter('pagination.render', null, $vars); ?>
<?php if (is_string($__pgnav) && $__pgnav !== ''): ?>
    <?= $__pgnav ?>
<?php endif; ?>

<?php Hook::fire('theme.search.after', $vars); ?>
