<?php
namespace Wawoo\Plugin\Contact;

/**
 * Serves the public /contact page. Hooks `core.request`; for any other
 * path it returns immediately so normal routing proceeds. Renders its own
 * self-contained HTML (theme-independent) and always exits once claimed.
 */
final class Page
{
    public function handle(?string $root = null): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($path !== '/contact' && $path !== '/contact/') {
            return;
        }
        $cfg = Config::all();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->post($cfg);
        } else {
            $this->render($cfg, [], [], false);
        }
        exit;
    }

    private function post(array $cfg): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $labels = Config::labels();
        $values = $this->postedValues();
        $csrf   = isset($_POST['contact_csrf']) ? (string)$_POST['contact_csrf'] : null;

        if (!Form::csrfValid($csrf)) {
            $error = is_string($labels['error_csrf'] ?? null) && $labels['error_csrf'] !== ''
                ? (string)$labels['error_csrf']
                : 'Your session has expired. Please try again.';
            $this->render($cfg, $values, [$error], false);
            return;
        }

        // Honeypot: pretend success so bots do not learn they were caught.
        if (trim((string)($_POST['website'] ?? '')) !== '') {
            $this->render($cfg, [], [], true);
            return;
        }

        $ip = preg_replace('/[^0-9a-fA-F.:]/', '', (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        if ($ip === '') {
            $ip = '0.0.0.0';
        }
        if (Form::rateLimited($cfg, $ip)) {
            $error = is_string($labels['error_rate_limited'] ?? null) && $labels['error_rate_limited'] !== ''
                ? (string)$labels['error_rate_limited']
                : 'Too many messages. Please try again later.';
            $this->render($cfg, $values, [$error], false);
            return;
        }
        Form::recordAttempt($cfg, $ip);

        $errors = Form::validate($_POST, $labels);
        if ($errors !== []) {
            $this->render($cfg, $values, $errors, false);
            return;
        }

        if (!Form::sendMail(Form::buildSubmission($_POST), $cfg)) {
            $error = is_string($labels['error_send_failed'] ?? null) && $labels['error_send_failed'] !== ''
                ? (string)$labels['error_send_failed']
                : 'Sorry, your message could not be sent. Please try again later.';
            $this->render($cfg, $values, [$error], false);
            return;
        }
        // Rotate the session id and CSRF token after a successful submit
        // (mitigates session fixation and token reuse).
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        unset($_SESSION['wawoo_contact_csrf']);
        $this->render($cfg, [], [], true);
    }

    private function postedValues(): array
    {
        $type = (string)($_POST['type'] ?? 'product');
        if (!in_array($type, ['product', 'business', 'media', 'other'], true)) {
            $type = 'product';
        }
        return [
            'name'    => (string)($_POST['name'] ?? ''),
            'email'   => (string)($_POST['email'] ?? ''),
            'company' => (string)($_POST['company'] ?? ''),
            'message' => (string)($_POST['message'] ?? ''),
            'type'    => $type,
        ];
    }

    private function render(array $cfg, array $values, array $errors, bool $success): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $labels = Config::labels();
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $le = static function (string $key, string $fallback = '') use ($labels): string {
            $v = $labels[$key] ?? '';
            return htmlspecialchars(is_string($v) && $v !== '' ? $v : $fallback, ENT_QUOTES, 'UTF-8');
        };
        $jsEncode = static function (string $value): string {
            return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        };

        $title     = $e((string)($cfg['title'] ?? 'Contact us'));
        $intro     = $e((string)($cfg['intro'] ?? ''));
        $base      = defined('BASE_URL') ? BASE_URL : '/';
        $homeUrl   = $base;
        $action    = $base . 'contact';
        $homeText  = $le('home', 'Home');
        $csrf      = $e(Form::csrfToken());
        $lang      = defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'en';

        $labType      = $le('type', 'Topic');
        $labName      = $le('name', 'Name');
        $labEmail     = $le('email', 'Email');
        $labCompany   = $le('company', 'Company');
        $labMessage   = $le('message', 'Message');
        $labPrivacy   = $le('privacy');
        $labSubmit    = $le('submit', 'Send message');
        $labInfoTitle = $le('info_title', 'Contact information');
        $successText  = $le('success_message', 'Thank you. Your message has been sent.');

        $vName    = $e((string)($values['name'] ?? ''));
        $vEmail   = $e((string)($values['email'] ?? ''));
        $vCompany = $e((string)($values['company'] ?? ''));
        $vMessage = $e((string)($values['message'] ?? ''));

        $typeOptions = '';
        $types = ['product', 'business', 'media', 'other'];
        $currentType = in_array($values['type'] ?? 'product', $types, true) ? (string)($values['type'] ?? 'product') : 'product';
        foreach ($types as $t) {
            $selected = $t === $currentType ? ' selected' : '';
            $typeOptions .= '<option value="' . $t . '"' . $selected . '>' . $le('type_' . $t, $t) . '</option>';
        }

        $infoRows = '';
        $info = is_array($cfg['info'] ?? null) ? $cfg['info'] : [];
        $icons = [
            'address' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>',
            'phone'   => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>',
            'email'   => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline>',
            'hours'   => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
        ];
        foreach ($icons as $key => $svg) {
            $val = trim((string)($info[$key] ?? ''));
            if ($val === '') {
                continue;
            }
            $ev = $e($val);
            $content = $key === 'email'
                ? '<a href="mailto:' . $ev . '">' . $ev . '</a>'
                : '<span>' . $ev . '</span>';
            $infoRows .= '<div class="ct-info-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' . $svg . '</svg>' . $content . '</div>';
        }
        $aside = $infoRows === ''
            ? ''
            : '<aside class="ct-card ct-info"><h2>' . $labInfoTitle . '</h2>' . $infoRows . '</aside>';
        $gridClass = $infoRows === '' ? ' ct-grid--single' : '';

        $banner = '';
        if ($success) {
            $banner = '<div class="ct-alert ct-alert-ok" role="status">' . $successText . '</div>';
        } elseif ($errors !== []) {
            $items = '';
            foreach ($errors as $err) {
                $items .= '<li>' . $e((string)$err) . '</li>';
            }
            $banner = '<div class="ct-alert ct-alert-err" role="alert"><ul>' . $items . '</ul></div>';
        }

        $js = [
            'nameRequired'   => $jsEncode($le('error_name_required', 'Please enter your name.')),
            'emailRequired'  => $jsEncode($le('error_email_required', 'Please enter your email address.')),
            'emailInvalid'   => $jsEncode($le('error_email_invalid', 'Please enter a valid email address.')),
            'messageRequired'=> $jsEncode($le('error_message_required', 'Please enter a message.')),
            'messageTooLong' => $jsEncode($le('error_message_too_long', 'Your message must be 2000 characters or fewer.')),
            'privacyRequired'=> $jsEncode($le('error_privacy_required', 'Please accept the privacy policy.')),
        ];

        echo <<<HTML
<!doctype html>
<html lang="{$lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>
:root {
    --bg: #f6f7f9; --card: #ffffff; --text: #1c1e26;
    --muted: #5f6672; --line: #d9dde3; --accent: #3b5bdb;
    --err: #b3261e; --err-bg: #fdecea; --ok: #1e7d32; --ok-bg: #e8f5e9;
}
@media (prefers-color-scheme: dark) {
    :root {
        --bg: #0e1116; --card: #171b22; --text: #e6e9ef;
        --muted: #9aa3b2; --line: #2a3038; --accent: #7c9aff;
        --err: #ffb4ab; --err-bg: #3a1216; --ok: #a5d6a7; --ok-bg: #17301d;
    }
}
* { box-sizing: border-box; }
body {
    margin: 0; font-family: -apple-system, system-ui, "Segoe UI", "Helvetica Neue", sans-serif;
    background: var(--bg); color: var(--text); line-height: 1.55;
}
.ct-hero { background: linear-gradient(135deg, #1d2b53, #3b5bdb 90%); color: #fff; }
.ct-shell { max-width: 1060px; margin: 0 auto; padding: 2.6rem 1.25rem 2.2rem; }
.ct-breadcrumb { display: flex; align-items: center; gap: .5rem; font-size: .85rem; margin-bottom: 1.1rem; color: rgba(255,255,255,.8); }
.ct-breadcrumb a { color: rgba(255,255,255,.92); text-decoration: none; }
.ct-breadcrumb a:hover { text-decoration: underline; }
.ct-hero h1 { margin: 0 0 .4rem; font-size: 2rem; letter-spacing: -.01em; }
.ct-hero .ct-intro { margin: 0; color: rgba(255,255,255,.85); max-width: 60ch; }
.ct-main { max-width: 1060px; margin: 0 auto; padding: 2.4rem 1.25rem 3.5rem; }
.ct-grid { display: grid; grid-template-columns: 1.55fr 1fr; gap: 1.75rem; align-items: start; }
.ct-grid--single { grid-template-columns: 1fr; }
.ct-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 1.75rem 1.75rem 1.85rem; box-shadow: 0 10px 30px rgba(15,23,42,.06); }
.ct-alert { border-radius: 10px; padding: .8rem 1rem; margin: 0 0 1.35rem; font-size: .95rem; }
.ct-alert ul { margin: 0; padding-left: 1.2rem; }
.ct-alert-err { background: var(--err-bg); color: var(--err); border: 1px solid var(--err); }
.ct-alert-ok { background: var(--ok-bg); color: var(--ok); border: 1px solid var(--ok); }
.form-group { margin-bottom: 1.1rem; }
.form-label { display: block; font-weight: 600; font-size: .9rem; margin-bottom: .35rem; }
.ct-req { color: var(--err); }
.form-input, .form-select, .form-textarea {
    width: 100%; box-sizing: border-box; font: inherit; color: inherit;
    background: var(--card); border: 1px solid var(--line); border-radius: 10px;
    padding: .7rem .85rem; outline: none;
}
.form-textarea { min-height: 150px; resize: vertical; }
.form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,91,219,.16); }
.form-select { appearance: none; background-image: linear-gradient(45deg, transparent 50%, var(--muted) 50%), linear-gradient(135deg, var(--muted) 50%, transparent 50%); background-position: calc(100% - 1.1rem) 55%, calc(100% - .8rem) 55%; background-size: 6px 6px; background-repeat: no-repeat; }
.form-check { display: flex; align-items: flex-start; gap: .55rem; font-size: .9rem; cursor: pointer; }
.form-check input { margin-top: .2rem; }
.ct-field-error { display: block; color: var(--err); font-size: .82rem; margin-top: .3rem; min-height: 0; }
.ct-submit {
    width: 100%; border: 0; cursor: pointer; font: inherit; font-weight: 600;
    background: var(--accent); color: #fff; padding: .8rem 1.2rem; border-radius: 10px;
}
.ct-submit:hover { filter: brightness(1.08); }
.ct-hp { position: absolute !important; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
.ct-info h2 { margin: 0 0 1.1rem; font-size: 1.1rem; }
.ct-info-item { display: flex; align-items: flex-start; gap: .75rem; margin-bottom: .95rem; font-size: .95rem; }
.ct-info-item:last-child { margin-bottom: 0; }
.ct-info-item svg { flex: none; width: 20px; height: 20px; margin-top: 1px; color: var(--accent); }
.ct-info-item a { color: var(--accent); text-decoration: none; }
@media (max-width: 760px) {
    .ct-grid, .ct-grid--single { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<header class="ct-hero">
    <div class="ct-shell">
        <nav class="ct-breadcrumb" aria-label="Breadcrumb">
            <a href="{$homeUrl}">{$homeText}</a>
            <span>/</span>
            <span aria-current="page">{$title}</span>
        </nav>
        <h1>{$title}</h1>
        <p class="ct-intro">{$intro}</p>
    </div>
</header>
<main class="ct-main">
    {$banner}
    <div class="ct-grid{$gridClass}">
        <div class="ct-card">
            <form id="contactForm" action="{$action}" method="post" novalidate>
                <input type="hidden" name="contact_csrf" value="{$csrf}">
                <div class="form-group">
                    <label class="form-label" for="type">{$labType}</label>
                    <select class="form-select" id="type" name="type">{$typeOptions}</select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="name">{$labName} <span class="ct-req">*</span></label>
                    <input class="form-input" type="text" id="name" name="name" value="{$vName}" required>
                    <span class="ct-field-error" id="name-error"></span>
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">{$labEmail} <span class="ct-req">*</span></label>
                    <input class="form-input" type="email" id="email" name="email" value="{$vEmail}" required>
                    <span class="ct-field-error" id="email-error"></span>
                </div>
                <div class="form-group">
                    <label class="form-label" for="company">{$labCompany}</label>
                    <input class="form-input" type="text" id="company" name="company" value="{$vCompany}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="message">{$labMessage} <span class="ct-req">*</span></label>
                    <textarea class="form-textarea" id="message" name="message" maxlength="2000" required>{$vMessage}</textarea>
                    <span class="ct-field-error" id="message-error"></span>
                </div>
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="privacy" id="privacy" value="1" required>
                        <span>{$labPrivacy} <span class="ct-req">*</span></span>
                    </label>
                    <span class="ct-field-error" id="privacy-error"></span>
                </div>
                <div class="ct-hp" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>
                <button type="submit" class="ct-submit">{$labSubmit}</button>
            </form>
        </div>
        {$aside}
    </div>
</main>
<script>
(function () {
    'use strict';
    var L = {
        nameRequired: {$js['nameRequired']},
        emailRequired: {$js['emailRequired']},
        emailInvalid: {$js['emailInvalid']},
        messageRequired: {$js['messageRequired']},
        messageTooLong: {$js['messageTooLong']},
        privacyRequired: {$js['privacyRequired']}
    };
    function showError(field, message) {
        var el = document.getElementById(field + '-error');
        if (el) {
            el.textContent = message;
        }
    }
    function clearErrors() {
        var els = document.querySelectorAll('.ct-field-error');
        for (var i = 0; i < els.length; i++) {
            els[i].textContent = '';
        }
    }
    function validate() {
        clearErrors();
        var ok = true;
        var name = document.getElementById('name');
        var email = document.getElementById('email');
        var message = document.getElementById('message');
        var privacy = document.getElementById('privacy');
        if (!name.value.trim()) {
            showError('name', L.nameRequired);
            ok = false;
        }
        var em = email.value.trim();
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!em) {
            showError('email', L.emailRequired);
            ok = false;
        } else if (!re.test(em)) {
            showError('email', L.emailInvalid);
            ok = false;
        }
        if (!message.value.trim()) {
            showError('message', L.messageRequired);
            ok = false;
        } else if (message.value.length > 2000) {
            showError('message', L.messageTooLong);
            ok = false;
        }
        if (!privacy.checked) {
            showError('privacy', L.privacyRequired);
            ok = false;
        }
        return ok;
    }
    var form = document.getElementById('contactForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!validate()) {
                e.preventDefault();
            }
        });
    }
})();
</script>
</body>
</html>
HTML;
    }
}
