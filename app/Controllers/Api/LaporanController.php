<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

class LaporanController extends BaseApiController
{
    public function progress(): ResponseInterface
    {
        $db    = \Config\Database::connect();
        $where = '';
        $bind  = [];

        // Filter per-tim hanya berlaku untuk role 'klien'
        if ($this->userRole() === 'klien') {
            $where = 'JOIN team_members tm2 ON tm2.team_id = k.team_id AND tm2.user_id = ' . $this->userId();
        }

        $rows = $db->query("
            SELECT k.id, k.nama_kegiatan, k.status, k.tanggal_mulai, k.tanggal_selesai,
                   t.nama_tim, u.nama AS ketua_tim_nama,
                   COUNT(tk.id) AS total_tasks,
                   SUM(tk.status='selesai') AS tasks_selesai,
                   SUM(tk.status='sedang_berjalan') AS tasks_berjalan,
                   SUM(tk.status='belum_dimulai') AS tasks_belum
            FROM kegiatan k
            LEFT JOIN teams t ON t.id = k.team_id
            LEFT JOIN users u ON u.id = t.ketua_tim_id
            LEFT JOIN tasks tk ON tk.kegiatan_id = k.id
            {$where}
            GROUP BY k.id
            ORDER BY k.nama_kegiatan
        ")->getResultArray();

        foreach ($rows as &$row) {
            $row['tasks_berjalan'] = $row['tasks_berjalan'] ?? 0;
            $row['progress']       = $row['total_tasks'] > 0
                ? round((($row['tasks_selesai'] + $row['tasks_berjalan'] * 0.5) / $row['total_tasks']) * 100, 2) : 0;
        }

        return $this->success(['rows' => $rows]);
    }

    public function anggaran(): ResponseInterface
    {
        $db    = \Config\Database::connect();
        $where = '';

        // Filter per-tim hanya berlaku untuk role 'klien'
        if ($this->userRole() === 'klien') {
            $where = 'JOIN team_members tm2 ON tm2.team_id = k.team_id AND tm2.user_id = ' . $this->userId();
        }

        $rows = $db->query("
            SELECT k.id, k.nama_kegiatan, k.pagu_anggaran, k.status,
                   t.nama_tim, u.nama AS ketua_tim_nama,
                   k.tanggal_mulai, k.tanggal_selesai,
                   COALESCE(SUM(r.nominal), 0) AS total_realisasi
            FROM kegiatan k
            LEFT JOIN teams t ON t.id = k.team_id
            LEFT JOIN users u ON u.id = t.ketua_tim_id
            LEFT JOIN realisasi_anggaran r ON r.kegiatan_id = k.id
            {$where}
            GROUP BY k.id
            ORDER BY k.nama_kegiatan
        ")->getResultArray();

        foreach ($rows as &$row) {
            $row['sisa_anggaran']      = $row['pagu_anggaran'] - $row['total_realisasi'];
            $row['persentase_serapan'] = $row['pagu_anggaran'] > 0
                ? round(($row['total_realisasi'] / $row['pagu_anggaran']) * 100, 2) : 0;
        }

        $totals = [
            'total_pagu'              => array_sum(array_column($rows, 'pagu_anggaran')),
            'total_realisasi'         => array_sum(array_column($rows, 'total_realisasi')),
            'total_sisa'              => array_sum(array_column($rows, 'sisa_anggaran')),
            'persentase_serapan'      => 0,
        ];
        if ($totals['total_pagu'] > 0) {
            $totals['persentase_serapan'] = round(($totals['total_realisasi'] / $totals['total_pagu']) * 100, 2);
        }

        return $this->success(['rows' => $rows, 'totals' => $totals]);
    }
}
