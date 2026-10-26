<?php

namespace App\Controllers\Api;

use App\Models\BiolinkProfileModel;
use App\Models\BiolinkItemModel;
use App\Models\ShortLinkModel;

/**
 * BiolinkController  — API multi-card Biolink Manager
 *
 * Setiap "card" adalah satu halaman publik dengan slug tersendiri.
 * Satu user bisa punya banyak card, setiap card punya banyak link.
 *
 * ── Card ──────────────────────────────────────────────────────────
 *  GET    api/v1/biolink/cards                → listCards()
 *  POST   api/v1/biolink/cards                → createCard()
 *  GET    api/v1/biolink/cards/(:num)         → showCard($id)
 *  POST   api/v1/biolink/cards/(:num)         → updateCard($id)   (multipart foto)
 *  DELETE api/v1/biolink/cards/(:num)         → deleteCard($id)
 *  PATCH  api/v1/biolink/cards/(:num)/toggle  → toggleCard($id)
 *
 * ── Items (nested per card) ───────────────────────────────────────
 *  GET    api/v1/biolink/cards/(:num)/items           → getItems($cardId)
 *  POST   api/v1/biolink/cards/(:num)/items           → createItem($cardId)
 *  PUT    api/v1/biolink/cards/(:num)/items/(:num)    → updateItem($cardId,$id)
 *  DELETE api/v1/biolink/cards/(:num)/items/(:num)    → deleteItem($cardId,$id)
 *  PUT    api/v1/biolink/cards/(:num)/items/reorder   → reorderItems($cardId)
 *  PATCH  api/v1/biolink/cards/(:num)/items/(:num)/toggle → toggleItem($cardId,$id)
 */
class BiolinkController extends BaseApiController
{
    private BiolinkProfileModel $cardModel;
    private BiolinkItemModel    $itemModel;
    private ShortLinkModel      $shortModel;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface  $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface            $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->cardModel  = new BiolinkProfileModel();
        $this->itemModel  = new BiolinkItemModel();
        $this->shortModel = new ShortLinkModel();
    }

    // =========================================================================
    // CARD
    // =========================================================================

    /** GET api/v1/biolink/cards — semua card milik user login */
    public function listCards(): \CodeIgniter\HTTP\ResponseInterface
    {
        $cards = $this->cardModel->getAllByUserId($this->userId());
        return $this->success($cards);
    }

    /** GET api/v1/biolink/cards/(:num) — detail satu card */
    public function showCard(int $cardId): \CodeIgniter\HTTP\ResponseInterface
    {
        $card = $this->_requireCard($cardId);
        if ($card instanceof \CodeIgniter\HTTP\ResponseInterface) return $card;
        return $this->success($card);
    }

    /**
     * POST api/v1/biolink/cards — buat card baru
     * Multipart: nama, slug?, bio?, warna_aksen?, foto?, sosmed_json?
     */
    public function createCard(): \CodeIgniter\HTTP\ResponseInterface
    {
        $input = $this->request->getPost();

        $nama = trim($input['nama'] ?? '');
        if ($nama === '') return $this->fail('Nama card wajib diisi.');

        $slug = trim($input['slug'] ?? '');
        if ($slug === '') {
            $slug = $this->cardModel->generateSlug($nama);
        } else {
            if (!preg_match('/^[a-zA-Z0-9_-]{3,80}$/', $slug)) {
                return $this->fail('Slug hanya boleh huruf, angka, - dan _ (3–80 karakter).');
            }
            if ($this->cardModel->where('slug', $slug)->first()) {
                return $this->fail('Slug sudah digunakan.');
            }
        }

        $fotoPath = $this->_handleFotoUpload(null);
        if (is_string($fotoPath) && str_starts_with($fotoPath, 'ERROR:')) {
            return $this->fail(substr($fotoPath, 6));
        }

        $coverPath = $this->_handleCoverUpload(null);
        if (is_string($coverPath) && str_starts_with($coverPath, 'ERROR:')) {
            return $this->fail(substr($coverPath, 6));
        }

        $bgPath = $this->_handleBgUpload(null);
        if (is_string($bgPath) && str_starts_with($bgPath, 'ERROR:')) {
            return $this->fail(substr($bgPath, 6));
        }

        $data = [
            'user_id'     => $this->userId(),
            'slug'        => $slug,
            'nama'        => $nama,
            'bio'         => trim($input['bio'] ?? '') ?: null,
            'foto_path'   => $fotoPath,
            'cover_path'  => $coverPath,
            'bg_path'     => $bgPath,
            'warna_aksen' => trim($input['warna_aksen'] ?? '') ?: '#1a6fb5',
            'sosmed_json' => $this->_parseSosmed($input['sosmed_json'] ?? null),
            'is_active'   => 1,
        ];

        $id = $this->cardModel->insert($data, true);
        if (!$id) {
            return $this->fail('Gagal membuat card: ' . implode(', ', $this->cardModel->errors()));
        }

        $this->auditLog('Buat biolink card', 'biolink_profiles', $id, null, $data);
        return $this->success($this->_cardResponse($id), 'Card berhasil dibuat.', 201);
    }

    /**
     * POST api/v1/biolink/cards/(:num) — update card
     * Pakai POST agar bisa multipart (foto upload) tanpa workaround.
     */
    public function updateCard(int $cardId): \CodeIgniter\HTTP\ResponseInterface
    {
        $card = $this->_requireCard($cardId);
        if ($card instanceof \CodeIgniter\HTTP\ResponseInterface) return $card;

        $input = $this->request->getPost();

        $nama = trim($input['nama'] ?? $card['nama']);
        if ($nama === '') return $this->fail('Nama card wajib diisi.');

        // Slug: jika diisi dan berbeda, validasi unik
        $slug = trim($input['slug'] ?? $card['slug']);
        if ($slug !== $card['slug']) {
            if (!preg_match('/^[a-zA-Z0-9_-]{3,80}$/', $slug)) {
                return $this->fail('Slug hanya boleh huruf, angka, - dan _ (3–80 karakter).');
            }
            if ($this->cardModel->where('slug', $slug)->first()) {
                return $this->fail('Slug sudah digunakan.');
            }
        }

        $fotoPath = $this->_handleFotoUpload($card['foto_path']);
        if (is_string($fotoPath) && str_starts_with($fotoPath, 'ERROR:')) {
            return $this->fail(substr($fotoPath, 6));
        }

        $coverPath = $this->_handleCoverUpload($card['cover_path'] ?? null);
        if (is_string($coverPath) && str_starts_with($coverPath, 'ERROR:')) {
            return $this->fail(substr($coverPath, 6));
        }

        $bgPath = $this->_handleBgUpload($card['bg_path'] ?? null);
        if (is_string($bgPath) && str_starts_with($bgPath, 'ERROR:')) {
            return $this->fail(substr($bgPath, 6));
        }

        $data = [
            'id'          => $cardId, // dibutuhkan agar is_unique {id} placeholder tergantikan
            'slug'        => $slug,
            'nama'        => $nama,
            'bio'         => trim($input['bio'] ?? '') ?: null,
            'foto_path'   => $fotoPath,
            'cover_path'  => $coverPath,
            'bg_path'     => $bgPath,
            'warna_aksen' => trim($input['warna_aksen'] ?? '') ?: '#1a6fb5',
            'sosmed_json' => $this->_parseSosmed($input['sosmed_json'] ?? null),
        ];

        $this->cardModel->update($cardId, $data);
        $this->auditLog('Update biolink card', 'biolink_profiles', $cardId, $card, $data);
        return $this->success($this->_cardResponse($cardId), 'Card berhasil diperbarui.');
    }

    /** DELETE api/v1/biolink/cards/(:num) */
    public function deleteCard(int $cardId): \CodeIgniter\HTTP\ResponseInterface
    {
        $card = $this->_requireCard($cardId);
        if ($card instanceof \CodeIgniter\HTTP\ResponseInterface) return $card;

        // Hapus foto, cover, dan background jika ada
        if (!empty($card['foto_path']))  @unlink(WRITEPATH . 'uploads/biolink/' . $card['foto_path']);
        if (!empty($card['cover_path'])) @unlink(WRITEPATH . 'uploads/biolink/' . $card['cover_path']);
        if (!empty($card['bg_path']))    @unlink(WRITEPATH . 'uploads/biolink/' . $card['bg_path']);

        $this->cardModel->delete($cardId);
        $this->auditLog('Hapus biolink card', 'biolink_profiles', $cardId, $card);
        return $this->success(null, 'Card berhasil dihapus.');
    }

    /** PATCH api/v1/biolink/cards/(:num)/toggle */
    public function toggleCard(int $cardId): \CodeIgniter\HTTP\ResponseInterface
    {
        $card = $this->_requireCard($cardId);
        if ($card instanceof \CodeIgniter\HTTP\ResponseInterface) return $card;

        $new = $card['is_active'] ? 0 : 1;
        $this->cardModel->update($cardId, ['is_active' => $new]);
        return $this->success(['is_active' => $new], 'Status card diperbarui.');
    }

    // =========================================================================
    // ITEMS (nested: /cards/:cardId/items)
    // =========================================================================

    /** GET api/v1/biolink/cards/(:num)/items */
    public function getItems(int $cardId): \CodeIgniter\HTTP\ResponseInterface
    {
        $card = $this->_requireCard($cardId);
        if ($card instanceof \CodeIgniter\HTTP\ResponseInterface) return $card;

        return $this->success($this->itemModel->getByProfile($cardId));
    }

    /**
     * POST api/v1/biolink/cards/(:num)/items
     * Body JSON: { judul, url_tujuan, ikon?, urutan?, use_short_link? }
     */
    public function createItem(int $cardId): \CodeIgniter\HTTP\ResponseInterface
    {
        $card = $this->_requireCard($cardId);
        if ($card instanceof \CodeIgniter\HTTP\ResponseInterface) return $card;

        $body = $this->request->getJSON(true) ?? $this->request->getPost();

        $judul     = trim($body['judul']      ?? '');
        $urlTujuan = trim($body['url_tujuan'] ?? '');
        $ikon      = trim($body['ikon']       ?? '');
        $urutan    = (int) ($body['urutan']   ?? 0);
        $useShort  = !empty($body['use_short_link']);

        if ($judul === '')     return $this->fail('Judul tombol wajib diisi.');
        if ($urlTujuan === '') return $this->fail('URL tujuan wajib diisi.');
        if (!filter_var($urlTujuan, FILTER_VALIDATE_URL)) {
            return $this->fail('Format URL tidak valid.');
        }

        $shortLinkId = null;
        if ($useShort) {
            $slug     = $this->shortModel->generateUniqueSlug();
            $slId     = $this->shortModel->insert([
                'user_id'  => $this->userId(),
                'long_url' => $urlTujuan,
                'slug'     => $slug,
                'title'    => $judul,
                'qr_path'  => null,
            ], true);
            if ($slId) {
                $shortLinkId = $slId;
                $urlTujuan   = base_url('s/' . $slug);
            }
        }

        $id = $this->itemModel->insert([
            'profile_id'    => $cardId,
            'short_link_id' => $shortLinkId,
            'judul'         => $judul,
            'url_tujuan'    => $urlTujuan,
            'ikon'          => $ikon ?: null,
            'urutan'        => $urutan,
            'is_active'     => 1,
        ], true);

        if (!$id) {
            return $this->fail('Gagal menyimpan item: ' . implode(', ', $this->itemModel->errors()));
        }

        $this->auditLog('Tambah biolink item', 'biolink_items', $id);
        return $this->success($this->itemModel->find($id), 'Link berhasil ditambahkan.', 201);
    }

    /** PUT api/v1/biolink/cards/(:num)/items/(:num) */
    public function updateItem(int $cardId, int $itemId): \CodeIgniter\HTTP\ResponseInterface
    {
        $item = $this->_requireItem($cardId, $itemId);
        if ($item instanceof \CodeIgniter\HTTP\ResponseInterface) return $item;

        $body = $this->request->getJSON(true) ?? $this->request->getPost();

        $judul     = trim($body['judul']      ?? $item['judul']);
        $urlTujuan = trim($body['url_tujuan'] ?? $item['url_tujuan']);
        $ikon      = isset($body['ikon'])     ? trim($body['ikon'])  : ($item['ikon'] ?? '');
        $urutan    = isset($body['urutan'])   ? (int)$body['urutan'] : (int)$item['urutan'];
        $isActive  = isset($body['is_active'])? (int)$body['is_active'] : (int)$item['is_active'];

        if ($judul === '')     return $this->fail('Judul tombol wajib diisi.');
        if ($urlTujuan === '') return $this->fail('URL tujuan wajib diisi.');
        if (!filter_var($urlTujuan, FILTER_VALIDATE_URL)) {
            return $this->fail('Format URL tidak valid.');
        }

        // Sinkronkan long_url di short_links jika item pakai tracking
        if ($item['short_link_id']) {
            $sl = $this->shortModel->find($item['short_link_id']);
            if ($sl) {
                $this->shortModel->update($item['short_link_id'], [
                    'long_url' => $urlTujuan,
                    'title'    => $judul,
                ]);
                $urlTujuan = base_url('s/' . $sl['slug']);
            }
        }

        $this->itemModel->update($itemId, [
            'judul'      => $judul,
            'url_tujuan' => $urlTujuan,
            'ikon'       => $ikon ?: null,
            'urutan'     => $urutan,
            'is_active'  => $isActive,
        ]);

        $this->auditLog('Edit biolink item', 'biolink_items', $itemId);
        return $this->success($this->itemModel->find($itemId), 'Link berhasil diperbarui.');
    }

    /** DELETE api/v1/biolink/cards/(:num)/items/(:num) */
    public function deleteItem(int $cardId, int $itemId): \CodeIgniter\HTTP\ResponseInterface
    {
        $item = $this->_requireItem($cardId, $itemId);
        if ($item instanceof \CodeIgniter\HTTP\ResponseInterface) return $item;

        $this->itemModel->delete($itemId);
        $this->auditLog('Hapus biolink item', 'biolink_items', $itemId, $item);
        return $this->success(null, 'Link berhasil dihapus.');
    }

    /** PUT api/v1/biolink/cards/(:num)/items/reorder */
    public function reorderItems(int $cardId): \CodeIgniter\HTTP\ResponseInterface
    {
        $card = $this->_requireCard($cardId);
        if ($card instanceof \CodeIgniter\HTTP\ResponseInterface) return $card;

        $body  = $this->request->getJSON(true) ?? $this->request->getPost();
        $order = $body['order'] ?? [];
        if (!is_array($order) || empty($order)) {
            return $this->fail('Data urutan tidak valid.');
        }

        $this->itemModel->reorder($cardId, $order);
        return $this->success(null, 'Urutan berhasil disimpan.');
    }

    /** PATCH api/v1/biolink/cards/(:num)/items/(:num)/toggle */
    public function toggleItem(int $cardId, int $itemId): \CodeIgniter\HTTP\ResponseInterface
    {
        $item = $this->_requireItem($cardId, $itemId);
        if ($item instanceof \CodeIgniter\HTTP\ResponseInterface) return $item;

        $new = $item['is_active'] ? 0 : 1;
        $this->itemModel->update($itemId, ['is_active' => $new]);
        return $this->success(['is_active' => $new], 'Status link diperbarui.');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function _requireCard(int $cardId): array|\CodeIgniter\HTTP\ResponseInterface
    {
        $card = $this->cardModel->getOwnedById($cardId, $this->userId(), $this->userRole());
        if (!$card) return $this->fail('Card tidak ditemukan atau akses ditolak.', 404);
        return $card;
    }

    private function _requireItem(int $cardId, int $itemId): array|\CodeIgniter\HTTP\ResponseInterface
    {
        // Pastikan card dulu milik user
        $card = $this->_requireCard($cardId);
        if ($card instanceof \CodeIgniter\HTTP\ResponseInterface) return $card;

        $item = $this->itemModel->find($itemId);
        if (!$item || (int)$item['profile_id'] !== $cardId) {
            return $this->fail('Link tidak ditemukan.', 404);
        }
        return $item;
    }

    /**
     * Handle upload foto. Return path baru, path lama (jika tidak ada upload),
     * atau string 'ERROR:<pesan>' jika validasi gagal.
     */
    private function _handleFotoUpload(?string $existing): ?string
    {
        $file = $this->request->getFile('foto');
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            return $existing; // tidak ada upload baru
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            return 'ERROR:Ukuran foto maksimal 2 MB.';
        }
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowed, true)) {
            return 'ERROR:Tipe file tidak didukung. Gunakan JPG, PNG, atau WebP.';
        }

        if ($existing) {
            @unlink(WRITEPATH . 'uploads/biolink/' . $existing);
        }

        $dir = WRITEPATH . 'uploads/biolink/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $newName = 'bio_' . $this->userId() . '_' . time() . '.' . $file->guessExtension();
        $file->move($dir, $newName);
        return $newName;
    }

    private function _parseSosmed(mixed $raw): ?string
    {
        $arr = [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $arr = $decoded;
        } elseif (is_array($raw)) {
            $arr = $raw;
        }
        return !empty($arr) ? json_encode($arr) : null;
    }

    private function _cardResponse(int $cardId): array
    {
        $card = $this->cardModel->find($cardId);
        if (!$card) return [];
        $card['sosmed_json'] = $card['sosmed_json']
            ? json_decode($card['sosmed_json'], true) : [];
        $card['foto_url']  = $card['foto_path']
            ? base_url('biolink-foto/' . $card['foto_path']) : null;
        $card['cover_url'] = !empty($card['cover_path'])
            ? base_url('biolink-foto/' . $card['cover_path']) : null;
        $card['bg_url']    = !empty($card['bg_path'])
            ? base_url('biolink-foto/' . $card['bg_path']) : null;
        $card['publik_url'] = base_url('biolink/' . $card['slug']);
        return $card;
    }

    /**
     * Handle upload cover (gambar banner). Return path baru, path lama,
     * atau string 'ERROR:<pesan>' jika validasi gagal.
     */
    private function _handleCoverUpload(?string $existing): ?string
    {
        $file = $this->request->getFile('cover');
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            return $existing;
        }

        if ($file->getSize() > 4 * 1024 * 1024) {
            return 'ERROR:Ukuran cover maksimal 4 MB.';
        }
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowed, true)) {
            return 'ERROR:Tipe file cover tidak didukung. Gunakan JPG, PNG, atau WebP.';
        }

        if ($existing) @unlink(WRITEPATH . 'uploads/biolink/' . $existing);

        $dir = WRITEPATH . 'uploads/biolink/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $newName = 'cover_' . $this->userId() . '_' . time() . '.' . $file->guessExtension();
        $file->move($dir, $newName);
        return $newName;
    }

    /**
     * Handle upload background halaman. Return path baru, path lama,
     * atau string 'ERROR:<pesan>' jika validasi gagal.
     */
    private function _handleBgUpload(?string $existing): ?string
    {
        $file = $this->request->getFile('bg');
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            return $existing;
        }

        if ($file->getSize() > 6 * 1024 * 1024) {
            return 'ERROR:Ukuran background maksimal 6 MB.';
        }
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowed, true)) {
            return 'ERROR:Tipe file background tidak didukung. Gunakan JPG, PNG, atau WebP.';
        }

        if ($existing) @unlink(WRITEPATH . 'uploads/biolink/' . $existing);

        $dir = WRITEPATH . 'uploads/biolink/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $newName = 'bg_' . $this->userId() . '_' . time() . '.' . $file->guessExtension();
        $file->move($dir, $newName);
        return $newName;
    }
}
