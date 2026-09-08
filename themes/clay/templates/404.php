<?php /** @var array $vars */
if (!function_exists('t')) {
    function t(string $key, string $default = ''): string
    {
        return $default !== '' ? $default : $key;
    }
}
?>
<div class="clay-card" style="text-align:center">
    <h1>404 — <?= t('e404.title', 'Not found') ?></h1>
    <p><?= t('e404.body', 'The page you requested does not exist.') ?></p>
    <p><a href="<?= htmlspecialchars(BASE_URL) ?>">&larr; <?= t('e404.home', 'Back to home') ?></a></p>
</div>
