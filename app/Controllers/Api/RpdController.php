<?php

namespace App\Controllers\Api;

use App\Models\RencanaPenarikanDanaModel;
use App\Models\KegiatanModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * RpdController — CRUD Rencana Penarikan Dana (per bulan).
 * Setiap baris RPD merujuk ke satu Kegiatan + satu tahun anggaran.
 * Hanya bisa diakses oleh role: direktur, perencana_anggaran.
 */
class RpdController extends BaseApiController
{
    private array $bulanFields = [
        'bln_01','bln_02','bln_03','bln_04','bln_05','bln_06',
        'bln_07','bln_08','bln_09','bln_10','bln_11','bln_12',
    ];

    // ----------------------------------------------------------------
    // List semua RPD joined ke kegiatan untuk tahun tertentu
    // ----------------------------------------------------------------
    public function index(): ResponseInterface
    {
        $tahun = (int) ($this->request->getGet('tahun') ?? date('Y'));
        $db    = \Config\Database::connect();

        $rows = $db->query("
            SELECT rpd.*, k.nama_kegiatan, k.pagu_anggaran AS kegiatan_pagu, k.status AS kegiatan_status
            FROM rencana_penarikan_dana rpd
            INNER JOIN kegiatan k ON k.id = rpd.kegiatan_id
            WHERE rpd.tahun_anggaran = {$tahun}
            ORDER BY k.nama_kegiatan ASC
        ")->getResultArray();

        // Cast kolom bulan ke float
        foreach ($rows as &$row) {
            foreach ($this->bulanFields as $f) {
                $row[$f] = (float)($row[$f] ?? 0);
            }
        }

        return $this->success($rows);
    }

    // ----------------------------------------------------------------
    // Simpan (insert atau update) RPD kegiatan + tahun
    // ----------------------------------------------------------------
    public function save(): ResponseInterface
    {
        $this->requireRole(['direktur', 'perencana_anggaran']);
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        if (empty($data['kegiatan_id'])) return $this->fail("Field 'kegiatan_id' wajib diisi.", 422);
        if (empty($data['tahun_anggaran'])) return $this->fail("Field 'tahun_anggaran' wajib diisi.", 422);

        $kegiatan = (new KegiatanModel())->find((int) $data['kegiatan_id']);
        if (! $kegiatan) return $this->fail('Kegiatan tidak ditemukan.', 404);

        $model    = new RencanaPenarikanDanaModel();
        $existing = $model->getByKegiatanTahun((int) $data['kegiatan_id'], (int) $data['tahun_anggaran']);

        $payload = [
            'kegiatan_id'    => $data['kegiatan_id'],
            'tahun_anggaran' => $data['tahun_anggaran'],
            'pagu_anggaran'  => $data['pagu_anggaran'] ?? 0,
            'keterangan'     => $data['keterangan'] ?? null,
            'created_by'     => $this->userId(),
        ];

        // Tambah 12 kolom bulan
        foreach ($this->bulanFields as $f) {
            $payload[$f] = isset($data[$f]) ? (float) $data[$f] : 0;
        }

        if ($existing) {
            $model->update($existing['id'], $payload);
            $this->auditLog(
                "Update RPD kegiatan '{$kegiatan['nama_kegiatan']}' tahun {$data['tahun_anggaran']}",
                'rencana_penarikan_dana', $existing['id'], $existing, $payload
            );
            return $this->success(['id' => $existing['id']], 'RPD berhasil diperbarui.');
        }

        $model->insert($payload);
        $newId = $model->insertID();
        $this->auditLog(
            "Buat RPD kegiatan '{$kegiatan['nama_kegiatan']}' tahun {$data['tahun_anggaran']}",
            'rencana_penarikan_dana', $newId, null, $payload
        );
        return $this->success(['id' => $newId], 'RPD berhasil disimpan.', 201);
    }

    // ----------------------------------------------------------------
    // Hapus RPD
    // ----------------------------------------------------------------
    public function delete(int $id): ResponseInterface
    {
        $this->requireRole(['direktur', 'perencana_anggaran']);
        $row = (new RencanaPenarikanDanaModel())->find($id);
        if (! $row) return $this->fail('RPD tidak ditemukan.', 404);
        (new RencanaPenarikanDanaModel())->delete($id);
        $this->auditLog("Hapus RPD #{$id}", 'rencana_penarikan_dana', $id, $row);
        return $this->success(null, 'RPD dihapus.');
    }

    // ----------------------------------------------------------------
    // Dashboard tables: Tabel Realisasi + Tabel RPD bulanan
    // ----------------------------------------------------------------
    public function dashboardTables(): ResponseInterface
    {
        $tahun = (int) ($this->request->getGet('tahun') ?? date('Y'));
        $model = new RencanaPenarikanDanaModel();

        $realisasi = $model->getTabelRealisasi($tahun);
        $rpd       = $model->getTabelRpd($tahun);
        $kegiatan  = (new KegiatanModel())
                        ->select('id, nama_kegiatan, pagu_anggaran, status')
                        ->orderBy('nama_kegiatan')
                        ->findAll();

        // Hitung total realisasi
        $totalReal = ['pagu_anggaran' => 0, 'real_total' => 0, 'pct_serapan' => 0, 'sisa_anggaran' => 0];
        for ($m = 1; $m <= 12; $m++) {
            $pad = str_pad($m, 2, '0', STR_PAD_LEFT);
            $totalReal["real_{$pad}"] = array_sum(array_column($realisasi['rows'], "real_{$pad}"));
        }
        $totalReal['pagu_anggaran'] = array_sum(array_column($realisasi['rows'], 'pagu_anggaran'));
        $totalReal['real_total']    = array_sum(array_column($realisasi['rows'], 'real_total'));
        $totalReal['pct_serapan']   = $totalReal['pagu_anggaran'] > 0
            ? round(($totalReal['real_total'] / $totalReal['pagu_anggaran']) * 100, 2) : 0;
        $totalReal['sisa_anggaran'] = $totalReal['pagu_anggaran'] - $totalReal['real_total'];

        // Hitung total RPD
        $totalRpd = ['rpd_total' => 0, 'real_total' => 0, 'pct_real_vs_rpd' => 0, 'selisih' => 0];
        for ($m = 1; $m <= 12; $m++) {
            $pad = str_pad($m, 2, '0', STR_PAD_LEFT);
            $totalRpd["bln_{$pad}"]  = array_sum(array_column($rpd['rows'], "bln_{$pad}"));
            $totalRpd["real_{$pad}"] = array_sum(array_column($rpd['rows'], "real_{$pad}"));
        }
        $totalRpd['rpd_total']       = array_sum(array_column($rpd['rows'], 'rpd_total'));
        $totalRpd['real_total']      = array_sum(array_column($rpd['rows'], 'real_total'));
        $totalRpd['pct_real_vs_rpd'] = $totalRpd['rpd_total'] > 0
            ? round(($totalRpd['real_total'] / $totalRpd['rpd_total']) * 100, 2) : 0;
        $totalRpd['selisih']         = $totalRpd['rpd_total'] - $totalRpd['real_total'];

        return $this->success([
            'tahun'           => $tahun,
            'tabel_realisasi' => ['rows' => $realisasi['rows'], 'total' => $totalReal],
            'tabel_rpd'       => ['rows' => $rpd['rows'],       'total' => $totalRpd],
            'kegiatan_list'   => $kegiatan,
        ]);
    }
}
