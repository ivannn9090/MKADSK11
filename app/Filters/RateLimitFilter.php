<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * RateLimitFilter — membatasi jumlah request per IP per window waktu.
 *
 * Penggunaan di Routes:
 *   ['filter' => 'rateLimit[5,60]']  → 5 request per 60 detik
 *   ['filter' => 'rateLimit']        → default: 10 request per 60 detik
 *
 * Menggunakan CI4 Cache (file-based default) — tidak perlu Redis/Memcached.
 */
class RateLimitFilter implements FilterInterface
{
    /** Default: maksimum request per window */
    private const DEFAULT_MAX      = 10;
    /** Default: window dalam detik */
    private const DEFAULT_WINDOW   = 60;

    public function before(RequestInterface $request, $arguments = null): mixed
    {
        $max    = (int) ($arguments[0] ?? self::DEFAULT_MAX);
        $window = (int) ($arguments[1] ?? self::DEFAULT_WINDOW);

        $ip  = $request->getIPAddress();
        // Sertakan URI agar limit per endpoint (login berbeda dari OTP)
        $uri = md5($request->getUri()->getPath());
        $key = 'rl_' . md5($ip . $uri);

        $cache = service('cache');

        $data = $cache->get($key);

        if ($data === null) {
            // Request pertama dalam window ini
            $cache->save($key, ['count' => 1, 'reset_at' => time() + $window], $window);
            return null;
        }

        if ($data['count'] >= $max) {
            $retryAfter = max(0, ($data['reset_at'] ?? time()) - time());
            return service('response')
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $retryAfter)
                ->setContentType('application/json')
                ->setJSON([
                    'status'  => false,
                    'message' => "Terlalu banyak percobaan. Coba lagi dalam {$retryAfter} detik.",
                ]);
        }

        // Tambah counter, pertahankan TTL yang tersisa
        $ttl = max(1, ($data['reset_at'] ?? time() + $window) - time());
        $cache->save($key, ['count' => $data['count'] + 1, 'reset_at' => $data['reset_at']], $ttl);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): mixed
    {
        return null;
    }
}
