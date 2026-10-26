<?php

namespace App\Controllers\Api;

use App\Models\KegiatanModel;
use App\Models\OffBudgetSumberModel;
use App\Models\NotifikasiModel;
use App\Models\RealisasiAnggaranModel;
use App\Models\RincianAnggaranModel;
use CodeIgniter\HTTP\ResponseInterface;

class AnggaranController extends BaseApiController
{
    private RealisasiAnggaranModel $model;

    public function __construct()
    {
        $this->model = new RealisasiAnggaranModel();
    }

    public function index(int $kegiatanId): ResponseInterface
    {
        $detail  = $this->model->getByKegiatan($kegiatanId);
        $summary = $this->model->getSummary($kegiatanId);
        return $this->success(['summary' => $summary, 'detail' => $detail]);
    }

    // ================================================================
    // CREATE — mendukung dua mode:
    //   1. On-budget: { tanggal, uraian, nominal, bukti_transaksi? }
    //   2. Off-budget: { tanggal, uraian, nominal, bukti_transaksi?,
    //                    splits: [{sumber_ob_id, nominal}, ...] }
    //      - splits.nominal boleh melebihi alokasi sumber JIKA total splits
    //        = nominal keseluruhan DAN setiap sumber masih punya sisa alokasi.
    //      - Jika hanya ada satu sumber: nominal otomatis di-assign ke sumber tsb,
    //        jika melebihi → error karena tidak ada sumber lain.
    // ================================================================
    public function create(int $kegiatanId): ResponseInterface
    {
        $this->requireRole(['direktur', 'ketua_tim', 'anggota_tim']);

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'tanggal' => 'required|valid_date[Y-m-d]',
            'uraian'  => 'required|max_length[255]',
            'nominal' => 'required|numeric',
        ];
        if (! $this->validate($rules)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $kegiatanModel = new KegiatanModel();
        $kegiatan      = $kegiatanModel->find($kegiatanId);
        if (! $kegiatan) return $this->fail('Kegiatan tidak ditemukan.', 404);

        $nominalTotal  = (float) $data['nominal'];
        $tanggal       = $data['tanggal'];
        $uraian        = $data['uraian'];
        $bukti         = $data['bukti_transaksi'] ?? null;

        // ----------------------------------------------------------------
        // OFF-BUDGET: proses splits
        // ----------------------------------------------------------------
        if ($kegiatan['tipe'] === 'off_budget') {
            $sumberModel = new OffBudgetSumberModel();

            // Normalisasi splits dari request
            $splitsRaw = $data['splits'] ?? null;

            // Backward compat: single sumber_ob_id → convert ke splits array
            if (empty($splitsRaw) && !empty($data['sumber_ob_id'])) {
                $splitsRaw = [['sumber_ob_id' => (int) $data['sumber_ob_id'], 'nominal' => $nominalTotal]];
            }

            if (empty($splitsRaw)) {
                return $this->fail('Kegiatan off-budget wajib memilih sumber anggaran.', 422);
            }

            // Ambil semua sumber milik off-budget ini beserta sisa alokasinya
            $allSumber = $this->getSumberWithSisa($kegiatanId);
            $sumberMap = [];
            foreach ($allSumber as $s) {
                $sumberMap[$s['id']] = $s;
            }

            // Validasi setiap split & hitung total splits
            $totalSplits = 0.0;
            $validSplits = [];

            foreach ($splitsRaw as $sp) {
                $sid       = (int) ($sp['sumber_ob_id'] ?? 0);
                $spNominal = (float) ($sp['nominal'] ?? 0);

                if ($spNominal <= 0) continue;    // skip entry 0

                if (! isset($sumberMap[$sid])) {
                    return $this->fail("Sumber #{$sid} tidak valid untuk kegiatan ini.", 422);
                }

                $sisa = (float) $sumberMap[$sid]['sisa_alokasi'];
                if ($spNominal > $sisa) {
                    $nm = $sumberMap[$sid]['sumber_nama'];
                    return $this->fail(
                        "Nominal untuk sumber \"{$nm}\" (Rp " . number_format($spNominal, 0, ',', '.') .
                        ") melebihi sisa alokasi (Rp " . number_format($sisa, 0, ',', '.') . ").",
                        422
                    );
                }

                $totalSplits += $spNominal;
                $validSplits[] = ['sumber_ob_id' => $sid, 'nominal' => $spNominal, 'sumber_row' => $sumberMap[$sid]];
            }

            if (empty($validSplits)) {
                return $this->fail('Tidak ada nominal yang valid pada splits.', 422);
            }

            // Total splits harus sama dengan nominal keseluruhan
            if (abs($totalSplits - $nominalTotal) > 1) {   // toleransi 1 rupiah untuk float
                return $this->fail(
                    "Total split (Rp " . number_format($totalSplits, 0, ',', '.') .
                    ") tidak sama dengan nominal (Rp " . number_format($nominalTotal, 0, ',', '.') . ").",
                    422
                );
            }

            // ---- Insert satu entry per split ----
            $insertedIds = [];
            foreach ($validSplits as $sp) {
                $sid      = $sp['sumber_ob_id'];
                $spNom    = $sp['nominal'];
                $sumberNm = $sp['sumber_row']['sumber_nama'];

                $insertData = [
                    'kegiatan_id'     => $kegiatanId,
                    'tanggal'         => $tanggal,
                    'uraian'          => $uraian . (count($validSplits) > 1 ? " [{$sumberNm}]" : ''),
                    'nominal'         => $spNom,
                    'bukti_transaksi' => $bukti,
                    'created_by'      => $this->userId(),
                    'sumber_ob_id'    => $sid,
                ];
                $this->model->insert($insertData);
                $newId = $this->model->insertID();

                // Mirror ke on-budget sumber
                $onBudgetKegId = (int) $sp['sumber_row']['sumber_id'];
                $mirrorData = [
                    'kegiatan_id'      => $onBudgetKegId,
                    'tanggal'          => $tanggal,
                    'uraian'           => "[OB] {$kegiatan['nama_kegiatan']}: {$uraian}",
                    'nominal'          => $spNom,
                    'bukti_transaksi'  => $bukti,
                    'created_by'       => $this->userId(),
                    'ref_realisasi_id' => $newId,
                ];
                $this->model->insert($mirrorData);
                $mirrorId = $this->model->insertID();

                // Cross-ref: entry OB → mirror
                $this->model->db->query(
                    "UPDATE realisasi_anggaran SET ref_realisasi_id = {$mirrorId} WHERE id = {$newId}"
                );

                $this->auditLog(
                    "Input realisasi OB split sumber #{$sid}: {$uraian} Rp" . number_format($spNom, 0),
                    'realisasi_anggaran', $newId, null, $insertData
                );
                $insertedIds[] = $newId;
            }

            // Notifikasi serapan ≥ 80%
            $this->cekNotifikasiSerapan($kegiatanId, $kegiatan);

            return $this->success(
                ['ids' => $insertedIds, 'count' => count($insertedIds)],
                'Realisasi anggaran berhasil dicatat (' . count($insertedIds) . ' entri).',
                201
            );
        }

        // ----------------------------------------------------------------
        // ON-BUDGET: normal atau dengan komponen splits
        // ----------------------------------------------------------------

        // Jika ada komponen_splits, proses split antar komponen
        $kompSplits = $data['komponen_splits'] ?? null;
        if (! empty($kompSplits)) {
            return $this->createOnBudgetWithSplits(
                $kegiatanId, $kegiatan, $tanggal, $uraian, $bukti, $nominalTotal, $kompSplits
            );
        }

        // Fallback: insert biasa tanpa komponen
        $insertData = [
            'kegiatan_id'     => $kegiatanId,
            'tanggal'         => $tanggal,
            'uraian'          => $uraian,
            'nominal'         => $nominalTotal,
            'bukti_transaksi' => $bukti,
            'created_by'      => $this->userId(),
        ];
        $this->model->insert($insertData);
        $newId = $this->model->insertID();

        $this->auditLog("Input realisasi anggaran: {$uraian}", 'realisasi_anggaran', $newId, null, $insertData);
        $this->cekNotifikasiSerapan($kegiatanId, $kegiatan);

        return $this->success(['id' => $newId], 'Realisasi anggaran berhasil dicatat.', 201);
    }

    // ================================================================
    // ON-BUDGET WITH KOMPONEN SPLITS
    // ================================================================
    // komponen_splits = [ { rincian_id, nominal }, ... ]
    // Setiap entri = satu baris realisasi yang terkait ke satu rincian.
    // Validasi: nominal split TIDAK BOLEH melebihi sisa nominal komponen (subtotal - terpakai).
    // Nominal boleh kurang (realisasi aktual bisa lebih hemat dari anggaran).
    // Total nominal splits harus = nominalTotal.
    // ================================================================
    private function createOnBudgetWithSplits(
        int $kegiatanId, array $kegiatan,
        string $tanggal, string $uraian, ?string $bukti,
        float $nominalTotal, array $splitsRaw
    ): ResponseInterface {
        $rincianModel = new RincianAnggaranModel();
        $sisaRows     = $rincianModel->getSisaByKegiatan($kegiatanId);

        // Buat map rincian_id => data sisa
        $sisaMap = [];
        foreach ($sisaRows as $r) {
            $sisaMap[(int) $r['id']] = $r;
        }

        $totalSplits = 0.0;
        $validSplits = [];

        foreach ($splitsRaw as $sp) {
            $rid   = (int) ($sp['rincian_id'] ?? 0);
            $spNom = (float) ($sp['nominal'] ?? 0);

            if ($spNom <= 0) continue;  // abaikan baris kosong

            if (! isset($sisaMap[$rid])) {
                return $this->fail("Komponen #{$rid} tidak valid untuk kegiatan ini.", 422);
            }

            $rincian  = $sisaMap[$rid];
            $sisaNom  = (float) $rincian['nominal_sisa'];

            // Validasi: nominal tidak boleh melebihi sisa anggaran komponen
            if ($spNom > $sisaNom + 1) {   // toleransi 1 rupiah untuk float
                $nm = $rincian['komponen'];
                return $this->fail(
                    "Nominal untuk komponen \"{$nm}\" (Rp " . number_format($spNom, 0, ',', '.') .
                    ") melebihi sisa anggaran komponen (Rp " . number_format($sisaNom, 0, ',', '.') . ").",
                    422
                );
            }

            $totalSplits += $spNom;
            $validSplits[] = [
                'rincian_id' => $rid,
                'nominal'    => $spNom,
                'komponen'   => $rincian['komponen'],
            ];
        }

        if (empty($validSplits)) {
            return $this->fail('Tidak ada split yang valid.', 422);
        }

        // Total splits harus sama dengan nominal keseluruhan (toleransi 1 rupiah)
        if (abs($totalSplits - $nominalTotal) > 1) {
            return $this->fail(
                "Total split (Rp " . number_format($totalSplits, 0, ',', '.') .
                ") tidak sama dengan nominal (Rp " . number_format($nominalTotal, 0, ',', '.') . ").",
                422
            );
        }

        $insertedIds = [];
        foreach ($validSplits as $sp) {
            $label = count($validSplits) > 1 ? " [{$sp['komponen']}]" : '';
            $insertData = [
                'kegiatan_id'         => $kegiatanId,
                'tanggal'             => $tanggal,
                'uraian'              => $uraian . $label,
                'nominal'             => $sp['nominal'],
                'bukti_transaksi'     => $bukti,
                'created_by'          => $this->userId(),
                'rincian_anggaran_id' => $sp['rincian_id'],
                // volume_dipakai tidak digunakan — tracking via nominal
            ];
            $this->model->insert($insertData);
            $newId = $this->model->insertID();

            $this->auditLog(
                "Input realisasi komponen #{$sp['rincian_id']}: {$uraian}",
                'realisasi_anggaran', $newId, null, $insertData
            );
            $insertedIds[] = $newId;
        }

        $this->cekNotifikasiSerapan($kegiatanId, $kegiatan);

        return $this->success(
            ['ids' => $insertedIds, 'count' => count($insertedIds)],
            'Realisasi anggaran berhasil dicatat (' . count($insertedIds) . ' entri).',
            201
        );
    }

    public function update(int $kegiatanId, int $id): ResponseInterface
    {
        $this->requireRole(['direktur', 'ketua_tim', 'anggota_tim']);

        $realisasi = $this->model->find($id);
        if (! $realisasi || (int) $realisasi['kegiatan_id'] !== $kegiatanId) {
            return $this->fail('Realisasi tidak ditemukan.', 404);
        }

        $data       = $this->request->getJSON(true) ?? [];
        $allowed    = ['tanggal', 'uraian', 'nominal', 'bukti_transaksi'];
        $updateData = array_intersect_key($data, array_flip($allowed));

        if (empty($updateData)) return $this->fail('Tidak ada data yang dapat diubah.', 422);

        $this->model->update($id, $updateData);

        // Sync mirror entry jika ada
        if (! empty($realisasi['ref_realisasi_id'])) {
            $mirrorAllowed = array_intersect_key($updateData, array_flip(['tanggal', 'nominal', 'bukti_transaksi']));
            if (! empty($mirrorAllowed)) {
                $this->model->update((int) $realisasi['ref_realisasi_id'], $mirrorAllowed);
            }
        }

        $this->auditLog("Mengubah realisasi anggaran: {$realisasi['uraian']}", 'realisasi_anggaran', $id, $realisasi, $updateData);
        return $this->success(null, 'Realisasi berhasil diperbarui.');
    }

    public function delete(int $kegiatanId, int $id): ResponseInterface
    {
        $this->requireRole(['direktur']);

        $realisasi = $this->model->find($id);
        if (! $realisasi || (int) $realisasi['kegiatan_id'] !== $kegiatanId) {
            return $this->fail('Realisasi tidak ditemukan.', 404);
        }

        // Hapus mirror entry
        if (! empty($realisasi['ref_realisasi_id'])) {
            $this->model->delete((int) $realisasi['ref_realisasi_id']);
        }

        $this->model->delete($id);
        $this->auditLog("Menghapus realisasi anggaran: {$realisasi['uraian']}", 'realisasi_anggaran', $id, $realisasi, null);
        return $this->success(null, 'Realisasi berhasil dihapus.');
    }

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * Ambil semua sumber off-budget beserta sisa alokasinya (alokasi - terpakai).
     */
    private function getSumberWithSisa(int $offBudgetId): array
    {
        return $this->model->db->query("
            SELECT
                obs.id,
                obs.sumber_id,
                obs.alokasi,
                obs.keterangan,
                ks.nama_kegiatan   AS sumber_nama,
                ks.pagu_anggaran   AS sumber_pagu,
                COALESCE(terpakai.total, 0)                    AS terpakai,
                (obs.alokasi - COALESCE(terpakai.total, 0))    AS sisa_alokasi
            FROM off_budget_sumber obs
            INNER JOIN kegiatan ks ON ks.id = obs.sumber_id
            LEFT JOIN (
                SELECT sumber_ob_id, SUM(nominal) AS total
                FROM realisasi_anggaran
                WHERE sumber_ob_id IS NOT NULL
                  AND ref_realisasi_id IS NOT NULL   -- hanya entry OB asli (bukan mirror)
                GROUP BY sumber_ob_id
            ) terpakai ON terpakai.sumber_ob_id = obs.id
            WHERE obs.off_budget_id = {$offBudgetId}
            ORDER BY ks.nama_kegiatan ASC
        ")->getResultArray();
    }

    private function cekNotifikasiSerapan(int $kegiatanId, array $kegiatan): void
    {
        $summary = $this->model->getSummary($kegiatanId);
        if ($summary['pagu_anggaran'] > 0 && $summary['persentase_serapan'] >= 80) {
            (new NotifikasiModel())->createForTeam(
                (int) $kegiatan['team_id'],
                'Serapan Anggaran ≥ 80%',
                "Realisasi kegiatan '{$kegiatan['nama_kegiatan']}' telah mencapai {$summary['persentase_serapan']}% dari pagu.",
                'anggaran'
            );
        }
    }
}
