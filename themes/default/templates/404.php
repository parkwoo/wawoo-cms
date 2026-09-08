<?php /** @var array $vars */
if (!function_exists('t')) {
    function t(string $key, string $default = ''): string
    {
        return $default !== '' ? $default : $key;
    }
}
?>
<div class="error-page">
    <p class="code">404</p>
    <h1><?= t('e404.title', 'Not found') ?></h1>
    <p><?= t('e404.body', 'The page you requested does not exist.') ?></p>
    <p><a href="<?= htmlspecialchars(BASE_URL) ?>">&larr; <?= t('e404.home', 'Back to home') ?></a></p>
</div>
