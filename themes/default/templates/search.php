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
?>
<h1><?= t('search.title', 'Search') ?></h1>
<form method="get" action="<?= htmlspecialchars(BASE_URL) ?>" style="margin-bottom:1.5rem">
    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= t('search.placeholder', 'Search...') ?>"
           style="padding:.5rem;width:60%;max-width:400px">
</form>

<?php if ($q === ''): ?>
    <p><?= t('search.no_query', 'Type a query above.') ?></p>
<?php elseif (empty($posts)): ?>
    <p><?= sprintf(t('search.empty', 'No results for <strong>%s</strong>.'), htmlspecialchars($q)) ?></p>
<?php else: ?>
    <p><?= sprintf(t('search.results', '%1$d result(s) for <strong>%2$s</strong>:'), count($posts), htmlspecialchars($q)) ?></p>
    <?php foreach ($posts as $post): ?>
        <article class="post-card">
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
