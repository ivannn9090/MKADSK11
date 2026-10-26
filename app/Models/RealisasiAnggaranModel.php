<?php

namespace App\Models;

use CodeIgniter\Model;

class RealisasiAnggaranModel extends Model
{
    protected $table         = 'realisasi_anggaran';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'kegiatan_id', 'tanggal', 'uraian', 'nominal', 'bukti_transaksi',
        'created_by', 'sumber_ob_id', 'ref_realisasi_id',
        'rincian_anggaran_id', 'volume_dipakai',
    ];
    protected $useTimestamps = true;

    public function getByKegiatan(int $kegiatanId): array
    {
        return $this->db->query("
            SELECT ra.*,
                   u.nama                  AS created_by_nama,
                   obs.sumber_id           AS sumber_id_on_budget,
                   ks_ob.nama_kegiatan     AS sumber_nama_kegiatan,
                   obs.alokasi             AS sumber_alokasi,
                   k_mirror.nama_kegiatan  AS mirror_dari_kegiatan,
                   rian.komponen           AS komponen_nama,
                   rian.satuan_vol         AS satuan_vol
            FROM realisasi_anggaran ra
            LEFT JOIN users u                ON u.id   = ra.created_by
            LEFT JOIN off_budget_sumber obs  ON obs.id  = ra.sumber_ob_id
            LEFT JOIN kegiatan ks_ob         ON ks_ob.id = obs.sumber_id
            -- untuk mirror entry: JOIN ke kegiatan off-budget yang menghasilkan mirror ini
            LEFT JOIN realisasi_anggaran ra2 ON ra2.id = ra.ref_realisasi_id
                                             AND ra.sumber_ob_id IS NULL
            LEFT JOIN kegiatan k_mirror      ON k_mirror.id = ra2.kegiatan_id
            -- komponen anggaran on-budget (jika ada)
            LEFT JOIN rincian_anggaran rian  ON rian.id = ra.rincian_anggaran_id
            WHERE ra.kegiatan_id = {$kegiatanId}
            ORDER BY ra.tanggal DESC
        ")->getResultArray();
    }

    public function getSummary(int $kegiatanId): array
    {
        $row = $this->db->table('kegiatan k')
            ->select('k.pagu_anggaran, COALESCE(SUM(r.nominal),0) AS total_realisasi')
            ->join('realisasi_anggaran r', 'r.kegiatan_id = k.id', 'left')
            ->where('k.id', $kegiatanId)
            ->groupBy('k.id')
            ->get()->getFirstRow('array');

        if (! $row) return [];

        $row['sisa_anggaran']      = $row['pagu_anggaran'] - $row['total_realisasi'];
        $row['persentase_serapan'] = $row['pagu_anggaran'] > 0
            ? round(($row['total_realisasi'] / $row['pagu_anggaran']) * 100, 2) : 0;

        return $row;
    }
}
