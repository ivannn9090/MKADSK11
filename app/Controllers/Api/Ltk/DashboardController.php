<?php

namespace App\Controllers\Api\Ltk;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Ltk\SlaCalculator;
use App\Models\Ltk\LtkNotifikasiModel;
use App\Models\Ltk\PermohonanModel;

/**
 * DashboardController — Widget agregat per role (TU, PIC, Klien).
 */
class DashboardController extends BaseApiController
{
    // GET /api/v1/ltk/dashboard/tu
    public function tu()
    {
        $this->requirePermission('ltk.view');

        $model = new PermohonanModel();
        $stats = $model->getStatsTU();

        // Jumlah permohonan yang diinput langsung TU dari SRIKANDI
        $tuInput = $this->db->query(
            "SELECT COUNT(*) AS total FROM ltk_permohonan
             WHERE sumber_permohonan = 'tu_input' AND deleted_at IS NULL"
        )->getRow()->total ?? 0;

        return $this->success(array_merge($stats, ['tu_input' => (int)$tuInput]));
    }

    // GET /api/v1/ltk/dashboard/pic
    public function pic()
    {
        $this->requirePermission('ltk.pic');

        $model = new PermohonanModel();
        $stats = $model->getStatsPIC($this->userId());

        // Hitung jumlah policy brief & paper yang sedang disusun
        $db = $this->db;
        $stats['policy_brief'] = $db->query(
            "SELECT COUNT(*) AS total FROM ltk_permohonan
             WHERE pic_user_id = ? AND status = 'penyusunan_policy_brief' AND deleted_at IS NULL",
            [$this->userId()]
        )->getRow()->total ?? 0;

        $stats['policy_paper'] = $db->query(
            "SELECT COUNT(*) AS total FROM ltk_permohonan
             WHERE pic_user_id = ? AND status = 'penyusunan_policy_paper' AND deleted_at IS NULL",
            [$this->userId()]
        )->getRow()->total ?? 0;

        // Tugas-tugas mendekati deadline (3 HK ke bawah)
        $stats['tugas_mendekati_deadline'] = (new PermohonanModel())
            ->where('pic_user_id', $this->userId())
            ->where('is_sla_breach', 0)
            ->where('tgl_deadline IS NOT NULL', null, false)
            ->where('DATEDIFF(tgl_deadline, CURDATE()) BETWEEN 0 AND 3', null, false)
            ->whereNotIn('status', ['selesai','ditutup'])
            ->where('deleted_at', null)
            ->countAllResults(false);

        return $this->success($stats);
    }

    // GET /api/v1/ltk/dashboard/klien
    public function klien()
    {
        $this->requireRole(['klien']);

        $stats = (new PermohonanModel())->getStatsKlien($this->userId());

        // Tambahkan notifikasi belum dibaca
        $stats['notif_unread'] = (new LtkNotifikasiModel())->countUnread($this->userId());

        return $this->success($stats);
    }
}
