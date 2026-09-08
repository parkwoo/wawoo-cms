<?php
namespace Wawoo\Plugin\Contact;

/**
 * Admin settings page for the contact plugin, dispatched by the admin
 * console at /admin/?action=settings&plugin=contact (requires "settings":
 * true in plugin.json). Persists runtime settings to
 * CACHE_DIR/settings/contact.json which Config merges last, so the page
 * overrides both plugin.json defaults and $WAWOO_CONTACT globals.
 */
final class Settings
{
    private static ?string $lastError = null;

    /**
     * Last validation error from saveFromPost(), or null on success.
     */
    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    public function handle(?string $root = null): void
    {
        $cfg    = Config::all();
        $notice = '';
        $error  = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!\Wawoo\Plugin\Admin\Csrf::verify(\Wawoo\Plugin\Admin\Csrf::posted())) {
                $error = 'Invalid or missing CSRF token.';
            } else {
                $cfg = self::saveFromPost($_POST);
                $error = (string)self::lastError();
                if ($error === '') {
                    $notice = 'Settings saved.';
                }
            }
        }

        echo self::html($cfg, $notice, $error);
        exit;
    }

    /**
     * Validate a settings form POST and persist it. Returns the resulting
     * merged config array. When the recipient email is invalid nothing is
     * written and lastError() reports why.
     *
     * @param array $post Form fields: title, intro, recipient_email,
     *                    enable_mail, save_inbox, info_address, info_phone,
     *                    info_email, info_hours.
     */
    public static function saveFromPost(array $post): array
    {
        self::$lastError = null;

        $cfg = Config::all();

        $email = trim((string)($post['recipient_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            self::$lastError = 'Recipient email is not a valid email address.';
            return $cfg;
        }

        $settings = self::read();
        $settings['title']           = self::field($post, 'title', $cfg);
        $settings['intro']           = self::field($post, 'intro', $cfg);
        $settings['recipient_email'] = $email;
        $settings['enable_mail']     = !empty($post['enable_mail']);
        $settings['save_inbox']      = !empty($post['save_inbox']);
        $settings['info'] = [
            'address' => trim((string)($post['info_address'] ?? '')),
            'phone'   => trim((string)($post['info_phone'] ?? '')),
            'email'   => trim((string)($post['info_email'] ?? '')),
            'hours'   => trim((string)($post['info_hours'] ?? '')),
        ];

        self::write($settings);
        self::resetConfigCache();

        return Config::all();
    }

    private static function field(array $post, string $key, array $cfg): string
    {
        $value = trim((string)($post[$key] ?? ''));
        return $value !== '' ? $value : (string)($cfg[$key] ?? '');
    }

    private static function file(): string
    {
        return rtrim(\CACHE_DIR, '/\\') . DIRECTORY_SEPARATOR . 'settings'
            . DIRECTORY_SEPARATOR . 'contact.json';
    }

    private static function read(): array
    {
        $f = self::file();
        if (!is_file($f)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($f), true);
        return is_array($data) ? $data : [];
    }

    private static function write(array $settings): void
    {
        $dir = dirname(self::file());
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $f    = self::file();
        $lock = $f . '.lock';
        $tmp  = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $fh   = @fopen($lock, 'c');
        if ($fh === false) {
            @file_put_contents($f, json_encode($settings));
            return;
        }
        flock($fh, LOCK_EX);
        $ok = @file_put_contents($tmp, json_encode($settings)) !== false;
        if ($ok) {
            $ok = @rename($tmp, $f);
        }
        if (!$ok) {
            @unlink($tmp);
        }
        flock($fh, LOCK_UN);
        fclose($fh);
        @unlink($lock);
    }

    private static function resetConfigCache(): void
    {
        $r = new \ReflectionClass(Config::class);
        if ($r->hasProperty('cache')) {
            $p = $r->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue(null, null);
        }
    }

    private static function html(array $cfg, string $notice, string $error): string
    {
        $e = function (string $v): string {
            return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        };
        $title     = (string)($cfg['title'] ?? 'Contact us');
        $intro     = (string)($cfg['intro'] ?? '');
        $recipient = (string)($cfg['recipient_email'] ?? '');
        $mailOn    = !empty($cfg['enable_mail']) ? ' checked' : '';
        $inboxOn   = !empty($cfg['save_inbox']) ? ' checked' : '';
        $info      = is_array($cfg['info'] ?? null) ? $cfg['info'] : [];
        $addr      = (string)($info['address'] ?? '');
        $phone     = (string)($info['phone'] ?? '');
        $infoEmail = (string)($info['email'] ?? '');
        $hours     = (string)($info['hours'] ?? '');
        $count     = Store::count();

        $noticeHtml = $notice !== ''
            ? '<div class="ct-note" role="status">' . $e($notice) . '</div>'
            : '';
        $errorHtml = $error !== ''
            ? '<div class="ct-error" role="alert">' . $e($error) . '</div>'
            : '';
        $csrf = \Wawoo\Plugin\Admin\Csrf::field();

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Contact settings - Admin</title>
<style>
body {
    margin: 0; min-height: 100vh; padding: 2rem 1rem;
    font-family: -apple-system, system-ui, "Segoe UI", "Helvetica Neue", sans-serif;
    background: #f6f7f9; color: #14161a;
}
.wrap { max-width: 640px; margin: 0 auto; }
a.back { font-size: .85rem; color: #3b5bdb; text-decoration: none; }
h1 { font-size: 1.4rem; margin: .4rem 0 .25rem; }
.hint { margin: 0 0 1rem; color: #6e6e73; font-size: .9rem; }
.card {
    background: #fff; border-radius: 14px; padding: 1.6rem 1.8rem;
    box-shadow: 0 12px 34px rgba(15,23,42,.08);
}
label { display: block; font-size: .85rem; font-weight: 600; margin: 1rem 0 .3rem; }
label.inline { display: flex; align-items: center; gap: .5rem; font-size: .95rem; margin-top: 0; }
input[type="text"], input[type="email"], textarea {
    width: 100%; box-sizing: border-box;
    padding: .55rem .7rem; font-size: .95rem;
    border: 1px solid #d3d9e1; border-radius: 8px;
    background: #fff; color: inherit; font-family: inherit;
}
textarea { min-height: 80px; resize: vertical; }
button {
    margin-top: 1.4rem; border: 0; cursor: pointer;
    background: #3b5bdb; color: #fff; font-weight: 600;
    padding: .65rem 1.4rem; font-size: .95rem; border-radius: 8px;
}
button:hover { filter: brightness(1.08); }
.ct-note {
    background: #e8f5e9; color: #1e7d32; border: 1px solid #a5d6a7;
    padding: .6rem .8rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem;
}
.ct-error {
    background: #fdecea; color: #b3261e; border: 1px solid #ffb4ab;
    padding: .6rem .8rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem;
}
.inbox-count { margin: 1.25rem 0 0; padding-top: 1rem; border-top: 1px solid #e5e7eb; font-size: .9rem; }
</style>
</head>
<body>
<div class="wrap">
    <a class="back" href="/admin/?action=plugins">&larr; Back to plugins</a>
    <h1>Contact form</h1>
    <p class="hint">Configures the public /contact page served by this plugin.</p>
    <div class="card">
        {$noticeHtml}
        {$errorHtml}
        <form method="post" action="/admin/?action=settings&amp;plugin=contact">
            {$csrf}
            <label class="inline"><input type="checkbox" name="enable_mail" value="1"{$mailOn}> Send submissions by email</label>
            <label for="c-recipient">Recipient email</label>
            <input type="email" id="c-recipient" name="recipient_email" value="{$e($recipient)}" placeholder="you@example.com">
            <label class="inline"><input type="checkbox" name="save_inbox" value="1"{$inboxOn}> Archive submissions to the inbox</label>

            <label for="c-title">Page title</label>
            <input type="text" id="c-title" name="title" value="{$e($title)}">
            <label for="c-intro">Intro text</label>
            <textarea id="c-intro" name="intro">{$e($intro)}</textarea>

            <label for="c-addr">Info &mdash; address</label>
            <input type="text" id="c-addr" name="info_address" value="{$e($addr)}">
            <label for="c-phone">Info &mdash; phone</label>
            <input type="text" id="c-phone" name="info_phone" value="{$e($phone)}">
            <label for="c-info-email">Info &mdash; email</label>
            <input type="text" id="c-info-email" name="info_email" value="{$e($infoEmail)}">
            <label for="c-hours">Info &mdash; hours</label>
            <input type="text" id="c-hours" name="info_hours" value="{$e($hours)}">

            <button type="submit">Save settings</button>
        </form>
        <p class="inbox-count">Inbox: {$count} archived submission(s).</p>
    </div>
</div>
</body>
</html>
HTML;
    }
}
