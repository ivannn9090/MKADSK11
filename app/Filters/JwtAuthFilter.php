<?php

namespace App\Filters;

use App\Libraries\JwtLibrary;
use App\Models\RevokedTokenModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * JWT Authentication Filter
 * Apply to all API routes that require authentication.
 * Menolak token yang sudah di-revoke (post-logout).
 */
class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): mixed
    {
        $jwt     = new JwtLibrary();
        $payload = $jwt->getFromRequest($request);

        if ($payload === null) {
            return service('response')
                ->setStatusCode(401)
                ->setContentType('application/json')
                ->setJSON(['status' => false, 'message' => 'Token tidak valid atau tidak ditemukan. Silakan login kembali.']);
        }

        // Cek apakah token sudah di-revoke (logout sebelum expired)
        $rawToken = $jwt->getRawTokenFromRequest($request);
        if ($rawToken && (new RevokedTokenModel())->isRevoked($rawToken)) {
            return service('response')
                ->setStatusCode(401)
                ->setContentType('application/json')
                ->setJSON(['status' => false, 'message' => 'Sesi telah berakhir. Silakan login kembali.']);
        }

        // Store payload in request for use in controllers
        $request->jwtPayload = $payload;

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): mixed
    {
        return null;
    }
}
