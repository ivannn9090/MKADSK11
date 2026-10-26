<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

class DashboardController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $db = \Config\Database::connect();

        // --- Kegiatan Summary ---
        $kegiatanStats = $db->query("
            SELECT COUNT(*) AS total,
                   SUM(status='berjalan')  AS berjalan,
                   SUM(status='selesai')   AS selesai,
                   SUM(status='terlambat') AS terlambat,
                   SUM(status='draft')     AS draft
            FROM kegiatan
        ")->getFirstRow('array');

        // --- Anggaran Summary ---
        $anggaranStats = $db->query("
            SELECT COALESCE(SUM(k.pagu_anggaran), 0) AS total_pagu,
                   COALESCE(SUM(r.total_realisasi), 0) AS total_realisasi
            FROM kegiatan k
            LEFT JOIN (
                SELECT kegiatan_id, SUM(nominal) AS total_realisasi
                FROM realisasi_anggaran GROUP BY kegiatan_id
            ) r ON r.kegiatan_id = k.id
        ")->getFirstRow('array');

        $anggaranStats['sisa_anggaran']      = $anggaranStats['total_pagu'] - $anggaranStats['total_realisasi'];
        $anggaranStats['persentase_serapan'] = $anggaranStats['total_pagu'] > 0
            ? round(($anggaranStats['total_realisasi'] / $anggaranStats['total_pagu']) * 100, 2) : 0;

        // --- Progress Per Kegiatan ---
        $progressList = $db->query("
            SELECT k.id, k.nama_kegiatan, k.status, k.tanggal_selesai,
                   k.pagu_anggaran, k.tipe, t.nama_tim, u.nama AS ketua_tim_nama,
                   COUNT(tk.id)                        AS total_tasks,
                   SUM(tk.status='selesai')             AS tasks_selesai,
                   SUM(tk.status='sedang_berjalan')     AS tasks_berjalan,
                   COALESCE(r.total_realisasi, 0) AS total_realisasi
            FROM kegiatan k
            LEFT JOIN teams t ON t.id = k.team_id
            LEFT JOIN users u ON u.id = t.ketua_tim_id
            LEFT JOIN tasks tk ON tk.kegiatan_id = k.id AND tk.parent_task_id IS NULL
            LEFT JOIN (
                SELECT kegiatan_id, SUM(nominal) AS total_realisasi
                FROM realisasi_anggaran
                GROUP BY kegiatan_id
            ) r ON r.kegiatan_id = k.id
            GROUP BY k.id, k.nama_kegiatan, k.status, k.tanggal_selesai,
                     k.pagu_anggaran, k.tipe, t.nama_tim, u.nama
            ORDER BY k.tanggal_selesai ASC
        ")->getResultArray();

        foreach ($progressList as &$row) {
            $row['tasks_berjalan']     = $row['tasks_berjalan'] ?? 0;
            $row['progress']           = $row['total_tasks'] > 0
                ? round((($row['tasks_selesai'] + $row['tasks_berjalan'] * 0.5) / $row['total_tasks']) * 100, 2) : 0;
            $row['sisa_anggaran']      = $row['pagu_anggaran'] - $row['total_realisasi'];
            $row['persentase_serapan'] = $row['pagu_anggaran'] > 0
                ? round(($row['total_realisasi'] / $row['pagu_anggaran']) * 100, 2) : 0;
            $row['warna_status']       = match(true) {
                $row['progress'] > 80  => 'hijau',
                $row['progress'] >= 50 => 'kuning',
                default                => 'merah',
            };
        }

        // --- Serapan Anggaran Bulanan (12 bulan terakhir) ---
        $serapanBulanan = $db->query("
            SELECT DATE_FORMAT(tanggal, '%Y-%m') AS bulan,
                   SUM(nominal) AS total
            FROM realisasi_anggaran
            WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY bulan
            ORDER BY bulan ASC
        ")->getResultArray();

        // --- Ranking Tim ---
        $rankingTim = $db->query("
            SELECT t.nama_tim,
                   COUNT(DISTINCT k.id) AS total_kegiatan,
                   ROUND(AVG(
                       CASE WHEN COALESCE(tk_total.total,0) > 0
                            THEN ((tk_done.selesai + COALESCE(tk_done.berjalan,0) * 0.5) / tk_total.total) * 100
                            ELSE 0 END
                   ), 2) AS avg_progress
            FROM teams t
            LEFT JOIN kegiatan k ON k.team_id = t.id
            LEFT JOIN (SELECT kegiatan_id, COUNT(*) AS total FROM tasks WHERE parent_task_id IS NULL GROUP BY kegiatan_id) tk_total ON tk_total.kegiatan_id = k.id
            LEFT JOIN (SELECT kegiatan_id, SUM(status='selesai') AS selesai, SUM(status='sedang_berjalan') AS berjalan FROM tasks WHERE parent_task_id IS NULL GROUP BY kegiatan_id) tk_done ON tk_done.kegiatan_id = k.id
            GROUP BY t.id
            ORDER BY avg_progress DESC
        ")->getResultArray();

        // --- RPD Dashboard Tables (tahun berjalan) ---
        $tahun = (int) date('Y');
        // Cek apakah ada kegiatan di tahun berjalan
        $hasKegiatan = $db->query("
            SELECT COUNT(*) AS c FROM kegiatan
            WHERE YEAR(tanggal_mulai) = {$tahun} OR YEAR(tanggal_selesai) = {$tahun}
        ")->getFirstRow('array')['c'] ?? 0;
        $rpdData = null;
        if ($hasKegiatan > 0) {
            $rpdData = [
                'tahun'           => $tahun,
                'tabel_realisasi' => $this->buildTabelRealisasi($db, $tahun),
                'tabel_rpd'       => $this->buildTabelRpd($db, $tahun),
            ];
        }

        return $this->success([
            'kegiatan'        => $kegiatanStats,
            'anggaran'        => $anggaranStats,
            'progress_list'   => $progressList,
            'serapan_bulanan' => $serapanBulanan,
            'ranking_tim'     => $rankingTim,
            'rpd_dashboard'   => $rpdData,
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers: Tabel Realisasi & RPD per bulan (berbasis Kegiatan)
    // -----------------------------------------------------------------------
    private function buildTabelRealisasi($db, int $tahun): array
    {
        $bulanSelect = '';
        for ($m = 1; $m <= 12; $m++) {
            $pad   = str_pad($m, 2, '0', STR_PAD_LEFT);
            $lastD = date('t', mktime(0, 0, 0, $m, 1, $tahun));
            $bulanSelect .= "COALESCE(SUM(CASE WHEN ra.tanggal BETWEEN '{$tahun}-{$pad}-01' AND '{$tahun}-{$pad}-{$lastD}' THEN ra.nominal ELSE 0 END),0) AS real_{$pad},\n                ";
        }

        $rows = $db->query("
            SELECT
                k.id AS kegiatan_id, k.nama_kegiatan, k.pagu_anggaran, k.status,
                {$bulanSelect}
                COALESCE(SUM(ra.nominal), 0) AS real_total
            FROM kegiatan k
            LEFT JOIN realisasi_anggaran ra ON ra.kegiatan_id = k.id AND YEAR(ra.tanggal) = {$tahun}
            WHERE YEAR(k.tanggal_mulai) = {$tahun} OR YEAR(k.tanggal_selesai) = {$tahun}
            GROUP BY k.id, k.nama_kegiatan, k.pagu_anggaran, k.status
            ORDER BY k.nama_kegiatan ASC
        ")->getResultArray();

        $total = ['pagu_anggaran' => 0.0, 'real_total' => 0.0];
        for ($m = 1; $m <= 12; $m++) {
            $total['real_' . str_pad($m, 2, '0', STR_PAD_LEFT)] = 0.0;
        }
        foreach ($rows as &$row) {
            $row['pagu_anggaran'] = (float)$row['pagu_anggaran'];
            $row['real_total']    = (float)$row['real_total'];
            $total['pagu_anggaran'] += $row['pagu_anggaran'];
            $total['real_total']    += $row['real_total'];
            for ($m = 1; $m <= 12; $m++) {
                $pad = str_pad($m, 2, '0', STR_PAD_LEFT);
                $row["real_{$pad}"] = (float)($row["real_{$pad}"] ?? 0);
                $total["real_{$pad}"] += $row["real_{$pad}"];
            }
            $row['pct_serapan']   = $row['pagu_anggaran'] > 0
                ? round(($row['real_total'] / $row['pagu_anggaran']) * 100, 2) : 0;
            $row['sisa_anggaran'] = $row['pagu_anggaran'] - $row['real_total'];
        }
        unset($row);
        $total['pct_serapan']   = $total['pagu_anggaran'] > 0
            ? round(($total['real_total'] / $total['pagu_anggaran']) * 100, 2) : 0;
        $total['sisa_anggaran'] = $total['pagu_anggaran'] - $total['real_total'];

        return ['rows' => $rows, 'total' => $total];
    }

    private function buildTabelRpd($db, int $tahun): array
    {
        $rpdSel = '';
        $ralSel = '';
        for ($m = 1; $m <= 12; $m++) {
            $pad   = str_pad($m, 2, '0', STR_PAD_LEFT);
            $lastD = date('t', mktime(0, 0, 0, $m, 1, $tahun));
            $rpdSel .= "COALESCE(rpd.bln_{$pad}, 0) AS bln_{$pad},\n                ";
            $ralSel .= "COALESCE(SUM(CASE WHEN ra.tanggal BETWEEN '{$tahun}-{$pad}-01' AND '{$tahun}-{$pad}-{$lastD}' THEN ra.nominal ELSE 0 END),0) AS real_{$pad},\n                ";
        }
        $groupBln = implode(',', array_map(fn($m) => 'rpd.bln_' . str_pad($m, 2, '0', STR_PAD_LEFT), range(1, 12)));

        $rows = $db->query("
            SELECT
                k.id AS kegiatan_id, k.nama_kegiatan, k.pagu_anggaran, k.status,
                rpd.id AS rpd_id, rpd.keterangan,
                {$rpdSel}
                {$ralSel}
                COALESCE(SUM(ra.nominal), 0) AS real_total
            FROM kegiatan k
            LEFT JOIN rencana_penarikan_dana rpd ON rpd.kegiatan_id = k.id AND rpd.tahun_anggaran = {$tahun}
            LEFT JOIN realisasi_anggaran ra ON ra.kegiatan_id = k.id AND YEAR(ra.tanggal) = {$tahun}
            WHERE YEAR(k.tanggal_mulai) = {$tahun} OR YEAR(k.tanggal_selesai) = {$tahun}
            GROUP BY k.id, k.nama_kegiatan, k.pagu_anggaran, k.status, rpd.id, rpd.keterangan, {$groupBln}
            ORDER BY k.nama_kegiatan ASC
        ")->getResultArray();

        $total = ['rpd_total' => 0.0, 'real_total' => 0.0];
        for ($m = 1; $m <= 12; $m++) {
            $pad = str_pad($m, 2, '0', STR_PAD_LEFT);
            $total["bln_{$pad}"]  = 0.0;
            $total["real_{$pad}"] = 0.0;
        }
        foreach ($rows as &$row) {
            $rpdSum = 0.0;
            for ($m = 1; $m <= 12; $m++) {
                $pad = str_pad($m, 2, '0', STR_PAD_LEFT);
                $row["bln_{$pad}"]  = (float)($row["bln_{$pad}"] ?? 0);
                $row["real_{$pad}"] = (float)($row["real_{$pad}"] ?? 0);
                $rpdSum += $row["bln_{$pad}"];
                $total["bln_{$pad}"]  += $row["bln_{$pad}"];
                $total["real_{$pad}"] += $row["real_{$pad}"];
            }
            $row['real_total']      = (float)($row['real_total'] ?? 0);
            $row['rpd_total']       = $rpdSum;
            $row['pct_real_vs_rpd'] = $rpdSum > 0
                ? round(($row['real_total'] / $rpdSum) * 100, 2) : 0;
            $row['selisih']         = $rpdSum - $row['real_total'];
            $total['rpd_total']    += $rpdSum;
            $total['real_total']   += $row['real_total'];
        }
        unset($row);
        $total['pct_real_vs_rpd'] = $total['rpd_total'] > 0
            ? round(($total['real_total'] / $total['rpd_total']) * 100, 2) : 0;
        $total['selisih'] = $total['rpd_total'] - $total['real_total'];

        return ['rows' => $rows, 'total' => $total];
    }
}
