<?php

namespace App\Controllers\Api\Ltk;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Ltk\SlaCalculator;
use App\Libraries\Ltk\WorkflowEngine;
use App\Models\Ltk\LtkNotifikasiModel;
use App\Models\Ltk\PermohonanModel;
use App\Models\Ltk\SlaConfigModel;

/**
 * PicController — Aksi PIC: terima penugasan, lihat tugas aktif.
 *
 * "PIC Telaah" tidak hanya role pic_telaah — direktur, ketua_tim, anggota_tim,
 * dan perencana_anggaran juga dapat ditugaskan sebagai PIC.
 */
class PicController extends BaseApiController
{
    /** Semua role yang dapat berperan sebagai PIC Telaah. */
    private const PIC_ROLES = ['pic_telaah', 'direktur', 'ketua_tim', 'anggota_tim', 'perencana_anggaran'];

    // GET /api/v1/ltk/pic/tugas
    public function tugasAktif()
    {
        $this->requirePermission('ltk.pic');

        $items = (new PermohonanModel())->getListLengkap([
            'pic_user_id' => $this->userId(),
        ]);

        // Lampirkan info SLA per item
        $calc = new SlaCalculator();
        foreach ($items as &$item) {
            $item['sla_info'] = $calc->getStatusSla(
                $item['tgl_deadline'] ?? null,
                (bool)$item['is_sla_breach']
            );
        }
        unset($item);

        return $this->success(['items' => $items]);
    }

    // POST /api/v1/ltk/pic/tugas/:id/terima
    public function terima(int $id)
    {
        $this->requirePermission('ltk.pic');

        $model      = new PermohonanModel();
        $permohonan = $model->find($id);
        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);
        if ((int)$permohonan['pic_user_id'] !== $this->userId()) return $this->fail('Bukan tugas Anda.', 403);
        if ($permohonan['status'] !== 'ditugaskan_pic') return $this->fail('Status tidak sesuai untuk aksi ini.', 422);

        // Hitung SLA berdasarkan skenario yang belum diketahui → default skenario B (28 HK) sebagai nilai aman
        // SLA akan diperbarui otomatis setelah asesmen dilakukan
        $slaConfig  = (new SlaConfigModel())->getHariKerja('B'); // sementara pakai B
        $calc       = new SlaCalculator();
        $deadline   = $calc->hitungDeadline(date('Y-m-d H:i:s'), $slaConfig);

        $workflow = new WorkflowEngine();
        $workflow->transition($id, 'diterima_pic', $this->userId(),
            'PIC menerima penugasan — SLA dimulai',
            [
                'tgl_terima_pic' => date('Y-m-d H:i:s'),
                'sla_hari_kerja' => $slaConfig,
                'tgl_deadline'   => $deadline,
            ]
        );

        // Notifikasi TU dan Klien
        $notif = new LtkNotifikasiModel();
        $notif->kirim(
            (int)$permohonan['klien_user_id'],
            'pic_terima_tugas',
            'PIC Mulai Menangani Permohonan Anda',
            "Permohonan {$permohonan['kode']} sudah diterima PIC dan sedang dalam proses telaah.",
            $id
        );

        $this->auditLog("PIC terima tugas permohonan #{$id}", 'ltk_permohonan', $id);
        return $this->success(['tgl_deadline' => $deadline], 'Penugasan berhasil diterima. SLA dimulai.');
    }

    // POST /api/v1/ltk/pic/tugas/:id/mulai-asesmen
    public function mulaiAsesmen(int $id)
    {
        $this->requirePermission('ltk.pic');

        $model      = new PermohonanModel();
        $permohonan = $model->find($id);
        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);
        if ((int)$permohonan['pic_user_id'] !== $this->userId()) return $this->fail('Bukan tugas Anda.', 403);
        if ($permohonan['status'] !== 'diterima_pic') return $this->fail('Status tidak sesuai.', 422);

        $workflow = new WorkflowEngine();
        $workflow->transition($id, 'asesmen_awal', $this->userId(), 'Asesmen awal dimulai');

        return $this->success(null, 'Asesmen awal dimulai.');
    }

    // GET /api/v1/ltk/pic/list  (TU/Admin — daftar semua user yang dapat jadi PIC)
    public function listPic()
    {
        $this->requirePermission('ltk.tugaskan');

        $items = $this->db->table('users')
                          ->select('id, nama, jabatan, email, role')
                          ->whereIn('role', self::PIC_ROLES)
                          ->where('status', 'aktif')
                          ->orderBy('nama')
                          ->get()->getResultArray();

        return $this->success(['items' => $items]);
    }
}
