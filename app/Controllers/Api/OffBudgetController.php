<?php

namespace App\Controllers\Api;

use App\Models\KegiatanModel;
use App\Models\OffBudgetSumberModel;
use App\Models\TaskModel;
use App\Models\RealisasiAnggaranModel;
use App\Models\TeamMemberModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * OffBudgetController — Kelola kegiatan off-budget & sumber anggarannya.
 *
 * Sumber anggaran off-budget bersifat many-to-many: satu off-budget
 * dapat mengambil anggaran dari beberapa kegiatan on-budget sekaligus.
 *
 * Hak akses pengaturan sumber: direktur + perencana_anggaran.
 */
class OffBudgetController extends BaseApiController
{
    private KegiatanModel       $model;
    private OffBudgetSumberModel $sumberModel;

    public function __construct()
    {
        $this->model       = new KegiatanModel();
        $this->sumberModel = new OffBudgetSumberModel();
    }

    // ====================================================================
    // LIST & DETAIL
    // ====================================================================

    /** GET /api/v1/off-budget — list semua kegiatan off-budget */
    public function index(): ResponseInterface
    {
        $rows = $this->model->getOffBudgetList();
        return $this->success($rows);
    }

    /** GET /api/v1/off-budget/:id — detail off-budget + tasks + realisasi + sumber list */
    public function show(int $id): ResponseInterface
    {
        $row = $this->model->getDetail($id);
        if (! $row) return $this->fail('Kegiatan tidak ditemukan.', 404);
        if ($row['tipe'] !== 'off_budget') return $this->fail('Kegiatan ini bukan off-budget.', 422);

        $row['tasks']           = (new TaskModel())->getByKegiatanWithSubtasks($id);
        $row['realisasi']       = (new RealisasiAnggaranModel())->getByKegiatan($id);
        $row['anggota']         = (new TeamMemberModel())->getByTeam((int) $row['team_id']);
        $row['sumber_ringkasan'] = $this->sumberModel->getRingkasanForOffBudget($id);

        return $this->success($row);
    }

    // ====================================================================
    // MANAGE SUMBER — hak akses: direktur + perencana_anggaran
    // ====================================================================

    /**
     * GET /api/v1/off-budget/:id/sumber
     * List semua sumber anggaran untuk satu kegiatan off-budget.
     */
    public function sumberIndex(int $offBudgetId): ResponseInterface
    {
        $this->validateOffBudget($offBudgetId);
        $rows = $this->sumberModel->getSumberByOffBudget($offBudgetId);
        return $this->success($rows);
    }

    /**
     * POST /api/v1/off-budget/:id/sumber
     * Tambah sumber anggaran baru ke kegiatan off-budget.
     * Body: { sumber_id, alokasi, keterangan? }
     */
    public function sumberCreate(int $offBudgetId): ResponseInterface
    {
        $this->requireRole(['direktur', 'perencana_anggaran']);
        $this->validateOffBudget($offBudgetId);

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        if (empty($data['sumber_id']))  return $this->fail("Field 'sumber_id' wajib diisi.", 422);
        if (! isset($data['alokasi']))  return $this->fail("Field 'alokasi' wajib diisi.", 422);

        // Validasi sumber harus on-budget
        $sumber = $this->model->find((int) $data['sumber_id']);
        if (! $sumber)                       return $this->fail('Kegiatan sumber tidak ditemukan.', 404);
        if ($sumber['tipe'] !== 'on_budget') return $this->fail('Kegiatan sumber harus bertipe on-budget.', 422);
        if ($sumber['id'] === $offBudgetId)  return $this->fail('Sumber tidak boleh merujuk ke dirinya sendiri.', 422);

        // Cegah duplikasi (per kegiatan + komponen)
        $rincianId = ! empty($data['rincian_anggaran_id']) ? (int) $data['rincian_anggaran_id'] : null;
        $existing  = $this->sumberModel->getExisting($offBudgetId, (int) $data['sumber_id'], $rincianId);
        if ($existing) return $this->fail('Sumber + komponen ini sudah ditambahkan sebelumnya.', 422);

        // Validasi alokasi tidak melebihi sisa komponen (jika dipilih) atau sisa kegiatan
        $alokasi = (float) $data['alokasi'];
        if ($rincianId !== null) {
            $sisaKomp = $this->getSisaKomponen($rincianId, null);
            if ($alokasi > $sisaKomp + 1) {
                return $this->fail(
                    "Alokasi melebihi sisa anggaran komponen (sisa: Rp " . number_format($sisaKomp, 0, ',', '.') . ").",
                    422
                );
            }
        } else {
            $ringkasan  = $this->sumberModel->getSumberRingkasan((int) $data['sumber_id']);
            $sisaSumber = $ringkasan['sisa_anggaran'] ?? 0;
            if ($alokasi > $sisaSumber + 1) {
                return $this->fail(
                    "Alokasi melebihi sisa anggaran sumber (sisa: Rp " . number_format($sisaSumber, 0, ',', '.') . ").",
                    422
                );
            }
        }

        $payload = [
            'off_budget_id'       => $offBudgetId,
            'sumber_id'           => $data['sumber_id'],
            'rincian_anggaran_id' => $rincianId,
            'alokasi'             => $alokasi,
            'keterangan'          => $data['keterangan'] ?? null,
            'created_by'          => $this->userId(),
        ];

        $this->sumberModel->insert($payload);
        $newId = $this->sumberModel->insertID();

        $this->syncPaguOffBudget($offBudgetId);
        $this->auditLog(
            "Tambah sumber OB #{$offBudgetId} dari sumber #{$data['sumber_id']}" .
            ($rincianId ? " komponen #{$rincianId}" : '') . " alokasi Rp{$alokasi}",
            'off_budget_sumber', $newId, null, $payload
        );

        return $this->success(['id' => $newId], 'Sumber anggaran berhasil ditambahkan.', 201);
    }

    /**
     * PUT /api/v1/off-budget/:id/sumber/:sumberId
     * Ubah alokasi atau keterangan sumber.
     */
    public function sumberUpdate(int $offBudgetId, int $rowId): ResponseInterface
    {
        $this->requireRole(['direktur', 'perencana_anggaran']);

        $row = $this->sumberModel->find($rowId);
        if (! $row || $row['off_budget_id'] != $offBudgetId) {
            return $this->fail('Data sumber tidak ditemukan.', 404);
        }

        $data    = $this->request->getJSON(true) ?? [];
        $allowed = ['alokasi', 'keterangan', 'rincian_anggaran_id'];
        $update  = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) return $this->fail('Tidak ada field yang diubah.', 422);

        // Validasi alokasi baru tidak melebihi sisa komponen / sumber
        if (isset($update['alokasi'])) {
            $rincianId = isset($update['rincian_anggaran_id'])
                ? ((int) $update['rincian_anggaran_id'] ?: null)
                : (! empty($row['rincian_anggaran_id']) ? (int) $row['rincian_anggaran_id'] : null);

            if ($rincianId !== null) {
                // Sisa komponen + alokasi lama baris ini
                $sisaKomp     = $this->getSisaKomponen($rincianId, $rowId);
                $sisaTersedia = $sisaKomp + (float) $row['alokasi'];
                if ((float) $update['alokasi'] > $sisaTersedia + 1) {
                    return $this->fail(
                        "Alokasi melebihi sisa anggaran komponen (tersedia: Rp " . number_format($sisaTersedia, 0, ',', '.') . ").",
                        422
                    );
                }
            } else {
                $ringkasan    = $this->sumberModel->getSumberRingkasan((int) $row['sumber_id']);
                $sisaTersedia = ($ringkasan['sisa_anggaran'] ?? 0) + (float) $row['alokasi'];
                if ((float) $update['alokasi'] > $sisaTersedia + 1) {
                    return $this->fail(
                        "Alokasi melebihi sisa anggaran sumber (tersedia: Rp " . number_format($sisaTersedia, 0, ',', '.') . ").",
                        422
                    );
                }
            }
        }

        // Konversi rincian_anggaran_id kosong → null
        if (array_key_exists('rincian_anggaran_id', $update)) {
            $update['rincian_anggaran_id'] = $update['rincian_anggaran_id'] ? (int) $update['rincian_anggaran_id'] : null;
        }

        $this->sumberModel->update($rowId, $update);
        $this->syncPaguOffBudget($offBudgetId);
        $this->auditLog("Update alokasi sumber #{$rowId} OB #{$offBudgetId}", 'off_budget_sumber', $rowId, $row, $update);

        return $this->success(null, 'Sumber anggaran diperbarui.');
    }

    /**
     * DELETE /api/v1/off-budget/:id/sumber/:rowId
     * Hapus sumber anggaran dari off-budget.
     */
    public function sumberDelete(int $offBudgetId, int $rowId): ResponseInterface
    {
        $this->requireRole(['direktur', 'perencana_anggaran']);

        $row = $this->sumberModel->find($rowId);
        if (! $row || $row['off_budget_id'] != $offBudgetId) {
            return $this->fail('Data sumber tidak ditemukan.', 404);
        }

        $this->sumberModel->delete($rowId);

        // Sync pagu off-budget = total alokasi semua sumber
        $this->syncPaguOffBudget($offBudgetId);

        $this->auditLog("Hapus sumber #{$rowId} dari off-budget #{$offBudgetId}", 'off_budget_sumber', $rowId, $row);

        return $this->success(null, 'Sumber anggaran dihapus.');
    }

    // ====================================================================
    // RINCIAN DARI SUMBER — derived rincian rows untuk off-budget
    // ====================================================================

    /**
     * GET /api/v1/off-budget/:id/rincian
     *
     * Mengembalikan baris rincian anggaran yang dialokasikan ke off-budget ini,
     * diambil dari tabel off_budget_sumber JOIN rincian_anggaran.
     *
     * Setiap baris = satu entri sumber dengan komponen spesifik.
     * Kolom yang dikembalikan sama dengan format /rincian/sisa agar
     * frontend dapat memakai renderRincianTable() yang sama.
     *
     * Kolom tambahan: sumber_nama, alokasi (batas atas realisasi OB)
     */
    public function rincianDariSumber(int $offBudgetId): ResponseInterface
    {
        $k = $this->model->find($offBudgetId);
        if (! $k || $k['tipe'] !== 'off_budget') {
            return $this->fail('Kegiatan tidak ditemukan atau bukan off-budget.', 404);
        }

        $db   = \Config\Database::connect();
        $rows = $db->query("
            SELECT
                obs.id              AS obs_id,
                obs.alokasi,
                obs.keterangan,
                ks.nama_kegiatan    AS sumber_nama,
                obs.sumber_id,

                -- Kolom rincian dari kegiatan sumber (NULL jika tidak pilih komponen)
                ra.id               AS rincian_id,
                COALESCE(ra.komponen, CONCAT('Alokasi dari: ', ks.nama_kegiatan)) AS komponen,
                COALESCE(ra.uraian,  obs.keterangan)                              AS uraian,
                COALESCE(ra.qty,     1)                                           AS qty,
                COALESCE(ra.satuan_qty, '')                                       AS satuan_qty,
                COALESCE(ra.volume,  1)                                           AS volume,
                COALESCE(ra.satuan_vol, '')                                       AS satuan_vol,
                COALESCE(ra.harga_satuan, obs.alokasi)                            AS harga_satuan,

                -- Subtotal di sini = alokasi yang ditetapkan (bukan subtotal sumber)
                obs.alokasi                                                       AS subtotal,

                -- Realisasi off-budget yang sudah terpakai dari sumber ini
                COALESCE(SUM(real.nominal), 0)                                    AS nominal_terpakai,
                obs.alokasi - COALESCE(SUM(real.nominal), 0)                      AS nominal_sisa,
                obs.alokasi - COALESCE(SUM(real.nominal), 0)                      AS alokasi_ob_porsi

            FROM off_budget_sumber obs
            INNER JOIN kegiatan ks       ON ks.id  = obs.sumber_id
            LEFT  JOIN rincian_anggaran ra ON ra.id = obs.rincian_anggaran_id
            LEFT  JOIN realisasi_anggaran real
                    ON real.kegiatan_id  = {$offBudgetId}
                   AND real.sumber_ob_id = obs.id
            WHERE obs.off_budget_id = {$offBudgetId}
            GROUP BY obs.id
            ORDER BY ks.nama_kegiatan ASC, ra.urutan ASC, obs.id ASC
        ")->getResultArray();

        $total = array_sum(array_column($rows, 'subtotal'));

        return $this->success([
            'rows'  => $rows,
            'total' => $total,
        ]);
    }

    // ====================================================================
    // RINGKASAN SUMBER (on-budget)
    // ====================================================================

    /** GET /api/v1/off-budget/sumber-ringkasan/:sumberId
     *  Ringkasan sebuah kegiatan on-budget: pagu, total alokasi OB, sisa. */
    public function sumberRingkasan(int $sumberId): ResponseInterface
    {
        $sumber = $this->model->find($sumberId);
        if (! $sumber) return $this->fail('Kegiatan sumber tidak ditemukan.', 404);

        $data = $this->sumberModel->getSumberRingkasan($sumberId);
        return $this->success($data);
    }

    // ====================================================================
    // Helpers
    // ====================================================================

    /**
     * Hitung sisa anggaran komponen (subtotal - alokasi_ob_sudah_ada - realisasi_on).
     * Jika excludeRowId diberikan, alokasi baris tersebut tidak dihitung
     * (digunakan saat update — baris lama dikecualikan).
     */
    private function getSisaKomponen(int $rincianId, ?int $excludeRowId): float
    {
        $db = \Config\Database::connect();

        $rincian = $db->query(
            "SELECT subtotal FROM rincian_anggaran WHERE id = {$rincianId}"
        )->getFirstRow('array');
        if (! $rincian) return 0.0;

        $subtotal = (float) $rincian['subtotal'];

        // Alokasi OB yang sudah terkunci ke komponen ini (kecuali baris yg sedang diedit)
        $excludeSql = $excludeRowId ? "AND id != {$excludeRowId}" : '';
        $alokasiOb  = (float) ($db->query("
            SELECT COALESCE(SUM(alokasi), 0) AS total
            FROM off_budget_sumber
            WHERE rincian_anggaran_id = {$rincianId} {$excludeSql}
        ")->getFirstRow('array')['total'] ?? 0);

        // Realisasi on-budget yang sudah terpakai dari komponen ini
        $realisasi = (float) ($db->query("
            SELECT COALESCE(SUM(nominal), 0) AS total
            FROM realisasi_anggaran
            WHERE rincian_anggaran_id = {$rincianId}
        ")->getFirstRow('array')['total'] ?? 0);

        return max(0.0, $subtotal - $alokasiOb - $realisasi);
    }

    /**
     * Sync pagu_anggaran kegiatan off-budget = total alokasi dari semua sumber.
     * Dipanggil setiap kali sumber ditambah / diubah / dihapus.
     */
    private function syncPaguOffBudget(int $offBudgetId): void
    {
        $db    = \Config\Database::connect();
        $total = $db->query("
            SELECT COALESCE(SUM(alokasi), 0) AS total
            FROM off_budget_sumber
            WHERE off_budget_id = {$offBudgetId}
        ")->getFirstRow('array')['total'] ?? 0;

        $this->model->update($offBudgetId, ['pagu_anggaran' => $total]);
    }

    private function validateOffBudget(int $id): void
    {
        $k = $this->model->find($id);
        if (! $k || $k['tipe'] !== 'off_budget') {
            throw new \RuntimeException("Kegiatan #{$id} bukan off-budget atau tidak ditemukan.");
        }
    }
}
