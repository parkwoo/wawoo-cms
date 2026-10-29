<?php /** @var array $vars */
if (!function_exists('t')) {
    function t(string $key, string $default = ''): string
    {
        return $default !== '' ? $default : $key;
    }
}
?>
<section class="clay-card error-page">
    <p class="error-code" aria-hidden="true">404</p>
    <h1><?= t('e404.title', 'Not found') ?></h1>
    <p class="error-body"><?= t('e404.body', 'The page you requested does not exist.') ?></p>
    <p><a class="btn" href="<?= htmlspecialchars(BASE_URL) ?>">&larr; <?= t('e404.home', 'Back to home') ?></a></p>
</section>
