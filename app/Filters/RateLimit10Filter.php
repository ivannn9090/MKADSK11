<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * RateLimit10Filter — membatasi 10 request per 60 detik per IP per endpoint.
 *
 * Digunakan untuk: auth/login, auth/verify-otp
 */
class RateLimit10Filter implements FilterInterface
{
    private const MAX    = 10;
    private const WINDOW = 60;

    public function before(RequestInterface $request, $arguments = null): mixed
    {
        $ip  = $request->getIPAddress();
        $uri = md5($request->getUri()->getPath());
        $key = 'rl_' . md5($ip . $uri);

        $cache = service('cache');
        $data  = $cache->get($key);

        if ($data === null) {
            $cache->save($key, ['count' => 1, 'reset_at' => time() + self::WINDOW], self::WINDOW);
            return null;
        }

        if ($data['count'] >= self::MAX) {
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

        $ttl = max(1, ($data['reset_at'] ?? time() + self::WINDOW) - time());
        $cache->save($key, ['count' => $data['count'] + 1, 'reset_at' => $data['reset_at']], $ttl);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): mixed
    {
        return null;
    }
}
