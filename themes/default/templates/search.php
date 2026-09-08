<?php
/**
 * @var array $vars
 *  q, posts, stats
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
$fmt   = static fn(string $d): string => ($t = strtotime($d)) ? date('F j, Y', $t) : $d;
?>
<header class="index-head">
    <h1><?= t('search.title', 'Search') ?></h1>
</header>

<form class="search-form" method="get" action="<?= htmlspecialchars(BASE_URL) ?>" role="search">
    <input class="search-input" type="search" name="q" value="<?= htmlspecialchars($q) ?>"
           placeholder="<?= t('search.placeholder', 'Search...') ?>" aria-label="<?= t('search.placeholder', 'Search...') ?>">
    <button class="btn" type="submit"><?= t('search.submit', 'Search') ?></button>
</form>

<?php if ($q === ''): ?>
    <p class="search-intro"><?= t('search.no_query', 'Type a query above.') ?></p>
<?php elseif (empty($posts)): ?>
    <p class="search-intro"><?= sprintf(t('search.empty', 'No results for <strong>%s</strong>.'), htmlspecialchars($q)) ?></p>
<?php else: ?>
    <p class="search-intro"><?= sprintf(t('search.results', '%1$d result(s) for <strong>%2$s</strong>:'), count($posts), htmlspecialchars($q)) ?></p>
    <div class="post-list search-results">
        <?php foreach ($posts as $post): ?>
            <article class="post-card post-card--plain">
                <div class="post-card-body">
                    <p class="meta">
                        <span><?= htmlspecialchars($post['category']) ?></span>
                        <span class="meta-sep" aria-hidden="true">&middot;</span>
                        <time datetime="<?= htmlspecialchars(date('c', strtotime($post['date']))) ?>"><?= htmlspecialchars($fmt($post['date'])) ?></time>
                    </p>
                    <h2 class="post-title"><a href="<?= htmlspecialchars(BASE_URL) ?>post/<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
                    <?php if (!empty($post['excerpt'])): ?>
                        <p class="excerpt"><?= htmlspecialchars($post['excerpt']) ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php $__pgnav = \Wawoo\Core\Hook::filter('pagination.render', null, $vars); ?>
<?php if (is_string($__pgnav) && $__pgnav !== ''): ?>
    <?= $__pgnav ?>
<?php endif; ?>

<?php Hook::fire('theme.search.after', $vars); ?>
