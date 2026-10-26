<?php

namespace App\Models;

use CodeIgniter\Model;

class KegiatanModel extends Model
{
    protected $table         = 'kegiatan';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'nama_kegiatan', 'deskripsi', 'tanggal_mulai', 'tanggal_selesai',
        'pagu_anggaran', 'status', 'tipe', 'ro_id', 'team_id', 'created_by',
    ];
    protected $useTimestamps = true;

    // ── Subquery SQL untuk progress kegiatan yang memperhitungkan subtask ─────
    // Progress setiap task induk:
    //   - Jika punya subtask → AVG(pct subtask)  [selesai=100, berjalan=50, belum=0]
    //   - Jika tidak         → pct dari status task itu sendiri
    // Progress kegiatan = AVG(progress semua task induk)
    private const PROGRESS_SUBQUERY = "
        SELECT
            p.kegiatan_id,
            COUNT(p.id)        AS total_tasks,
            SUM(p.status='selesai')         AS tasks_selesai,
            SUM(p.status='sedang_berjalan') AS tasks_berjalan,
            COALESCE(AVG(
                CASE WHEN sub.cnt IS NULL OR sub.cnt = 0
                     THEN -- task tanpa subtask: pakai status task itu
                          CASE p.status
                              WHEN 'selesai'         THEN 100
                              WHEN 'sedang_berjalan' THEN 50
                              ELSE 0
                          END
                     ELSE -- task punya subtask: rata-rata persen subtask
                          (sub.selesai * 100 + sub.berjalan * 50) / sub.cnt
                END
            ), 0) AS progress
        FROM tasks p
        LEFT JOIN (
            SELECT
                parent_task_id,
                COUNT(*)                           AS cnt,
                SUM(status = 'selesai')            AS selesai,
                SUM(status = 'sedang_berjalan')    AS berjalan
            FROM tasks
            WHERE parent_task_id IS NOT NULL
            GROUP BY parent_task_id
        ) sub ON sub.parent_task_id = p.id
        WHERE p.parent_task_id IS NULL
        GROUP BY p.kegiatan_id
    ";

    public function getWithSummary(?int $userId = null, ?string $role = null): array
    {
        $builder = $this->db->table('kegiatan k')
            ->select('k.*, t.nama_tim, u.nama AS ketua_tim_nama,
                      COALESCE(r.total_realisasi, 0) AS total_realisasi,
                      COALESCE(tk.total_tasks, 0)    AS total_tasks,
                      COALESCE(tk.tasks_selesai, 0)  AS tasks_selesai,
                      COALESCE(tk.tasks_berjalan, 0) AS tasks_berjalan,
                      COALESCE(tk.progress, 0)       AS progress_raw')
            ->join('teams t', 't.id = k.team_id', 'left')
            ->join('users u', 'u.id = t.ketua_tim_id', 'left')
            ->join('(SELECT kegiatan_id, SUM(nominal) AS total_realisasi FROM realisasi_anggaran GROUP BY kegiatan_id) r', 'r.kegiatan_id = k.id', 'left')
            ->join('(' . self::PROGRESS_SUBQUERY . ') tk', 'tk.kegiatan_id = k.id', 'left')
            ->orderBy('k.created_at', 'DESC');

        if ($role === 'klien' && $userId) {
            $builder->join('(SELECT DISTINCT team_id FROM team_members WHERE user_id = ' . (int)$userId . ') tm_filter', 'tm_filter.team_id = k.team_id', 'inner');
        }

        $rows = $builder->get()->getResultArray();

        foreach ($rows as &$row) {
            $row['tipe']               = $row['tipe'] ?? 'on_budget';
            $row['tasks_berjalan']     = $row['tasks_berjalan'] ?? 0;
            $row['progress']           = round((float)($row['progress_raw'] ?? 0), 2);
            $row['sisa_anggaran']      = $row['pagu_anggaran'] - $row['total_realisasi'];
            $row['persentase_serapan'] = $row['pagu_anggaran'] > 0
                ? round(($row['total_realisasi'] / $row['pagu_anggaran']) * 100, 2) : 0;
            $row['warna_status']       = match(true) {
                $row['progress'] > 80  => 'hijau',
                $row['progress'] >= 50 => 'kuning',
                default                => 'merah',
            };
        }
        unset($row);

        return $rows;
    }

    public function getDetail(int $id): ?array
    {
        $row = $this->db->table('kegiatan k')
            ->select('k.*, t.nama_tim, u.nama AS ketua_tim_nama,
                      COALESCE(r.total_realisasi, 0) AS total_realisasi,
                      COALESCE(tk.total_tasks, 0)    AS total_tasks,
                      COALESCE(tk.tasks_selesai, 0)  AS tasks_selesai,
                      COALESCE(tk.tasks_berjalan, 0) AS tasks_berjalan,
                      COALESCE(tk.progress, 0)       AS progress_raw')
            ->join('teams t', 't.id = k.team_id', 'left')
            ->join('users u', 'u.id = t.ketua_tim_id', 'left')
            ->join('(SELECT kegiatan_id, SUM(nominal) AS total_realisasi FROM realisasi_anggaran GROUP BY kegiatan_id) r', 'r.kegiatan_id = k.id', 'left')
            ->join('(' . self::PROGRESS_SUBQUERY . ') tk', 'tk.kegiatan_id = k.id', 'left')
            ->where('k.id', $id)
            ->get()->getFirstRow('array');

        if (! $row) return null;

        $row['tipe']               = $row['tipe'] ?? 'on_budget';
        $row['tasks_berjalan']     = $row['tasks_berjalan'] ?? 0;
        $row['progress']           = round((float)($row['progress_raw'] ?? 0), 2);
        $row['sisa_anggaran']      = $row['pagu_anggaran'] - $row['total_realisasi'];
        $row['persentase_serapan'] = $row['pagu_anggaran'] > 0
            ? round(($row['total_realisasi'] / $row['pagu_anggaran']) * 100, 2) : 0;
        $row['warna_status']       = match(true) {
            $row['progress'] > 80  => 'hijau',
            $row['progress'] >= 50 => 'kuning',
            default                => 'merah',
        };

        return $row;
    }

    /**
     * Ambil semua kegiatan yang berstipe on_budget (untuk dropdown sumber).
     */
    public function getOnBudgetList(): array
    {
        return $this->select('id, nama_kegiatan, pagu_anggaran, status')
                    ->where('tipe', 'on_budget')
                    ->orderBy('nama_kegiatan')
                    ->findAll();
    }

    /**
     * Ambil kegiatan off-budget lengkap dengan jumlah sumber & total alokasi.
     */
    public function getOffBudgetList(): array
    {
        // Gunakan PROGRESS_SUBQUERY yang sama agar konsisten dengan getWithSummary/getDetail
        $progressSub = self::PROGRESS_SUBQUERY;
        $rows = $this->db->query("
            SELECT
                k.*,
                t.nama_tim,
                u.nama AS ketua_tim_nama,
                COALESCE(r_ob.total_realisasi, 0) AS total_realisasi,
                COALESCE(tk.total_tasks, 0)        AS total_tasks,
                COALESCE(tk.tasks_selesai, 0)      AS tasks_selesai,
                COALESCE(tk.tasks_berjalan, 0)     AS tasks_berjalan,
                COALESCE(tk.progress, 0)           AS progress_raw,
                COALESCE(obs.jumlah_sumber, 0)     AS jumlah_sumber,
                COALESCE(obs.total_alokasi, 0)     AS total_alokasi
            FROM kegiatan k
            LEFT JOIN teams t ON t.id = k.team_id
            LEFT JOIN users u ON u.id = t.ketua_tim_id
            LEFT JOIN (
                SELECT kegiatan_id, SUM(nominal) AS total_realisasi
                FROM realisasi_anggaran GROUP BY kegiatan_id
            ) r_ob ON r_ob.kegiatan_id = k.id
            LEFT JOIN ({$progressSub}) tk ON tk.kegiatan_id = k.id
            LEFT JOIN (
                SELECT off_budget_id, COUNT(*) AS jumlah_sumber, SUM(alokasi) AS total_alokasi
                FROM off_budget_sumber GROUP BY off_budget_id
            ) obs ON obs.off_budget_id = k.id
            WHERE k.tipe = 'off_budget'
            ORDER BY k.created_at DESC
        ")->getResultArray();

        foreach ($rows as &$row) {
            $row['tasks_berjalan']     = $row['tasks_berjalan'] ?? 0;
            $row['progress']           = round((float)($row['progress_raw'] ?? 0), 2);
            $row['sisa_anggaran']      = $row['pagu_anggaran'] - $row['total_realisasi'];
            $row['persentase_serapan'] = $row['pagu_anggaran'] > 0
                ? round(($row['total_realisasi'] / $row['pagu_anggaran']) * 100, 2) : 0;
            $row['warna_status']       = match(true) {
                $row['progress'] > 80  => 'hijau',
                $row['progress'] >= 50 => 'kuning',
                default                => 'merah',
            };
        }
        unset($row);

        return $rows;
    }
}
