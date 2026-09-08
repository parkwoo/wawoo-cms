<?php
/**
 * Admin dashboard template (clay variant).
 * @var array $vars posts, stats, pageTitle, plugins, themes, enabledPlugins, activeTheme, action
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
$actForm = function (string $action, string $value, string $label, bool $confirm = false, string $field = 'slug', string $color = '#8E7CC3') use ($base): string {
    $confirmAttr = '';
    if ($confirm) {
        $msg = sprintf(t('admin.confirm_delete', 'Delete %s?'), $value);
        $confirmAttr = ' onsubmit="return confirm(\'' . htmlspecialchars(addslashes($msg), ENT_QUOTES, 'UTF-8') . '\')"';
    }
    return '<form method="post" action="' . htmlspecialchars($base . '?action=' . $action) . '" style="display:inline"' . $confirmAttr . '>'
        . '<input type="hidden" name="csrf" value="' . htmlspecialchars(\Wawoo\Plugin\Admin\Csrf::token(), ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="action" value="' . htmlspecialchars($action) . '">'
        . '<input type="hidden" name="' . htmlspecialchars($field) . '" value="' . htmlspecialchars($value) . '">'
        . '<button type="submit" style="background:none;border:0;color:' . $color . ';cursor:pointer;padding:0;font:inherit;text-decoration:underline">' . htmlspecialchars($label) . '</button>'
        . '</form>';
};
?>
<h1><?= htmlspecialchars($vars['pageTitle']) ?></h1>

<div class="clay-card">
    <p>
        <a href="<?= htmlspecialchars($base) ?>"><?= t('admin.dashboard', 'Dashboard') ?></a>
        &middot;
        <a href="<?= htmlspecialchars($base) ?>?action=plugins"><?= t('admin.plugins', 'Plugins') ?></a>
        &middot;
        <a href="<?= htmlspecialchars($base) ?>?action=themes"><?= t('admin.themes', 'Themes') ?></a>
        &middot;
        <a href="<?= htmlspecialchars($base) ?>?logout=1&amp;csrf=<?= urlencode(\Wawoo\Plugin\Admin\Csrf::token()) ?>"><?= t('admin.logout', 'Logout') ?></a>
    </p>

    <?php if ($action === 'plugins'): ?>
        <h2><?= t('admin.plugins', 'Plugins') ?></h2>
        <table style="width:100%;border-collapse:collapse;margin-top:1rem">
            <thead>
                <tr>
                    <th style="text-align:left;padding:.5rem"><?= t('admin.name', 'Name') ?></th>
                    <th style="text-align:left;padding:.5rem"><?= t('admin.version', 'Version') ?></th>
                    <th style="text-align:left;padding:.5rem"><?= t('admin.description', 'Description') ?></th>
                    <th style="padding:.5rem;text-align:center"><?= t('admin.status', 'Status') ?></th>
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
                    <td style="padding:.5rem"><?= $name ?></td>
                    <td style="padding:.5rem"><?= $version ?></td>
                    <td style="padding:.5rem"><?= $desc ?></td>
                    <td style="padding:.5rem;text-align:center">
                        <?php if ($enabled): ?>
                            <?= $actForm('disable-plugin', (string)($p['name'] ?? ''), t('admin.disable', 'Disable'), false, 'name', '#b00020') ?>
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
        <h2><?= t('admin.themes', 'Themes') ?></h2>
        <table style="width:100%;border-collapse:collapse;margin-top:1rem">
            <thead>
                <tr>
                    <th style="text-align:left;padding:.5rem"><?= t('admin.name', 'Name') ?></th>
                    <th style="text-align:left;padding:.5rem"><?= t('admin.version', 'Version') ?></th>
                    <th style="text-align:left;padding:.5rem"><?= t('admin.description', 'Description') ?></th>
                    <th style="padding:.5rem;text-align:center"><?= t('admin.status', 'Status') ?></th>
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
                    <td style="padding:.5rem"><?= $name ?></td>
                    <td style="padding:.5rem"><?= $version ?></td>
                    <td style="padding:.5rem"><?= $desc ?></td>
                    <td style="padding:.5rem;text-align:center">
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
        <p>
            <?= sprintf(t('admin.stats', '<strong>%1$d</strong> posts, <strong>%2$d</strong> categories, <strong>%3$d</strong> tags.'), (int)($stats['post_count'] ?? 0), (int)($stats['category_count'] ?? 0), (int)($stats['tag_count'] ?? 0)) ?>
        </p>

        <?php if (empty($posts)): ?>
            <p><?= t('admin.no_posts', 'No posts yet. Drop a markdown file into <code>posts/</code> to get started.') ?></p>
        <?php else: ?>
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:.5rem"><?= t('admin.title_col', 'Title') ?></th>
                        <th style="text-align:left;padding:.5rem"><?= t('admin.date', 'Date') ?></th>
                        <th style="text-align:left;padding:.5rem"><?= t('common.category', 'Category') ?></th>
                        <th style="text-align:left;padding:.5rem"><?= t('admin.status', 'Status') ?></th>
                        <th style="padding:.5rem;text-align:right"><?= t('admin.actions', 'Actions') ?></th>
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
                        <td style="padding:.5rem">
                            <a href="<?= htmlspecialchars(BASE_URL) ?>post/<?= urlencode($p['slug']) ?>"><?= htmlspecialchars($p['title']) ?></a>
                        </td>
                        <td style="padding:.5rem"><?= htmlspecialchars($p['date']) ?></td>
                        <td style="padding:.5rem"><?= htmlspecialchars($p['category']) ?></td>
                        <td style="padding:.5rem"><?= htmlspecialchars($statusLabel) ?></td>
                        <td style="padding:.5rem;text-align:right">
                            <?php if (($p['status'] ?? '') !== 'published'): ?>
                                <?= $actForm('publish', $p['slug'], t('admin.publish', 'Publish')) ?>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($base) ?>?action=schedule&amp;slug=<?= urlencode($p['slug']) ?>"><?= t('admin.schedule', 'Schedule') ?></a>
                            <?= $actForm('toggle', $p['slug'], t('admin.toggle', 'Toggle')) ?>
                            <?= $actForm('delete', $p['slug'], t('admin.delete', 'Delete'), true) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (!empty($vars['admin_panel_comments'])) echo $vars['admin_panel_comments']; ?>

<?php Hook::fire('theme.admin.after', $vars); ?>
