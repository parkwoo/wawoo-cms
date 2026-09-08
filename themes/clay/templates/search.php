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
<div class="clay-card">
    <h1><?= t('search.title', 'Search') ?></h1>
    <form method="get" action="<?= htmlspecialchars(BASE_URL) ?>">
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= t('search.placeholder', 'Search...') ?>"
               style="width:100%;padding:0.75rem;border:0;border-radius:1rem;background:#FFF8E5;font-size:1rem;outline:none">
    </form>
</div>

<?php if ($q === ''): ?>
<?php elseif (empty($posts)): ?>
    <div class="clay-card"><p><?= sprintf(t('search.empty', 'No results for <strong>%s</strong>.'), htmlspecialchars($q)) ?></p></div>
<?php else: ?>
    <p><?= sprintf(t('search.results', '%1$d result(s) for <strong>%2$s</strong>:'), count($posts), htmlspecialchars($q)) ?></p>
    <?php foreach ($posts as $post): ?>
        <article class="clay-card post-card">
            <h2><a href="<?= htmlspecialchars(BASE_URL) ?>post/<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
            <div class="meta"><time><?= htmlspecialchars($post['date']) ?></time></div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php $__pgnav = \Wawoo\Core\Hook::filter('pagination.render', null, $vars); ?>
<?php if (is_string($__pgnav) && $__pgnav !== ''): ?>
    <?= $__pgnav ?>
<?php endif; ?>

<?php Hook::fire('theme.search.after', $vars); ?>
