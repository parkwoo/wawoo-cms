<?php
/**
 * Admin dashboard template.
 *
 * @var array $vars  posts, stats, pageTitle, plugins, themes, enabledPlugins, activeTheme, action
 */
use Wawoo\Core\Hook;

if (!function_exists('t')) {
    function t(string $key, string $default = ''): string
    {
        return $default !== '' ? $default : $key;
    }
}

$posts = $vars['posts'] ?? [];
$stats = $vars['stats'] ?? [];
$base  = (string)($vars['base'] ?? $_SERVER['PHP_SELF']);
$action = (string)($vars['action'] ?? 'dashboard');
$actForm = function (string $action, string $value, string $label, bool $confirm = false, string $field = 'slug', bool $danger = false) use ($base): string {
    $confirmAttr = '';
    if ($confirm) {
        $msg = sprintf(t('admin.confirm_delete', 'Delete %s?'), $value);
        $confirmAttr = ' onsubmit="return confirm(\'' . htmlspecialchars(addslashes($msg), ENT_QUOTES, 'UTF-8') . '\')"';
    }
    return '<form method="post" action="' . htmlspecialchars($base . '?action=' . $action) . '" class="act-form"' . $confirmAttr . '>'
        . '<input type="hidden" name="csrf" value="' . htmlspecialchars(\Wawoo\Plugin\Admin\Csrf::token(), ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="action" value="' . htmlspecialchars($action) . '">'
        . '<input type="hidden" name="' . htmlspecialchars($field) . '" value="' . htmlspecialchars($value) . '">'
        . '<button type="submit" class="link-btn' . ($danger ? ' link-btn--danger' : '') . '">' . htmlspecialchars($label) . '</button>'
        . '</form>';
};
?>
<header class="index-head">
    <h1 class="page-title"><?= htmlspecialchars($vars['pageTitle']) ?></h1>
</header>

<p class="admin-sub">
    <a href="<?= htmlspecialchars($base) ?>"><?= t('admin.dashboard', 'Dashboard') ?></a>
    <span class="meta-sep" aria-hidden="true">&middot;</span>
    <a href="<?= htmlspecialchars($base) ?>?action=plugins"><?= t('admin.plugins', 'Plugins') ?></a>
    <span class="meta-sep" aria-hidden="true">&middot;</span>
    <a href="<?= htmlspecialchars($base) ?>?action=themes"><?= t('admin.themes', 'Themes') ?></a>
    <span class="meta-sep" aria-hidden="true">&middot;</span>
    <form method="post" action="<?= htmlspecialchars($base) ?>" class="act-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(\Wawoo\Plugin\Admin\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="link-btn"><?= t('admin.logout', 'Logout') ?></button>
    </form>
</p>

<?php if ($action === 'plugins'): ?>
    <h2 class="section-title"><?= t('admin.plugins', 'Plugins') ?></h2>
    <table class="admin-table">
        <thead>
            <tr>
                <th><?= t('admin.name', 'Name') ?></th>
                <th><?= t('admin.version', 'Version') ?></th>
                <th><?= t('admin.description', 'Description') ?></th>
                <th><?= t('admin.status', 'Status') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (($vars['plugins'] ?? []) as $p): ?>
            <?php
                $name = htmlspecialchars((string)($p['name'] ?? ''));
                $version = htmlspecialchars((string)($p['version'] ?? ''));
                $desc = htmlspecialchars((string)($p['description'] ?? $p['_path'] ?? ''));
                $enabled = in_array($name, (array)($vars['enabledPlugins'] ?? []), true);
            ?>
            <tr>
                <td><?= $name ?></td>
                <td><?= $version ?></td>
                <td><?= $desc ?></td>
                <td class="cell-status">
                    <?php if ($enabled): ?>
                        <?= $actForm('disable-plugin', (string)($p['name'] ?? ''), t('admin.disable', 'Disable'), false, 'name', true) ?>
                    <?php else: ?>
                        <?= $actForm('enable-plugin', (string)($p['name'] ?? ''), t('admin.enable', 'Enable'), false, 'name') ?>
                    <?php endif; ?>
                    <?php if (!empty($p['settings'])): ?>
                        &nbsp;<a href="<?= htmlspecialchars($base) ?>?action=settings&amp;plugin=<?= urlencode($name) ?>"><?= t('admin.settings', 'Settings') ?></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($action === 'themes'): ?>
    <h2 class="section-title"><?= t('admin.themes', 'Themes') ?></h2>
    <table class="admin-table">
        <thead>
            <tr>
                <th><?= t('admin.name', 'Name') ?></th>
                <th><?= t('admin.version', 'Version') ?></th>
                <th><?= t('admin.description', 'Description') ?></th>
                <th><?= t('admin.status', 'Status') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (($vars['themes'] ?? []) as $t): ?>
            <?php
                $name = htmlspecialchars((string)($t['name'] ?? ''));
                $version = htmlspecialchars((string)($t['version'] ?? ''));
                $desc = htmlspecialchars((string)($t['description'] ?? $t['_path'] ?? ''));
                $active = ($name === (string)($vars['activeTheme'] ?? ''));
            ?>
            <tr>
                <td><?= $name ?></td>
                <td><?= $version ?></td>
                <td><?= $desc ?></td>
                <td class="cell-status">
                    <?php if ($active): ?>
                        <strong><?= t('admin.active', 'Active') ?></strong>
                    <?php else: ?>
                        <?= $actForm('enable-theme', (string)($t['name'] ?? ''), t('admin.activate', 'Activate'), false, 'name') ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php else: // dashboard ?>
    <p class="admin-stat">
        <?= sprintf(t('admin.stats', '<strong>%1$d</strong> posts, <strong>%2$d</strong> categories, <strong>%3$d</strong> tags.'), (int)($stats['post_count'] ?? 0), (int)($stats['category_count'] ?? 0), (int)($stats['tag_count'] ?? 0)) ?>
    </p>

    <?php if (empty($posts)): ?>
        <p class="admin-stat"><?= t('admin.no_posts', 'No posts yet. Drop a markdown file into <code>posts/</code> to get started.') ?></p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th><?= t('admin.title_col', 'Title') ?></th>
                <th><?= t('admin.date', 'Date') ?></th>
                <th><?= t('common.category', 'Category') ?></th>
                <th><?= t('admin.status', 'Status') ?></th>
                <th><?= t('admin.actions', 'Actions') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
            <?php $statusLabel = match ((string)($p['status'] ?? '')) {
                'published' => t('admin.status.published', 'published'),
                'draft'     => t('admin.status.draft', 'draft'),
                default     => (string)($p['status'] ?? ''),
            }; ?>
            <tr>
                <td>
                    <a href="<?= htmlspecialchars(BASE_URL) ?>post/<?= urlencode($p['slug']) ?>"><?= htmlspecialchars($p['title']) ?></a>
                </td>
                <td><?= htmlspecialchars($p['date']) ?></td>
                <td><?= htmlspecialchars($p['category']) ?></td>
                <td class="cell-status"><?= htmlspecialchars($statusLabel) ?></td>
                <td class="cell-actions">
                    <?php if (($p['status'] ?? '') !== 'published'): ?>
                        <?= $actForm('publish', $p['slug'], t('admin.publish', 'Publish')) ?>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($base) ?>?action=schedule&amp;slug=<?= urlencode($p['slug']) ?>"><?= t('admin.schedule', 'Schedule') ?></a>
                    <?= $actForm('toggle', $p['slug'], t('admin.toggle', 'Toggle')) ?>
                    <?= $actForm('delete', $p['slug'], t('admin.delete', 'Delete'), true, 'slug', true) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
<?php endif; ?>

<?php if (!empty($vars['admin_panel_comments'])) echo $vars['admin_panel_comments']; ?>

<?php Hook::fire('theme.admin.after', $vars); ?>
