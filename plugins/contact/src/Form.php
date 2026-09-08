<?php
namespace Wawoo\Plugin\Contact;

/**
 * Pure, testable form logic: CSRF tokens, field validation, submission
 * normalisation, per-IP rate limiting and message delivery (mail plus the
 * inbox fallback).
 */
final class Form
{
    private const MESSAGE_MAX = 2000;
    private const TYPES = ['product', 'business', 'media', 'other'];

    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['wawoo_contact_csrf'])) {
            $_SESSION['wawoo_contact_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['wawoo_contact_csrf'];
    }

    public static function csrfValid(?string $token): bool
    {
        $expected = $_SESSION['wawoo_contact_csrf'] ?? '';
        return is_string($expected) && $expected !== ''
            && is_string($token) && $token !== ''
            && hash_equals($expected, $token);
    }

    /** Returns a list of human-readable error strings; empty means valid. */
    public static function validate(array $post, array $labels): array
    {
        $errors = [];
        $name    = trim((string)($post['name'] ?? ''));
        $email   = trim((string)($post['email'] ?? ''));
        $message = trim((string)($post['message'] ?? ''));

        if ($name === '') {
            $errors[] = self::msg($labels, 'error_name_required', 'Please enter your name.');
        }
        if ($email === '') {
            $errors[] = self::msg($labels, 'error_email_required', 'Please enter your email address.');
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = self::msg($labels, 'error_email_invalid', 'Please enter a valid email address.');
        }
        if ($message === '') {
            $errors[] = self::msg($labels, 'error_message_required', 'Please enter a message.');
        } elseif (mb_strlen($message) > self::MESSAGE_MAX) {
            $errors[] = self::msg($labels, 'error_message_too_long', 'Your message must be 2000 characters or fewer.');
        }
        if (empty($post['privacy'])) {
            $errors[] = self::msg($labels, 'error_privacy_required', 'Please accept the privacy policy.');
        }
        return $errors;
    }

    public static function buildSubmission(array $post): array
    {
        $type = (string)($post['type'] ?? 'other');
        if (!in_array($type, self::TYPES, true)) {
            $type = 'other';
        }
        return [
            'name'       => trim((string)($post['name'] ?? '')),
            'email'      => trim((string)($post['email'] ?? '')),
            'company'    => trim((string)($post['company'] ?? '')),
            'type'       => $type,
            'message'    => trim((string)($post['message'] ?? '')),
            'ip'         => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'ua'         => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
            'created_at' => time(),
        ];
    }

    public static function rateLimited(array $cfg, string $ip): bool
    {
        return self::attemptCount($cfg, $ip) >= self::maxAttempts($cfg);
    }

    public static function recordAttempt(array $cfg, string $ip): void
    {
        $window = self::window($cfg);
        $max    = self::maxAttempts($cfg);
        $now    = time();
        $times  = Store::rateGet($ip);
        $times[] = $now;
        $times = array_values(array_filter($times, static fn($t) => (int)$t > $now - $window));
        usort($times, static fn($a, $b) => $a <=> $b);
        if (count($times) > $max) {
            $times = array_slice($times, -$max);
        }
        Store::rateSet($ip, $times);
    }

    /** Seconds until the oldest in-window attempt ages out, or 0 when allowed. */
    public static function lockoutSeconds(array $cfg, string $ip): int
    {
        if (!self::rateLimited($cfg, $ip)) {
            return 0;
        }
        $window = self::window($cfg);
        $now    = time();
        $times  = array_values(array_filter(Store::rateGet($ip), static fn($t) => (int)$t > $now - $window));
        if ($times === []) {
            return 0;
        }
        return max(1, (int)min($times) + $window - $now);
    }

    /**
     * Delivers a submission. When enable_mail is on and a recipient is
     * configured a plain-text mail() is attempted; the returned value also
     * counts a successful inbox save (save_inbox) as delivery success, so a
     * site that only archives inquiries still reports the message as sent.
     */
    public static function sendMail(array $submission, array $cfg): bool
    {
        $ok        = false;
        $recipient = trim((string)($cfg['recipient_email'] ?? ''));
        if (!empty($cfg['enable_mail']) && $recipient !== '') {
            if (function_exists('mail')) {
                $domain = '';
                if (preg_match('/@([^@]+)$/', $recipient, $m) === 1 && $m[1] !== '') {
                    $domain = $m[1];
                }
                $from    = $domain !== '' ? 'noreply@' . $domain : $recipient;
                $subject = str_replace(["\r", "\n"], ' ', (string)($cfg['mail_subject_prefix'] ?? '') . (string)($submission['name'] ?? ''));
                $headers = 'From: ' . $from . "\r\n"
                    . 'Reply-To: ' . (string)($submission['email'] ?? '') . "\r\n"
                    . 'Content-Type: text/plain; charset=UTF-8';
                $ok = @mail($recipient, $subject, self::mailBody($submission), $headers);
            }
        }
        if (!empty($cfg['save_inbox']) && Store::save($submission) !== '') {
            $ok = true;
        }
        return $ok;
    }

    private static function attemptCount(array $cfg, string $ip): int
    {
        $window = self::window($cfg);
        $now    = time();
        $count  = 0;
        foreach (Store::rateGet($ip) as $t) {
            if ((int)$t > $now - $window) {
                $count++;
            }
        }
        return $count;
    }

    private static function maxAttempts(array $cfg): int
    {
        return max(1, (int)($cfg['max_attempts'] ?? 5));
    }

    private static function window(array $cfg): int
    {
        return max(1, (int)($cfg['rate_window'] ?? 3600));
    }

    private static function msg(array $labels, string $key, string $fallback): string
    {
        $value = $labels[$key] ?? '';
        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private static function mailBody(array $submission): string
    {
        $typeLabels = [
            'product'  => 'Product inquiry',
            'business' => 'Business inquiry',
            'media'    => 'Media inquiry',
            'other'    => 'Other',
        ];
        $type = (string)($submission['type'] ?? 'other');
        $body  = 'Type: ' . ($typeLabels[$type] ?? $type) . "\n";
        $body .= 'Name: ' . (string)($submission['name'] ?? '') . "\n";
        $body .= 'Email: ' . (string)($submission['email'] ?? '') . "\n";
        if (!empty($submission['company'])) {
            $body .= 'Company: ' . (string)$submission['company'] . "\n";
        }
        $body .= "\n" . (string)($submission['message'] ?? '') . "\n";
        $body .= "\n------------------------------\n";
        $body .= 'Sent: ' . date('Y-m-d H:i:s', (int)($submission['created_at'] ?? time())) . "\n";
        $body .= 'IP: ' . (string)($submission['ip'] ?? '') . "\n";
        return $body;
    }
}
