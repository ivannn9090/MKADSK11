<?php

namespace App\Models;

use CodeIgniter\Model;

class RincianAnggaranModel extends Model
{
    protected $table         = 'rincian_anggaran';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'kegiatan_id', 'urutan', 'komponen', 'uraian',
        'qty', 'satuan_qty', 'volume', 'satuan_vol',
        'harga_satuan', 'subtotal', 'created_by',
    ];
    protected $useTimestamps = true;

    /** Ambil semua rincian milik satu kegiatan, urutkan by urutan. */
    public function getByKegiatan(int $kegiatanId): array
    {
        return $this->select('rincian_anggaran.*, u.nama AS created_by_nama')
                    ->join('users u', 'u.id = rincian_anggaran.created_by', 'left')
                    ->where('kegiatan_id', $kegiatanId)
                    ->orderBy('urutan', 'ASC')
                    ->orderBy('id', 'ASC')
                    ->findAll();
    }

    /** Hitung total subtotal semua rincian untuk satu kegiatan. */
    public function getTotalByKegiatan(int $kegiatanId): float
    {
        $row = $this->selectSum('subtotal', 'total')
                    ->where('kegiatan_id', $kegiatanId)
                    ->first();
        return (float) ($row['total'] ?? 0);
    }

    /**
     * Hitung subtotal dari qty * volume * harga_satuan.
     * Disimpan agar query summary cukup SUM(subtotal).
     */
    public static function hitungSubtotal(float $qty, float $volume, float $harga): float
    {
        return round($qty * $volume * $harga, 2);
    }

    /** Urutan berikutnya dalam satu kegiatan. */
    public function getNextUrutan(int $kegiatanId): int
    {
        $row = $this->selectMax('urutan', 'max_urutan')
                    ->where('kegiatan_id', $kegiatanId)
                    ->first();
        return (int) ($row['max_urutan'] ?? 0) + 1;
    }

    /**
     * Ambil semua rincian beserta sisa anggaran (nominal) yang belum terpakai.
     *
     * Sisa dihitung dari:
     *   nominal_sisa = subtotal - SUM(nominal realisasi yang terkait ke rincian ini)
     *
     * Batas maksimal per komponen = subtotal (qty * volume * harga_satuan).
     * Realisasi boleh kurang dari subtotal, tapi TIDAK boleh melebihi subtotal.
     *
     * Kolom yang dikembalikan:
     *   id, komponen, uraian, qty, satuan_qty, volume, satuan_vol,
     *   harga_satuan, subtotal, urutan,
     *   nominal_terpakai  = sudah direalisasikan
     *   nominal_sisa      = subtotal - terpakai
     */
    public function getSisaByKegiatan(int $kegiatanId): array
    {
        return $this->db->query("
            SELECT
                ra.id,
                ra.komponen,
                ra.uraian,
                ra.qty,
                ra.satuan_qty,
                ra.volume,
                ra.satuan_vol,
                ra.harga_satuan,
                ra.subtotal,
                ra.urutan,
                -- Realisasi on-budget yang sudah terpakai dari komponen ini
                COALESCE(SUM(rl.nominal), 0)              AS nominal_terpakai,
                -- Alokasi OB yang dikunci tepat pada komponen ini (exact via rincian_anggaran_id)
                -- Jika ada OB yang belum pilih komponen (rincian_anggaran_id NULL), diabaikan di sini
                COALESCE(ob_exact.alokasi_ob_komponen, 0) AS alokasi_ob_porsi,
                -- Sisa efektif = subtotal - terpakai_on - alokasi_ob_komponen
                ra.subtotal
                    - COALESCE(SUM(rl.nominal), 0)
                    - COALESCE(ob_exact.alokasi_ob_komponen, 0) AS nominal_sisa
            FROM rincian_anggaran ra
            -- Realisasi on-budget yang terkait ke komponen ini
            LEFT JOIN realisasi_anggaran rl
                   ON rl.rincian_anggaran_id = ra.id
            -- Alokasi OB yang dikunci tepat ke komponen ini
            LEFT JOIN (
                SELECT rincian_anggaran_id, SUM(alokasi) AS alokasi_ob_komponen
                FROM off_budget_sumber
                WHERE rincian_anggaran_id IS NOT NULL
                GROUP BY rincian_anggaran_id
            ) ob_exact ON ob_exact.rincian_anggaran_id = ra.id
            WHERE ra.kegiatan_id = {$kegiatanId}
            GROUP BY ra.id, ob_exact.alokasi_ob_komponen
            ORDER BY ra.urutan ASC, ra.id ASC
        ")->getResultArray();
    }
}
