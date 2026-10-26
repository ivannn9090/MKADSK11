<?php

namespace App\Models;

use CodeIgniter\Model;

class RoModel extends Model
{
    protected $table         = 'ro';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nama_ro', 'kode_ro', 'deskripsi', 'tahun', 'team_id', 'created_by'];
    protected $useTimestamps = true;

    /**
     * Ambil semua RO beserta statistik rollup dari child kegiatan:
     * - total_pagu         : SUM pagu_anggaran anak
     * - total_realisasi    : SUM realisasi anak
     * - persentase_realisasi
     * - progress           : rata-rata progress anak (weighted avg tasks)
     * - jumlah_kegiatan    : COUNT anak
     */
    public function getWithStats(): array
    {
        $rows = $this->db->query("
            SELECT
                ro.*,
                t.nama_tim,
                COALESCE(stats.jumlah_kegiatan,    0)    AS jumlah_kegiatan,
                COALESCE(stats.total_pagu,         0)    AS total_pagu,
                COALESCE(stats.total_realisasi,    0)    AS total_realisasi,
                COALESCE(stats.sum_progress_num,   0)    AS sum_progress_num,
                COALESCE(stats.sum_progress_den,   0)    AS sum_progress_den
            FROM ro
            LEFT JOIN teams t ON t.id = ro.team_id
            LEFT JOIN (
                SELECT
                    k.ro_id,
                    COUNT(k.id)                                          AS jumlah_kegiatan,
                    SUM(k.pagu_anggaran)                                 AS total_pagu,
                    COALESCE(SUM(ra.total_realisasi), 0)                 AS total_realisasi,
                    SUM(COALESCE(tk.tasks_selesai, 0)
                        + COALESCE(tk.tasks_berjalan, 0) * 0.5)         AS sum_progress_num,
                    SUM(COALESCE(tk.total_tasks, 0))                    AS sum_progress_den
                FROM kegiatan k
                LEFT JOIN (
                    SELECT kegiatan_id, SUM(nominal) AS total_realisasi
                    FROM realisasi_anggaran GROUP BY kegiatan_id
                ) ra ON ra.kegiatan_id = k.id
                LEFT JOIN (
                    SELECT kegiatan_id,
                           COUNT(*)                      AS total_tasks,
                           SUM(status='selesai')         AS tasks_selesai,
                           SUM(status='sedang_berjalan') AS tasks_berjalan
                    FROM tasks GROUP BY kegiatan_id
                ) tk ON tk.kegiatan_id = k.id
                WHERE k.ro_id IS NOT NULL AND k.tipe = 'on_budget'
                GROUP BY k.ro_id
            ) stats ON stats.ro_id = ro.id
            ORDER BY ro.created_at DESC
        ")->getResultArray();

        foreach ($rows as &$row) {
            $pagu  = (float) $row['total_pagu'];
            $real  = (float) $row['total_realisasi'];
            $num   = (float) $row['sum_progress_num'];
            $den   = (float) $row['sum_progress_den'];

            $row['persentase_realisasi'] = $pagu > 0 ? round($real / $pagu * 100, 2) : 0;
            $row['progress']             = $den  > 0 ? round($num  / $den  * 100, 2) : 0;

            unset($row['sum_progress_num'], $row['sum_progress_den']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Ambil detail satu RO beserta daftar child kegiatan lengkap (dengan summary).
     */
    public function getDetail(int $id): ?array
    {
        $rows = $this->getWithStats();
        $ro   = null;
        foreach ($rows as $r) {
            if ((int) $r['id'] === $id) { $ro = $r; break; }
        }
        if (! $ro) return null;

        // Ambil child kegiatan dengan summary (gunakan KegiatanModel)
        $kegiatanModel = new KegiatanModel();
        $all = $kegiatanModel->getWithSummary();
        $ro['kegiatan'] = array_values(array_filter($all, fn($k) => (int)($k['ro_id'] ?? 0) === $id));

        return $ro;
    }
}
