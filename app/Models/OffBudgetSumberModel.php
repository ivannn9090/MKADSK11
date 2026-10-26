<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * OffBudgetSumberModel — tabel pivot off_budget_sumber.
 *
 * Setiap baris = satu kegiatan on-budget yang menjadi sumber dana
 * bagi satu kegiatan off-budget.
 *
 * Relasi: off_budget_id (N) ↔ (M) sumber_id
 */
class OffBudgetSumberModel extends Model
{
    protected $table         = 'off_budget_sumber';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['off_budget_id', 'sumber_id', 'rincian_anggaran_id', 'alokasi', 'keterangan', 'created_by'];
    protected $useTimestamps = true;

    // ---------------------------------------------------------------
    // Ambil semua sumber untuk satu kegiatan off-budget,
    // lengkap dengan info kegiatan sumber & pemakaian anggaran sumber.
    // ---------------------------------------------------------------
    public function getSumberByOffBudget(int $offBudgetId): array
    {
        return $this->db->query("
            SELECT
                obs.*,
                ks.nama_kegiatan   AS sumber_nama,
                ks.pagu_anggaran   AS sumber_pagu,
                ks.status          AS sumber_status,
                u.nama             AS created_by_nama,
                ra.komponen        AS rincian_komponen,
                ra.subtotal        AS rincian_subtotal,
                ra.satuan_qty      AS rincian_satuan_qty,
                ra.satuan_vol      AS rincian_satuan_vol,
                COALESCE(r_src.total_realisasi_sumber, 0)  AS sumber_total_realisasi,
                COALESCE(r_ob.total_alokasi_ob, 0)         AS sumber_total_alokasi_ob,
                -- Nominal realisasi OB yang sudah terpakai dari sumber (obs.id) ini
                COALESCE(r_terpakai.terpakai, 0)           AS terpakai
            FROM off_budget_sumber obs
            INNER JOIN kegiatan ks        ON ks.id  = obs.sumber_id
            LEFT JOIN users u             ON u.id   = obs.created_by
            LEFT JOIN rincian_anggaran ra ON ra.id  = obs.rincian_anggaran_id
            LEFT JOIN (
                SELECT kegiatan_id, SUM(nominal) AS total_realisasi_sumber
                FROM realisasi_anggaran
                GROUP BY kegiatan_id
            ) r_src ON r_src.kegiatan_id = obs.sumber_id
            LEFT JOIN (
                SELECT sumber_id, SUM(alokasi) AS total_alokasi_ob
                FROM off_budget_sumber
                GROUP BY sumber_id
            ) r_ob ON r_ob.sumber_id = obs.sumber_id
            -- Berapa nominal realisasi OB yang sudah memakai sumber ini (per obs.id)
            LEFT JOIN (
                SELECT sumber_ob_id, SUM(nominal) AS terpakai
                FROM realisasi_anggaran
                WHERE sumber_ob_id IS NOT NULL
                  AND ref_realisasi_id IS NOT NULL
                GROUP BY sumber_ob_id
            ) r_terpakai ON r_terpakai.sumber_ob_id = obs.id
            WHERE obs.off_budget_id = {$offBudgetId}
            ORDER BY ks.nama_kegiatan ASC, ra.urutan ASC
        ")->getResultArray();
    }

    // ---------------------------------------------------------------
    // Ambil semua off-budget yang menggunakan sumber tertentu
    // ---------------------------------------------------------------
    public function getOffBudgetBySumber(int $sumberId): array
    {
        return $this->db->query("
            SELECT
                obs.*,
                k.nama_kegiatan  AS off_budget_nama,
                k.status         AS off_budget_status,
                COALESCE(r.total_realisasi, 0) AS total_realisasi
            FROM off_budget_sumber obs
            INNER JOIN kegiatan k ON k.id = obs.off_budget_id
            LEFT JOIN (
                SELECT kegiatan_id, SUM(nominal) AS total_realisasi
                FROM realisasi_anggaran GROUP BY kegiatan_id
            ) r ON r.kegiatan_id = obs.off_budget_id
            WHERE obs.sumber_id = {$sumberId}
            ORDER BY k.nama_kegiatan ASC
        ")->getResultArray();
    }

    // ---------------------------------------------------------------
    // Ringkasan satu sumber: pagu, total alokasi OB, sisa, realisasi OB
    // ---------------------------------------------------------------
    public function getSumberRingkasan(int $sumberId): array
    {
        $sumber = $this->db->query("
            SELECT ks.id, ks.nama_kegiatan, ks.pagu_anggaran, ks.status,
                   COALESCE(r.total_realisasi, 0) AS total_realisasi_on
            FROM kegiatan ks
            LEFT JOIN (SELECT kegiatan_id, SUM(nominal) AS total_realisasi
                       FROM realisasi_anggaran GROUP BY kegiatan_id) r
                   ON r.kegiatan_id = ks.id
            WHERE ks.id = {$sumberId}
        ")->getFirstRow('array');

        if (! $sumber) return [];

        $obList = $this->getOffBudgetBySumber($sumberId);
        $totalAlokasi     = array_sum(array_column($obList, 'alokasi'));
        $totalRealisasiOB = array_sum(array_column($obList, 'total_realisasi'));

        return [
            'sumber'              => $sumber,
            'off_budget_list'     => $obList,
            'total_alokasi_ob'    => $totalAlokasi,
            'total_realisasi_ob'  => $totalRealisasiOB,
            'sisa_anggaran'       => $sumber['pagu_anggaran'] - $sumber['total_realisasi_on'] - $totalAlokasi,
        ];
    }

    // ---------------------------------------------------------------
    // Ringkasan semua sumber untuk satu off-budget
    // ---------------------------------------------------------------
    public function getRingkasanForOffBudget(int $offBudgetId): array
    {
        $sumbers = $this->getSumberByOffBudget($offBudgetId);
        $totalAlokasi = array_sum(array_column($sumbers, 'alokasi'));

        return [
            'sumber_list'   => $sumbers,
            'total_alokasi' => $totalAlokasi,
        ];
    }

    // ---------------------------------------------------------------
    // Cek apakah (off_budget_id, sumber_id) sudah ada
    // ---------------------------------------------------------------
    /**
     * Cek apakah kombinasi (off_budget_id, sumber_id, rincian_anggaran_id) sudah ada.
     * Jika rincianId null, cek hanya berdasarkan kegiatan sumber saja.
     */
    public function getExisting(int $offBudgetId, int $sumberId, ?int $rincianId = null): ?array
    {
        $q = $this->where('off_budget_id', $offBudgetId)
                  ->where('sumber_id', $sumberId);

        if ($rincianId !== null) {
            $q = $q->where('rincian_anggaran_id', $rincianId);
        } else {
            $q = $q->where('rincian_anggaran_id IS NULL', null, false);
        }

        return $q->first();
    }
}
