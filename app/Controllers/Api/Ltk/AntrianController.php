<?php

namespace App\Controllers\Api\Ltk;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Ltk\WorkflowEngine;
use App\Models\Ltk\LtkNotifikasiModel;
use App\Models\Ltk\PermohonanModel;
use App\Models\Ltk\SlaConfigModel;

/**
 * AntrianController — Pengelolaan antrian dan penugasan PIC oleh TU.
 */
class AntrianController extends BaseApiController
{
    // GET /api/v1/ltk/antrian
    public function index()
    {
        $this->requirePermission('ltk.antrian');
        return $this->success(['items' => (new PermohonanModel())->getAntrian()]);
    }

    // PUT /api/v1/ltk/antrian/:id/prioritas
    // Body: { "jenis_antrian": "regular" | "prioritas" }
    public function setPrioritas(int $id)
    {
        $this->requirePermission('ltk.antrian');

        $data  = $this->request->getJSON(true) ?? [];
        $jenis = $data['jenis_antrian'] ?? '';
        if (!in_array($jenis, ['regular', 'prioritas'], true)) {
            return $this->fail('Jenis antrian tidak valid. Pilih: regular atau prioritas.', 422);
        }

        $model      = new PermohonanModel();
        $permohonan = $model->find($id);
        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);
        if ($permohonan['status'] !== 'masuk_antrian') {
            return $this->fail('Prioritas hanya bisa diubah saat permohonan ada di antrian.', 422);
        }

        $model->update($id, ['jenis_antrian' => $jenis, 'updated_by' => $this->userId()]);
        $this->auditLog("Set prioritas antrian #{$id} → {$jenis}", 'ltk_permohonan', $id);

        return $this->success(null, 'Prioritas antrian berhasil diperbarui.');
    }

    // POST /api/v1/ltk/antrian/:id/tugaskan
    // Body: { "pic_user_id": 5, "jenis_antrian": "prioritas" }
    public function tugaskan(int $id)
    {
        $this->requirePermission('ltk.tugaskan');

        // Role yang dapat menjadi PIC Telaah
        $picRoles = ['pic_telaah', 'direktur', 'ketua_tim', 'anggota_tim', 'perencana_anggaran'];

        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'pic_user_id'   => 'required|integer',
            'jenis_antrian' => 'permit_empty|in_list[regular,prioritas]',
        ];

        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $model      = new PermohonanModel();
        $permohonan = $model->find($id);
        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);
        if ($permohonan['status'] !== 'masuk_antrian') {
            return $this->fail('Permohonan harus berstatus "masuk_antrian" untuk dapat ditugaskan.', 422);
        }

        // Pastikan pic_user_id memiliki salah satu role yang bisa menjadi PIC
        $picUser = $this->db->table('users')
                            ->whereIn('role', $picRoles)
                            ->where('id', (int)$data['pic_user_id'])
                            ->where('status', 'aktif')
                            ->get()->getRowArray();
        if (!$picUser) {
            return $this->fail('User yang dipilih tidak dapat dijadikan PIC Telaah.', 422);
        }

        $workflow = new WorkflowEngine();
        $workflow->transition($id, 'ditugaskan_pic', $this->userId(),
            "Ditugaskan ke PIC: {$picUser['nama']}",
            [
                'pic_user_id'   => (int)$data['pic_user_id'],
                'jenis_antrian' => $data['jenis_antrian'] ?? $permohonan['jenis_antrian'],
                'tgl_penugasan' => date('Y-m-d H:i:s'),
            ]
        );

        // ── Buat event Schedule otomatis untuk penugasan PIC ──────
        $this->_buatSchedulePenugasan($permohonan, $picUser, (int)$data['pic_user_id']);

        // Notifikasi ke PIC
        (new LtkNotifikasiModel())->kirim(
            (int)$data['pic_user_id'],
            'penugasan_pic',
            'Anda Mendapat Penugasan Telaah',
            "Anda ditugaskan untuk menangani permohonan {$permohonan['kode']}: {$permohonan['judul']}.",
            $id
        );

        // Notifikasi ke Klien
        (new LtkNotifikasiModel())->kirim(
            (int)$permohonan['klien_user_id'],
            'pic_ditugaskan',
            'PIC Telah Ditugaskan',
            "Permohonan {$permohonan['kode']} Anda telah ditugaskan ke PIC dan segera diproses.",
            $id
        );

        $this->auditLog("Tugaskan PIC #{$data['pic_user_id']} ke permohonan #{$id}", 'ltk_permohonan', $id);
        return $this->success(null, 'PIC berhasil ditugaskan.');
    }

    // ──────────────────────────────────────────────────────────────
    // Buat event Schedule otomatis saat PIC ditugaskan ke telaah
    // ──────────────────────────────────────────────────────────────
    private function _buatSchedulePenugasan(array $permohonan, array $picUser, int $picUserId): void
    {
        try {
            $schModel = new \App\Models\Schedule\ScheduleModel();
            $kode     = $schModel->generateKode();

            $cat = $this->db->table('schedule_categories')
                            ->where('kode', 'telaah')->get()->getRowArray();
            $catId = $cat ? (int)$cat['id'] : 1;

            $schModel->insert([
                'kode'        => $kode,
                'judul'       => "Penugasan Telaah: {$permohonan['kode']}",
                'deskripsi'   => "Permohonan {$permohonan['kode']} — {$permohonan['judul']} telah ditugaskan kepada {$picUser['nama']}.",
                'category_id' => $catId,
                'tgl_mulai'   => date('Y-m-d'),
                'tgl_selesai' => date('Y-m-d'),
                'all_day'     => 1,
                'pic_user_id' => $picUserId,
                'sync_google' => 1,
                'ref_type'    => 'ltk_permohonan',
                'ref_id'      => (int)$permohonan['id'],
                'created_by'  => $this->userId(),
            ]);
            $schId = (int)$schModel->insertID();

            // Sync ke Google Calendar — jangan gagalkan flow utama
            try {
                $detail  = $schModel->getDetail($schId);
                $gcal    = new \App\Libraries\Schedule\GoogleCalendarService();
                $eventId = $gcal->createEvent($detail);
                $schModel->update($schId, [
                    'google_event_id'    => $eventId,
                    'google_sync_status' => 'synced',
                    'google_synced_at'   => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) {
                $schModel->update($schId, ['google_sync_status' => 'failed']);
                log_message('info', "[AntrianController] Sync Google Calendar gagal: {$e->getMessage()}");
            }
        } catch (\Throwable $e) {
            log_message('warning', "[AntrianController] Gagal buat schedule penugasan: {$e->getMessage()}");
        }
    }
}
