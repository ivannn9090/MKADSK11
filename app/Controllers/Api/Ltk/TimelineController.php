<?php

namespace App\Controllers\Api\Ltk;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Ltk\SlaCalculator;
use App\Models\Ltk\AktivitasLogModel;
use App\Models\Ltk\PermohonanModel;

/**
 * TimelineController — Timeline realtime permohonan untuk semua role.
 * SlaController — Status dan info SLA per permohonan.
 */
class TimelineController extends BaseApiController
{
    // GET /api/v1/ltk/permohonan/:id/timeline
    public function show(int $id)
    {
        $model      = new PermohonanModel();
        $permohonan = $model->find($id);
        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);

        // Klien hanya boleh lihat miliknya
        if ($this->userRole() === 'klien' && (int)$permohonan['klien_user_id'] !== $this->userId()) {
            return $this->fail('Akses ditolak.', 403);
        }

        $timeline = (new AktivitasLogModel())->getTimeline($id);

        return $this->success([
            'permohonan_id' => $id,
            'kode'          => $permohonan['kode'],
            'status'        => $permohonan['status'],
            'timeline'      => $timeline,
        ]);
    }
}
