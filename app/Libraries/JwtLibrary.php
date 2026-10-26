<?php

namespace App\Libraries;

/**
 * Simple JWT Library for CodeIgniter 4
 * Pure PHP — no external dependencies required.
 */
class JwtLibrary
{
    private string $secret;
    private int    $expiry;

    public function __construct()
    {
        $this->secret = env('jwt.secret', 'default-secret-change-me');
        $this->expiry = (int) env('jwt.expiry', 86400);
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    public function generate(array $payload): string
    {
        $header  = $this->base64urlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = array_merge($payload, ['iat' => time(), 'exp' => time() + $this->expiry]);
        $pay     = $this->base64urlEncode(json_encode($payload));
        $sig     = $this->base64urlEncode(hash_hmac('sha256', "{$header}.{$pay}", $this->secret, true));

        return "{$header}.{$pay}.{$sig}";
    }

    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $sig] = $parts;

        $expected = $this->base64urlEncode(hash_hmac('sha256', "{$header}.{$payload}", $this->secret, true));
        if (! hash_equals($expected, $sig)) return null;

        $data = json_decode($this->base64urlDecode($payload), true);
        if (! $data || ($data['exp'] ?? 0) < time()) return null;

        return $data;
    }

    public function getFromRequest(\CodeIgniter\HTTP\IncomingRequest $request): ?array
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (! preg_match('/Bearer\s+(.+)$/i', $authHeader, $m)) return null;

        return $this->verify($m[1]);
    }

    /**
     * Ambil raw token string dari Authorization header (tanpa verify).
     * Digunakan untuk keperluan revocation check.
     */
    public function getRawTokenFromRequest(\CodeIgniter\HTTP\RequestInterface $request): ?string
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (! preg_match('/Bearer\s+(.+)$/i', $authHeader, $m)) return null;

        return trim($m[1]);
    }
}
