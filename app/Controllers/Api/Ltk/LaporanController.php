<?php

namespace App\Controllers\Api\Ltk;

use App\Controllers\Api\BaseApiController;

/**
 * LaporanController — 10 laporan kinerja layanan telaah.
 */
class LaporanController extends BaseApiController
{
    public function __construct()
    {
        // Semua laporan: hanya TU dan Admin
    }

    // GET /api/v1/ltk/laporan/permohonan?dari=2025-01-01&sampai=2025-12-31
    public function permohonan()
    {
        $this->requirePermission('ltk.laporan');
        [$dari, $sampai] = $this->_rangeFilter();

        $rows = $this->db->query("
            SELECT p.kode, p.judul, p.status, p.jenis_antrian, p.skenario,
                   p.tgl_deadline, p.tgl_selesai, p.is_sla_breach,
                   u_k.nama AS klien_nama, u_k.email AS klien_email,
                   p.unit_kerja, u_p.nama AS pic_nama, p.created_at
            FROM ltk_permohonan p
            LEFT JOIN users u_k ON u_k.id = p.klien_user_id
            LEFT JOIN users u_p ON u_p.id = p.pic_user_id
            WHERE p.deleted_at IS NULL
              AND DATE(p.created_at) BETWEEN ? AND ?
            ORDER BY p.created_at DESC
        ", [$dari, $sampai])->getResultArray();

        return $this->success(['items' => $rows, 'total' => count($rows)]);
    }

    // GET /api/v1/ltk/laporan/per-unit?dari=...&sampai=...
    public function perUnit()
    {
        $this->requirePermission('ltk.laporan');
        [$dari, $sampai] = $this->_rangeFilter();

        $rows = $this->db->query("
            SELECT unit_kerja,
                   COUNT(*) AS total,
                   SUM(CASE WHEN status IN ('selesai','ditutup') THEN 1 ELSE 0 END) AS selesai,
                   SUM(is_sla_breach) AS sla_breach
            FROM ltk_permohonan
            WHERE deleted_at IS NULL AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY unit_kerja
            ORDER BY total DESC
        ", [$dari, $sampai])->getResultArray();

        return $this->success(['items' => $rows]);
    }

    // GET /api/v1/ltk/laporan/per-pic?dari=...&sampai=...
    public function perPic()
    {
        $this->requirePermission('ltk.laporan');
        [$dari, $sampai] = $this->_rangeFilter();

        $rows = $this->db->query("
            SELECT u.nama AS pic_nama, u.jabatan,
                   COUNT(p.id) AS total_tugas,
                   SUM(CASE WHEN p.status IN ('selesai','ditutup') THEN 1 ELSE 0 END) AS selesai,
                   SUM(p.is_sla_breach) AS sla_breach
            FROM ltk_permohonan p
            JOIN users u ON u.id = p.pic_user_id
            WHERE p.deleted_at IS NULL AND DATE(p.created_at) BETWEEN ? AND ?
            GROUP BY p.pic_user_id, u.nama, u.jabatan
            ORDER BY total_tugas DESC
        ", [$dari, $sampai])->getResultArray();

        return $this->success(['items' => $rows]);
    }

    // GET /api/v1/ltk/laporan/sla?dari=...&sampai=...
    public function sla()
    {
        $this->requirePermission('ltk.laporan');
        [$dari, $sampai] = $this->_rangeFilter();

        $rows = $this->db->query("
            SELECT p.kode, p.judul, p.skenario, p.sla_hari_kerja,
                   p.tgl_terima_pic, p.tgl_deadline, p.tgl_selesai,
                   p.is_sla_breach, u.nama AS pic_nama,
                   CASE
                     WHEN p.tgl_selesai IS NOT NULL
                     THEN DATEDIFF(p.tgl_selesai, p.tgl_terima_pic)
                     ELSE NULL
                   END AS durasi_hari_kalender
            FROM ltk_permohonan p
            LEFT JOIN users u ON u.id = p.pic_user_id
            WHERE p.deleted_at IS NULL
              AND p.tgl_terima_pic IS NOT NULL
              AND DATE(p.created_at) BETWEEN ? AND ?
            ORDER BY p.is_sla_breach DESC, p.tgl_deadline ASC
        ", [$dari, $sampai])->getResultArray();

        $totalBreach = array_sum(array_column($rows, 'is_sla_breach'));

        return $this->success([
            'items'        => $rows,
            'total'        => count($rows),
            'total_breach' => $totalBreach,
        ]);
    }

    // GET /api/v1/ltk/laporan/prioritas?dari=...&sampai=...
    public function prioritas()
    {
        $this->requirePermission('ltk.laporan');
        [$dari, $sampai] = $this->_rangeFilter();

        $rows = $this->db->query("
            SELECT jenis_antrian,
                   COUNT(*) AS total,
                   SUM(CASE WHEN status IN ('selesai','ditutup') THEN 1 ELSE 0 END) AS selesai,
                   SUM(is_sla_breach) AS sla_breach
            FROM ltk_permohonan
            WHERE deleted_at IS NULL AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY jenis_antrian
        ", [$dari, $sampai])->getResultArray();

        return $this->success(['items' => $rows]);
    }

    // GET /api/v1/ltk/laporan/output-jenis?dari=...&sampai=...
    public function outputJenis()
    {
        $this->requirePermission('ltk.laporan');
        [$dari, $sampai] = $this->_rangeFilter();

        $rows = $this->db->query("
            SELECT tipe_dokumen, COUNT(*) AS total
            FROM ltk_dokumen_output d
            JOIN ltk_permohonan p ON p.id = d.permohonan_id
            WHERE p.deleted_at IS NULL AND DATE(d.created_at) BETWEEN ? AND ?
            GROUP BY tipe_dokumen
            ORDER BY total DESC
        ", [$dari, $sampai])->getResultArray();

        return $this->success(['items' => $rows]);
    }

    // GET /api/v1/ltk/laporan/statistik-policy-brief?dari=...&sampai=...
    public function statistikPolicyBrief()
    {
        $this->requirePermission('ltk.laporan');
        [$dari, $sampai] = $this->_rangeFilter();

        $rows = $this->db->query("
            SELECT p.kode, p.judul, p.skenario, u.nama AS pic_nama,
                   d.tgl_dokumen AS tgl_policy_brief,
                   d.link_cloud, d.keterangan_link
            FROM ltk_dokumen_output d
            JOIN ltk_permohonan p ON p.id = d.permohonan_id
            LEFT JOIN users u ON u.id = p.pic_user_id
            WHERE d.tipe_dokumen = 'policy_brief'
              AND p.deleted_at IS NULL
              AND DATE(d.created_at) BETWEEN ? AND ?
            ORDER BY d.created_at DESC
        ", [$dari, $sampai])->getResultArray();

        return $this->success(['items' => $rows, 'total' => count($rows)]);
    }

    // GET /api/v1/ltk/laporan/statistik-policy-paper?dari=...&sampai=...
    public function statistikPolicyPaper()
    {
        $this->requirePermission('ltk.laporan');
        [$dari, $sampai] = $this->_rangeFilter();

        $rows = $this->db->query("
            SELECT p.kode, p.judul, u.nama AS pic_nama,
                   d.tgl_dokumen AS tgl_policy_paper,
                   d.link_cloud, d.keterangan_link
            FROM ltk_dokumen_output d
            JOIN ltk_permohonan p ON p.id = d.permohonan_id
            LEFT JOIN users u ON u.id = p.pic_user_id
            WHERE d.tipe_dokumen = 'policy_paper'
              AND p.deleted_at IS NULL
              AND DATE(d.created_at) BETWEEN ? AND ?
            ORDER BY d.created_at DESC
        ", [$dari, $sampai])->getResultArray();

        return $this->success(['items' => $rows, 'total' => count($rows)]);
    }

    // GET /api/v1/ltk/laporan/kinerja-pic?dari=...&sampai=...
    public function kinerjaPic()
    {
        $this->requirePermission('ltk.laporan');
        [$dari, $sampai] = $this->_rangeFilter();

        $rows = $this->db->query("
            SELECT u.nama AS pic_nama, u.jabatan,
                   COUNT(p.id) AS total_selesai,
                   AVG(DATEDIFF(p.tgl_selesai, p.tgl_terima_pic)) AS rata_hari_kalender,
                   SUM(p.is_sla_breach) AS total_breach,
                   SUM(CASE WHEN p.skenario = 'A' THEN 1 ELSE 0 END) AS skenario_a,
                   SUM(CASE WHEN p.skenario = 'B' THEN 1 ELSE 0 END) AS skenario_b
            FROM ltk_permohonan p
            JOIN users u ON u.id = p.pic_user_id
            WHERE p.deleted_at IS NULL
              AND p.status IN ('selesai','ditutup')
              AND p.tgl_selesai IS NOT NULL
              AND DATE(p.tgl_selesai) BETWEEN ? AND ?
            GROUP BY p.pic_user_id, u.nama, u.jabatan
            ORDER BY total_selesai DESC
        ", [$dari, $sampai])->getResultArray();

        return $this->success(['items' => $rows]);
    }

    // GET /api/v1/ltk/laporan/rata-waktu?dari=...&sampai=...
    public function rataWaktu()
    {
        $this->requirePermission('ltk.laporan');
        [$dari, $sampai] = $this->_rangeFilter();

        $row = $this->db->query("
            SELECT
                COUNT(*) AS total_selesai,
                ROUND(AVG(DATEDIFF(tgl_selesai, tgl_terima_pic)), 1) AS rata_hari_kalender,
                SUM(CASE WHEN skenario = 'A' THEN 1 ELSE 0 END) AS total_skenario_a,
                SUM(CASE WHEN skenario = 'B' THEN 1 ELSE 0 END) AS total_skenario_b,
                ROUND(AVG(CASE WHEN skenario = 'A' THEN DATEDIFF(tgl_selesai, tgl_terima_pic) END), 1) AS rata_hari_skenario_a,
                ROUND(AVG(CASE WHEN skenario = 'B' THEN DATEDIFF(tgl_selesai, tgl_terima_pic) END), 1) AS rata_hari_skenario_b
            FROM ltk_permohonan
            WHERE deleted_at IS NULL
              AND status IN ('selesai','ditutup')
              AND tgl_selesai IS NOT NULL
              AND tgl_terima_pic IS NOT NULL
              AND DATE(tgl_selesai) BETWEEN ? AND ?
        ", [$dari, $sampai])->getRowArray();

        return $this->success($row);
    }

    // ──────────────────────────────────────────────────────────────
    // Private helper — baca parameter rentang tanggal dari query string
    // ──────────────────────────────────────────────────────────────
    private function _rangeFilter(): array
    {
        $dari   = $this->request->getGet('dari')   ?? date('Y-01-01');
        $sampai = $this->request->getGet('sampai') ?? date('Y-12-31');

        // Validasi sederhana — fallback ke default jika format salah
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari))   $dari   = date('Y-01-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) $sampai = date('Y-12-31');

        return [$dari, $sampai];
    }
}
