<?php
namespace Wawoo\Plugin\S3;

/**
 * Minimal AWS Signature Version 4 client for S3-compatible object storage.
 *
 * Talks to the configured endpoint verbatim using PHP stream contexts only
 * (no curl, no third-party SDKs). Requests are signed for path-style access
 * (endpoint/bucket/key), which AWS S3, Cloudflare R2 and MinIO all accept.
 */
final class Client
{
    private array $cfg;

    public function __construct(array $cfg = [])
    {
        $this->cfg = $cfg;
    }

    /**
     * Normalize and SSRF-validate an S3 endpoint. Returns the cleaned
     * scheme://host[:port] string when the host only resolves to public
     * addresses, or null when the endpoint is unusable (http without
     * allow_http, embedded path/userinfo, blocked port, or any resolved
     * loopback/private/link-local/unspecified address).
     */
    public static function validateEndpoint(string $endpoint, array $cfg = []): ?string
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '' || !preg_match('#^https?://#i', $endpoint)) {
            return null;
        }
        $parts = parse_url($endpoint);
        if ($parts === false || !isset($parts['host'])) {
            return null;
        }
        $scheme = strtolower((string)$parts['scheme']);
        $allowHttp = !empty($cfg['allow_http']);
        if ($scheme !== 'https' && $scheme !== 'http') {
            return null;
        }
        if ($scheme === 'http' && !$allowHttp) {
            return null;
        }
        $host = (string)$parts['host'];
        if ($host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $path = (string)($parts['path'] ?? '');
        if (trim($path, '/') !== '' || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }
        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        if ($port !== null && !self::portAllowed($scheme, $port, $allowHttp)) {
            return null;
        }
        foreach (self::hostAddresses($host) as $address) {
            if (self::isBlockedAddress($address)) {
                return null;
            }
        }
        return self::buildEndpoint($scheme, $host, $port);
    }

    private static function portAllowed(string $scheme, int $port, bool $allowHttp): bool
    {
        if ($allowHttp) {
            return $port > 0 && $port <= 65535;
        }
        return $scheme === 'https' && ($port === 443 || $port === 8443);
    }

    private static function hostAddresses(string $host): array
    {
        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }
        $addresses = [];
        $ipv4 = gethostbynamel($host);
        if (is_array($ipv4)) {
            $addresses = $ipv4;
        }
        $aaaa = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $record) {
                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }
        return $addresses;
    }

    private static function isBlockedAddress(string $address): bool
    {
        $bin = @inet_pton($address);
        if ($bin === false) {
            return true;
        }
        if (strlen($bin) === 4) {
            [$b1, $b2, $b3] = array_values(unpack('C4', $bin));
            if ($b1 === 0 || $b1 === 127) {
                return true;
            }
            if ($b1 === 10) {
                return true;
            }
            if ($b1 === 100 && $b2 >= 64 && $b2 <= 127) {
                return true;
            }
            if ($b1 === 169 && $b2 === 254) {
                return true;
            }
            if ($b1 === 172 && $b2 >= 16 && $b2 <= 31) {
                return true;
            }
            if ($b1 === 192 && $b2 === 168) {
                return true;
            }
            if ($b1 === 192 && $b2 === 0 && ($b3 === 0 || $b3 === 2)) {
                return true;
            }
            if ($b1 === 198 && ($b2 === 18 || $b2 === 19)) {
                return true;
            }
            if (($b1 === 198 && $b2 === 51 && $b3 === 100) || ($b1 === 203 && $b2 === 0 && $b3 === 113)) {
                return true;
            }
            if ($b1 >= 224) {
                return true;
            }
            return false;
        }
        if (strlen($bin) === 16) {
            $w = array_values(unpack('n8', $bin));
            if ($w[0] === 0 && $w[1] === 0 && $w[2] === 0 && $w[3] === 0 && $w[4] === 0 && $w[5] === 0xffff) {
                return self::isBlockedAddress(inet_ntop(pack('N', ($w[6] << 16) | $w[7])));
            }
            if ($w[0] === 0 && $w[1] === 0 && $w[2] === 0 && $w[3] === 0 && $w[4] === 0 && $w[5] === 0 && $w[6] === 0) {
                return $w[7] === 0 || $w[7] === 1;
            }
            if (($w[0] & 0xffc0) === 0xfe80) {
                return true;
            }
            if (($w[0] & 0xfe00) === 0xfc00) {
                return true;
            }
            if (($w[0] & 0xff00) === 0xff00) {
                return true;
            }
            return false;
        }
        return true;
    }

    private static function buildEndpoint(string $scheme, string $host, ?int $port): string
    {
        $bare = trim($host, '[]');
        $authority = str_contains($bare, ':') ? '[' . $bare . ']' : $bare;
        return $scheme . '://' . $authority . ($port !== null ? ':' . $port : '');
    }

    public static function region(array $cfg): string
    {
        $region = trim((string)($cfg['region'] ?? ''));
        return $region !== '' ? $region : 'us-east-1';
    }

    public static function hmacSha256(string $key, string $data): string
    {
        return hash_hmac('sha256', $data, $key, true);
    }

    /**
     * Derive the SigV4 signing key (binary) for a scope of
     * dateStamp/region/service/aws4_request.
     */
    public static function signingKey(string $secret, string $dateStamp, string $region, string $service): string
    {
        $k = self::hmacSha256('AWS4' . $secret, $dateStamp);
        $k = self::hmacSha256($k, $region);
        $k = self::hmacSha256($k, $service);
        return self::hmacSha256($k, 'aws4_request');
    }

    /**
     * The canonical request string for SigV4. $headers maps header name to
     * value; names are lowercased and sorted, and the sorted names become
     * the trailing SignedHeaders line.
     */
    public static function canonicalRequest(
        string $method,
        string $canonicalUri,
        string $canonicalQuery,
        array $headers,
        string $payloadHash
    ): string {
        $headers = self::normalizedHeaders($headers);
        ksort($headers);
        $lines = '';
        foreach ($headers as $name => $value) {
            $lines .= $name . ':' . trim($value) . "\n";
        }
        $signed = implode(';', array_keys($headers));
        return $method . "\n"
            . $canonicalUri . "\n"
            . $canonicalQuery . "\n"
            . $lines
            . "\n"
            . $signed . "\n"
            . $payloadHash;
    }

    public static function stringToSign(string $amzDate, string $scope, string $canonicalRequest): string
    {
        return "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
    }

    public static function contentTypeFor(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'webp'  => 'image/webp',
            'avif'  => 'image/avif',
            'ico'   => 'image/x-icon',
            'pdf'   => 'application/pdf',
            'mp4'   => 'video/mp4',
            'webm'  => 'video/webm',
            'mp3'   => 'audio/mpeg',
            'wav'   => 'audio/wav',
            'ogg'   => 'audio/ogg',
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'json'  => 'application/json',
            'txt'   => 'text/plain',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }

    public function putObject(string $key, string $body, string $contentType): bool
    {
        [$status] = $this->request('PUT', $key, $body, $contentType);
        return $status >= 200 && $status < 300;
    }

    /** Create the configured bucket if missing (path-style PUT /bucket/). */
    public function putBucket(): bool
    {
        [$status] = $this->request('PUT', '', '');
        return $status >= 200 && $status < 300;
    }

    public function objectExists(string $key): bool
    {
        [$status] = $this->request('HEAD', $key);
        return $status === 200;
    }

    public function deleteObject(string $key): bool
    {
        [$status] = $this->request('DELETE', $key);
        return $status >= 200 && $status < 300;
    }

    /**
     * Perform one signed request against the endpoint and return
     * [httpStatus, responseBody]. Non-2xx responses are still read back
     * (ignore_errors) so the caller can decide.
     */
    private function request(string $method, string $key, string $body = '', ?string $contentType = null): array
    {
        $bucket = trim((string)($this->cfg['bucket'] ?? ''));
        $access = (string)($this->cfg['access_key'] ?? '');
        $secret = (string)($this->cfg['secret_key'] ?? '');
        if ($bucket === '' || $access === '' || $secret === '') {
            return [0, ''];
        }
        $endpoint = self::validateEndpoint((string)($this->cfg['endpoint'] ?? ''), $this->cfg);
        if ($endpoint === null) {
            return [0, ''];
        }

        $region = self::region($this->cfg);
        $keyPath = self::encodePath($key);
        $uri = '/' . self::encodePath($bucket) . '/' . $keyPath;
        $url = $endpoint . $uri;
        $host = (string)parse_url($url, PHP_URL_HOST);
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);

        $headers = [
            'host'                 => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date'           => $amzDate,
        ];
        if ($contentType !== null && $contentType !== '') {
            $headers['content-type'] = $contentType;
        }

        $canonical = self::canonicalRequest($method, $uri, '', $headers, $payloadHash);
        $signedHeaders = self::signedHeaderNames($headers);
        $scope = $dateStamp . '/' . $region . '/s3/aws4_request';
        $signature = hash_hmac(
            'sha256',
            self::stringToSign($amzDate, $scope, $canonical),
            self::signingKey($secret, $dateStamp, $region, 's3')
        );
        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $access . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        $headerLines = [
            'Host: ' . $host,
            'x-amz-date: ' . $amzDate,
            'x-amz-content-sha256: ' . $payloadHash,
            'Authorization: ' . $authorization,
        ];
        if (isset($headers['content-type'])) {
            $headerLines[] = 'Content-Type: ' . $headers['content-type'];
        }

        $ctx = stream_context_create(['http' => [
            'method'          => $method,
            'header'          => implode("\r\n", $headerLines),
            'content'         => $body,
            'ignore_errors'   => true,
            'timeout'         => 30,
            'protocol_version'=> 1.1,
            'follow_location' => 0,
        ]]);
        $resp = @file_get_contents($url, false, $ctx);

        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }
        return [$status, $resp === false ? '' : (string)$resp];
    }

    private static function signedHeaderNames(array $headers): string
    {
        $names = array_keys(self::normalizedHeaders($headers));
        sort($names);
        return implode(';', $names);
    }

    private static function normalizedHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            $out[strtolower(trim((string)$name))] = trim((string)$value);
        }
        return $out;
    }

    /** Percent-encode a key path segment-wise so '/' separators survive. */
    private static function encodePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $segment) {
            $parts[] = rawurlencode($segment);
        }
        return implode('/', $parts);
    }
}
