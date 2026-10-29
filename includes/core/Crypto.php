<?php
declare(strict_types=1);

namespace Wawoo\Core;

/**
 * Optional at-rest encryption for small secrets (API keys, TOTP secrets).
 *
 * Uses libsodium secretbox with a 32-byte key supplied by the operator via
 * $GLOBALS['WAWOO_SECRET_KEY'] (base64) or the WAWOO_SECRET_KEY environment
 * variable. When no key is configured, values are returned unchanged so the
 * CMS keeps working; the stored files then rely on 0600 permissions.
 *
 * Sealed values are prefixed with "enc:v1:" so plaintext and ciphertext can
 * coexist and migrate transparently on the next write.
 */
final class Crypto
{
    private const PREFIX = 'enc:v1:';

    public static function available(): bool
    {
        return function_exists('sodium_crypto_secretbox');
    }

    private static function key(): ?string
    {
        $raw = '';
        if (isset($GLOBALS['WAWOO_SECRET_KEY']) && is_string($GLOBALS['WAWOO_SECRET_KEY'])) {
            $raw = $GLOBALS['WAWOO_SECRET_KEY'];
        } elseif (($env = getenv('WAWOO_SECRET_KEY')) !== false) {
            $raw = (string)$env;
        }
        if ($raw === '') {
            return null;
        }
        $decoded = base64_decode($raw, true);
        if (is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return $decoded;
        }
        return strlen($raw) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ? $raw : null;
    }

    /** Encrypt a secret for storage; returns the input unchanged when no key. */
    public static function seal(string $plain): string
    {
        $key = self::key();
        if ($key === null || $plain === '' || !self::available()) {
            return $plain;
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $box = sodium_crypto_secretbox($plain, $nonce, $key);
        return self::PREFIX . base64_encode($nonce . $box);
    }

    /** Decrypt a stored secret. Plaintext is returned as-is; null on failure. */
    public static function open(?string $stored): ?string
    {
        if ($stored === null) {
            return null;
        }
        if (!str_starts_with($stored, self::PREFIX)) {
            return $stored; // plaintext or empty
        }
        $key = self::key();
        if ($key === null || !self::available()) {
            return null;
        }
        $blob = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if (!is_string($blob) || strlen($blob) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $nonce = substr($blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $box = substr($blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($box, $nonce, $key);
        return $plain === false ? null : $plain;
    }
}
