<?php

namespace App\Controllers;

use App\Models\BiolinkProfileModel;

/**
 * BiolinkPublicController
 *
 * Menangani dua route publik (tidak butuh JWT):
 *
 *   GET  /biolink/(:any)          → show($slug)  — render halaman publik Linktree
 *   GET  /biolink-foto/(:any)     → foto($file)  — serve gambar profil dari writable/
 *
 * Analytics / click tracking dilakukan melalui sistem URL Shortener yang sudah ada.
 * Setiap tombol yang terhubung ke short_link akan redirect via /s/<slug>,
 * sehingga tabel short_link_logs terisi secara otomatis oleh RedirectController.
 */
class BiolinkPublicController extends BaseController
{
    /**
     * Render halaman publik profil biolink.
     * Route: GET /biolink/(:any)
     */
    public function show(string $slug): string|\CodeIgniter\HTTP\ResponseInterface
    {
        $model   = new BiolinkProfileModel();
        $profile = $model->getPublicBySlug($slug);

        if (!$profile) {
            // Tampilkan halaman 404 sederhana
            return view('biolink/not_found');
        }

        // Resolusi URL foto, cover & background
        $profile['foto_url']  = !empty($profile['foto_path'])
            ? base_url('biolink-foto/' . $profile['foto_path']) : null;
        $profile['cover_url'] = !empty($profile['cover_path'])
            ? base_url('biolink-foto/' . $profile['cover_path']) : null;
        $profile['bg_url']    = !empty($profile['bg_path'])
            ? base_url('biolink-foto/' . $profile['bg_path']) : null;

        return view('biolink/public', ['profile' => $profile]);
    }

    /**
     * Serve foto profil dari writable/uploads/biolink/.
     * Route: GET /biolink-foto/(:any)
     *
     * Alasan tidak menggunakan public/: foto profil adalah data pribadi
     * yang disimpan di luar webroot untuk keamanan, diakses via controller.
     */
    public function foto(string $filename): \CodeIgniter\HTTP\ResponseInterface
    {
        // Sanitasi nama file: tolak path traversal
        $filename = basename($filename);
        $filepath = WRITEPATH . 'uploads/biolink/' . $filename;

        if (!file_exists($filepath) || !is_file($filepath)) {
            return $this->response->setStatusCode(404);
        }

        $mime    = mime_content_type($filepath) ?: 'application/octet-stream';
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed, true)) {
            return $this->response->setStatusCode(403);
        }

        return $this->response
            ->setStatusCode(200)
            ->setContentType($mime)
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody(file_get_contents($filepath));
    }
}
