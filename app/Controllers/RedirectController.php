<?php

namespace App\Controllers;

use App\Models\ShortLinkModel;

/**
 * RedirectController — menangani route publik /s/(:any)
 *
 * Tidak memerlukan JWT. Increment total_klik, catat log akses,
 * lalu redirect ke long_url.
 */
class RedirectController extends BaseController
{
    public function redirect(string $slug): void
    {
        $model = new ShortLinkModel();
        $link  = $model->findBySlug($slug);

        if (!$link) {
            // Tampilkan halaman 404 yang bersih
            $this->response->setStatusCode(404);
            echo view('errors/html/error_404');
            return;
        }

        // Catat akses secara asinkron-ish (tetap satu request, tapi cepat)
        $model->incrementKlik((int) $link['id']);
        $model->logAccess(
            (int) $link['id'],
            (string) $this->request->getIPAddress(),
            (string) ($this->request->getServer('HTTP_USER_AGENT') ?? ''),
            (string) ($this->request->getServer('HTTP_REFERER') ?? '')
        );

        // Redirect permanen agar tidak membebani server saat sama user klik lagi
        header('Location: ' . $link['long_url'], true, 302);
        exit;
    }
}
