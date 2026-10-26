<?php

namespace App\Controllers\Api;

use App\Models\KegiatanModel;
use App\Models\RincianAnggaranModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * RincianAnggaranController
 *
 * Mengelola komponen biaya (rincian anggaran) per kegiatan.
 * Hak akses: direktur + perencana_anggaran (write), semua role (read).
 *
 * Setiap kali rincian berubah, pagu_anggaran kegiatan di-sync otomatis
 * ke SUM(subtotal) seluruh rincian.
 */
class RincianAnggaranController extends BaseApiController
{
    private RincianAnggaranModel $model;
    private KegiatanModel        $kegiatanModel;

    public function __construct()
    {
        $this->model         = new RincianAnggaranModel();
        $this->kegiatanModel = new KegiatanModel();
    }

    // ----------------------------------------------------------------
    // GET /api/v1/kegiatan/:id/rincian
    // ----------------------------------------------------------------
    public function index(int $kegiatanId): ResponseInterface
    {
        $kegiatan = $this->kegiatanModel->find($kegiatanId);
        if (! $kegiatan) return $this->fail('Kegiatan tidak ditemukan.', 404);

        $rows  = $this->model->getByKegiatan($kegiatanId);
        $total = $this->model->getTotalByKegiatan($kegiatanId);

        return $this->success([
            'rows'  => $rows,
            'total' => $total,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /api/v1/kegiatan/:id/rincian/sisa
    // Kembalikan setiap komponen beserta sisa volume & nominal sisa.
    // Digunakan oleh form Input Realisasi (on-budget) agar user bisa
    // memilih komponen & sistem otomatis split ke komponen berikutnya.
    // ----------------------------------------------------------------
    public function sisa(int $kegiatanId): ResponseInterface
    {
        $kegiatan = $this->kegiatanModel->find($kegiatanId);
        if (! $kegiatan) return $this->fail('Kegiatan tidak ditemukan.', 404);

        $rows = $this->model->getSisaByKegiatan($kegiatanId);
        return $this->success(['rows' => $rows]);
    }

    // ----------------------------------------------------------------
    // POST /api/v1/kegiatan/:id/rincian
    // ----------------------------------------------------------------
    public function create(int $kegiatanId): ResponseInterface
    {
        $this->requireRole(['direktur', 'perencana_anggaran']);

        $kegiatan = $this->kegiatanModel->find($kegiatanId);
        if (! $kegiatan) return $this->fail('Kegiatan tidak ditemukan.', 404);

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'komponen'     => 'required|max_length[300]',
            'qty'          => 'required|numeric',
            'volume'       => 'required|numeric',
            'harga_satuan' => 'required|numeric',
        ];
        if (! $this->validate($rules)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $qty      = (float) $data['qty'];
        $volume   = (float) $data['volume'];
        $harga    = (float) $data['harga_satuan'];
        $subtotal = RincianAnggaranModel::hitungSubtotal($qty, $volume, $harga);

        $insert = [
            'kegiatan_id'  => $kegiatanId,
            'urutan'       => $this->model->getNextUrutan($kegiatanId),
            'komponen'     => trim($data['komponen']),
            'uraian'       => isset($data['uraian']) ? trim($data['uraian']) : null,
            'qty'          => $qty,
            'satuan_qty'   => $data['satuan_qty'] ?? null,
            'volume'       => $volume,
            'satuan_vol'   => $data['satuan_vol'] ?? null,
            'harga_satuan' => $harga,
            'subtotal'     => $subtotal,
            'created_by'   => $this->userId(),
        ];

        $this->model->insert($insert);
        $newId = $this->model->insertID();

        $this->syncPagu($kegiatanId);
        $this->auditLog("Tambah rincian anggaran: {$insert['komponen']}", 'rincian_anggaran', $newId, null, $insert);

        return $this->success(['id' => $newId, 'subtotal' => $subtotal], 'Rincian berhasil ditambahkan.', 201);
    }

    // ----------------------------------------------------------------
    // PUT /api/v1/kegiatan/:id/rincian/:rid
    // ----------------------------------------------------------------
    public function update(int $kegiatanId, int $rid): ResponseInterface
    {
        $this->requireRole(['direktur', 'perencana_anggaran']);

        $row = $this->model->find($rid);
        if (! $row || (int) $row['kegiatan_id'] !== $kegiatanId) {
            return $this->fail('Rincian tidak ditemukan.', 404);
        }

        $data    = $this->request->getJSON(true) ?? [];
        $allowed = ['komponen', 'uraian', 'qty', 'satuan_qty', 'volume', 'satuan_vol', 'harga_satuan', 'urutan'];
        $update  = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) return $this->fail('Tidak ada data yang diubah.', 422);

        // Recalculate subtotal jika salah satu komponen perhitungan berubah
        $qty   = isset($update['qty'])          ? (float) $update['qty']          : (float) $row['qty'];
        $vol   = isset($update['volume'])       ? (float) $update['volume']       : (float) $row['volume'];
        $harga = isset($update['harga_satuan']) ? (float) $update['harga_satuan'] : (float) $row['harga_satuan'];
        $update['subtotal'] = RincianAnggaranModel::hitungSubtotal($qty, $vol, $harga);

        if (isset($update['komponen']))    $update['komponen'] = trim($update['komponen']);
        if (isset($update['uraian']))      $update['uraian']   = trim($update['uraian']) ?: null;

        $this->model->update($rid, $update);
        $this->syncPagu($kegiatanId);
        $this->auditLog("Update rincian anggaran #{$rid}", 'rincian_anggaran', $rid, $row, $update);

        return $this->success(['subtotal' => $update['subtotal']], 'Rincian berhasil diperbarui.');
    }

    // ----------------------------------------------------------------
    // DELETE /api/v1/kegiatan/:id/rincian/:rid
    // ----------------------------------------------------------------
    public function delete(int $kegiatanId, int $rid): ResponseInterface
    {
        $this->requireRole(['direktur', 'perencana_anggaran']);

        $row = $this->model->find($rid);
        if (! $row || (int) $row['kegiatan_id'] !== $kegiatanId) {
            return $this->fail('Rincian tidak ditemukan.', 404);
        }

        $this->model->delete($rid);
        $this->syncPagu($kegiatanId);
        $this->auditLog("Hapus rincian anggaran: {$row['komponen']}", 'rincian_anggaran', $rid, $row, null);

        return $this->success(null, 'Rincian berhasil dihapus.');
    }

    // ----------------------------------------------------------------
    // Sync pagu_anggaran kegiatan = SUM(subtotal) semua rincian
    // ----------------------------------------------------------------
    private function syncPagu(int $kegiatanId): void
    {
        $total = $this->model->getTotalByKegiatan($kegiatanId);
        $this->kegiatanModel->update($kegiatanId, ['pagu_anggaran' => $total]);
    }
}
