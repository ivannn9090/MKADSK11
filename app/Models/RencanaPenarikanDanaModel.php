<?php

namespace App\Models;

use CodeIgniter\Model;

class RencanaPenarikanDanaModel extends Model
{
    protected $table         = 'rencana_penarikan_dana';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'kegiatan_id', 'tahun_anggaran', 'pagu_anggaran',
        'bln_01','bln_02','bln_03','bln_04','bln_05','bln_06',
        'bln_07','bln_08','bln_09','bln_10','bln_11','bln_12',
        'keterangan', 'created_by',
    ];
    protected $useTimestamps = true;

    /** Nama bulan Indonesia (indeks 1-12) */
    public static array $namaBulan = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
        5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
        9=>'September',10=>'Oktober',11=>'November',12=>'Desember',
    ];

    public function getByKegiatanTahun(int $kegiatanId, int $tahun): ?array
    {
        return $this->where('kegiatan_id', $kegiatanId)
                    ->where('tahun_anggaran', $tahun)
                    ->first();
    }

    /**
     * Tabel Realisasi: setiap baris = satu kegiatan.
     * Pagu dari kegiatan.pagu_anggaran.
     * Realisasi aktual dihitung per bulan dari realisasi_anggaran.tanggal.
     */
    public function getTabelRealisasi(int $tahun): array
    {
        $db = \Config\Database::connect();

        // Bangun kolom SELECT per bulan
        $bulanSelect = '';
        for ($m = 1; $m <= 12; $m++) {
            $pad   = str_pad($m, 2, '0', STR_PAD_LEFT);
            $next  = str_pad($m + 1, 2, '0', STR_PAD_LEFT);
            $lastD = date('t', mktime(0, 0, 0, $m, 1, $tahun));
            $bulanSelect .= "COALESCE(SUM(CASE WHEN ra.tanggal BETWEEN '{$tahun}-{$pad}-01' AND '{$tahun}-{$pad}-{$lastD}' THEN ra.nominal ELSE 0 END),0) AS real_{$pad},\n                ";
        }

        $rows = $db->query("
            SELECT
                k.id AS kegiatan_id,
                k.nama_kegiatan,
                k.pagu_anggaran,
                k.status,
                t.nama_tim,
                {$bulanSelect}
                COALESCE(SUM(ra.nominal), 0) AS real_total
            FROM kegiatan k
            LEFT JOIN teams t ON t.id = k.team_id
            LEFT JOIN realisasi_anggaran ra ON ra.kegiatan_id = k.id AND YEAR(ra.tanggal) = {$tahun}
            WHERE YEAR(k.tanggal_mulai) = {$tahun} OR YEAR(k.tanggal_selesai) = {$tahun}
            GROUP BY k.id, k.nama_kegiatan, k.pagu_anggaran, k.status, t.nama_tim
            ORDER BY k.nama_kegiatan ASC
        ")->getResultArray();

        $totalFields = ['pagu_anggaran','real_total'];
        for ($m = 1; $m <= 12; $m++) {
            $totalFields[] = 'real_' . str_pad($m, 2, '0', STR_PAD_LEFT);
        }
        $total = array_fill_keys($totalFields, 0.0);

        foreach ($rows as &$row) {
            foreach ($totalFields as $f) {
                $row[$f] = (float)($row[$f] ?? 0);
                $total[$f] += $row[$f];
            }
            $row['pct_serapan']       = $row['pagu_anggaran'] > 0
                ? round(($row['real_total'] / $row['pagu_anggaran']) * 100, 2) : 0;
            $row['sisa_anggaran']     = $row['pagu_anggaran'] - $row['real_total'];
        }
        unset($row);

        $total['pct_serapan']   = $total['pagu_anggaran'] > 0
            ? round(($total['real_total'] / $total['pagu_anggaran']) * 100, 2) : 0;
        $total['sisa_anggaran'] = $total['pagu_anggaran'] - $total['real_total'];

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Tabel RPD: setiap baris = satu kegiatan.
     * bln_01..bln_12 diinput perencana, dibandingkan realisasi aktual per bulan.
     */
    public function getTabelRpd(int $tahun): array
    {
        $db = \Config\Database::connect();

        // Kolom SELECT bulan RPD
        $rpdSelect  = '';
        $realSelect = '';
        for ($m = 1; $m <= 12; $m++) {
            $pad   = str_pad($m, 2, '0', STR_PAD_LEFT);
            $lastD = date('t', mktime(0, 0, 0, $m, 1, $tahun));
            $rpdSelect  .= "COALESCE(rpd.bln_{$pad}, 0) AS bln_{$pad},\n                ";
            $realSelect .= "COALESCE(SUM(CASE WHEN ra.tanggal BETWEEN '{$tahun}-{$pad}-01' AND '{$tahun}-{$pad}-{$lastD}' THEN ra.nominal ELSE 0 END),0) AS real_{$pad},\n                ";
        }

        $rows = $db->query("
            SELECT
                k.id AS kegiatan_id,
                k.nama_kegiatan,
                k.pagu_anggaran,
                k.status,
                t.nama_tim,
                rpd.id AS rpd_id,
                rpd.keterangan,
                {$rpdSelect}
                {$realSelect}
                COALESCE(SUM(ra.nominal), 0) AS real_total
            FROM kegiatan k
            LEFT JOIN teams t ON t.id = k.team_id
            LEFT JOIN rencana_penarikan_dana rpd
                   ON rpd.kegiatan_id = k.id AND rpd.tahun_anggaran = {$tahun}
            LEFT JOIN realisasi_anggaran ra
                   ON ra.kegiatan_id = k.id AND YEAR(ra.tanggal) = {$tahun}
            WHERE YEAR(k.tanggal_mulai) = {$tahun} OR YEAR(k.tanggal_selesai) = {$tahun}
            GROUP BY k.id, k.nama_kegiatan, k.pagu_anggaran, k.status, t.nama_tim,
                     rpd.id, rpd.keterangan,
                     rpd.bln_01, rpd.bln_02, rpd.bln_03, rpd.bln_04,
                     rpd.bln_05, rpd.bln_06, rpd.bln_07, rpd.bln_08,
                     rpd.bln_09, rpd.bln_10, rpd.bln_11, rpd.bln_12
            ORDER BY k.nama_kegiatan ASC
        ")->getResultArray();

        $totalFields = ['real_total'];
        for ($m = 1; $m <= 12; $m++) {
            $pad = str_pad($m, 2, '0', STR_PAD_LEFT);
            $totalFields[] = "bln_{$pad}";
            $totalFields[] = "real_{$pad}";
        }
        $total = array_fill_keys($totalFields, 0.0);
        $total['rpd_total'] = 0.0;

        foreach ($rows as &$row) {
            $rpdSum  = 0.0;
            $realSum = 0.0;
            for ($m = 1; $m <= 12; $m++) {
                $pad = str_pad($m, 2, '0', STR_PAD_LEFT);
                $row["bln_{$pad}"]  = (float)($row["bln_{$pad}"] ?? 0);
                $row["real_{$pad}"] = (float)($row["real_{$pad}"] ?? 0);
                $rpdSum  += $row["bln_{$pad}"];
                $realSum += $row["real_{$pad}"];
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
