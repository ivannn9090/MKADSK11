<?php

namespace App\Controllers\Api;

use App\Models\ShortLinkModel;
use App\Services\QrCodeService;

/**
 * ShortLinkController
 *
 * API Endpoints:
 *   GET    api/v1/short-links              → index()    – daftar link milik user
 *   POST   api/v1/short-links              → create()   – buat link baru + QR Code
 *   GET    api/v1/short-links/(:num)       → show($id)  – detail satu link + log akses
 *   DELETE api/v1/short-links/(:num)       → delete($id)
 *   GET    api/v1/short-links/(:num)/logs  → logs($id)  – riwayat klik
 *
 * Public (tidak butuh JWT):
 *   GET    s/(:any)                        → redirect()  – redirect + catat klik
 */
class ShortLinkController extends BaseApiController
{
    private ShortLinkModel $model;
    private QrCodeService  $qrService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->model     = new ShortLinkModel();
        $this->qrService = new QrCodeService();
    }

    // -------------------------------------------------------------------------
    // GET api/v1/short-links
    // -------------------------------------------------------------------------
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $links = $this->model->getByUser($this->userId());

        // Tambahkan URL penuh untuk setiap link
        $baseUrl = base_url();
        foreach ($links as &$link) {
            $link['short_url'] = $baseUrl . 's/' . $link['slug'];
            $link['qr_url']    = $link['qr_path'] ? $this->qrService->publicUrl($link['qr_path']) : null;
        }
        unset($link);

        return $this->success($links);
    }

    // -------------------------------------------------------------------------
    // POST api/v1/short-links
    // -------------------------------------------------------------------------
    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();

        $longUrl    = trim($body['long_url'] ?? '');
        $customSlug = trim($body['slug'] ?? '');
        $title      = trim($body['title'] ?? '');

        if ($longUrl === '') {
            return $this->fail('long_url wajib diisi.');
        }

        // Validasi URL
        if (!filter_var($longUrl, FILTER_VALIDATE_URL)) {
            return $this->fail('Format URL tidak valid.');
        }

        // Tentukan slug
        if ($customSlug !== '') {
            // Validasi karakter slug
            if (!preg_match('/^[a-zA-Z0-9_-]{3,64}$/', $customSlug)) {
                return $this->fail('Slug hanya boleh berisi huruf, angka, tanda hubung, dan underscore (3–64 karakter).');
            }
            // Cek keunikan
            if ($this->model->findBySlug($customSlug) !== null) {
                return $this->fail('Slug sudah digunakan, coba yang lain.');
            }
            $slug = $customSlug;
        } else {
            $slug = $this->model->generateUniqueSlug();
        }

        // Generate QR Code
        $shortUrl = base_url('s/' . $slug);
        $qrPath   = $this->qrService->generate($shortUrl, $slug);

        // Simpan ke database
        $data = [
            'user_id'  => $this->userId(),
            'long_url' => $longUrl,
            'slug'     => $slug,
            'title'    => $title !== '' ? $title : null,
            'qr_path'  => $qrPath,
        ];

        $id = $this->model->insert($data, true);

        if (!$id) {
            // Rollback file QR jika insert gagal
            $this->qrService->delete($qrPath);
            return $this->fail('Gagal menyimpan short link: ' . implode(', ', $this->model->errors()), 422);
        }

        $record             = $this->model->find($id);
        $record['short_url'] = $shortUrl;
        $record['qr_url']   = $this->qrService->publicUrl($qrPath);

        $this->auditLog('Membuat short link', 'short_links', $id, null, $record);

        return $this->success($record, 'Short link berhasil dibuat.', 201);
    }

    // -------------------------------------------------------------------------
    // GET api/v1/short-links/(:num)
    // -------------------------------------------------------------------------
    public function show(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $link = $this->model->find($id);

        if (!$link) {
            return $this->fail('Short link tidak ditemukan.', 404);
        }

        // Hanya pemilik atau admin
        if ($link['user_id'] !== $this->userId() && $this->userRole() !== 'admin') {
            return $this->fail('Akses tidak diizinkan.', 403);
        }

        $link['short_url'] = base_url('s/' . $link['slug']);
        $link['qr_url']    = $link['qr_path'] ? $this->qrService->publicUrl($link['qr_path']) : null;

        return $this->success($link);
    }

    // -------------------------------------------------------------------------
    // DELETE api/v1/short-links/(:num)
    // -------------------------------------------------------------------------
    public function delete(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $link = $this->model->find($id);

        if (!$link) {
            return $this->fail('Short link tidak ditemukan.', 404);
        }

        if ($link['user_id'] !== $this->userId() && $this->userRole() !== 'admin') {
            return $this->fail('Akses tidak diizinkan.', 403);
        }

        // Hapus file QR
        if ($link['qr_path']) {
            $this->qrService->delete($link['qr_path']);
        }

        $this->model->delete($id);
        $this->auditLog('Hapus short link', 'short_links', $id, $link);

        return $this->success(null, 'Short link berhasil dihapus.');
    }

    // -------------------------------------------------------------------------
    // GET api/v1/short-links/(:num)/logs
    // -------------------------------------------------------------------------
    public function logs(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $link = $this->model->find($id);

        if (!$link) {
            return $this->fail('Short link tidak ditemukan.', 404);
        }

        if ($link['user_id'] !== $this->userId() && $this->userRole() !== 'admin') {
            return $this->fail('Akses tidak diizinkan.', 403);
        }

        $limit  = (int) ($this->request->getGet('limit') ?? 50);
        $offset = (int) ($this->request->getGet('offset') ?? 0);

        $logs = $this->model->getAccessLogs($id, $limit, $offset);

        return $this->success([
            'total_klik' => (int) $link['total_klik'],
            'logs'       => $logs,
        ]);
    }
}
