<?php

namespace App\Controllers\Api\Ltk;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Ltk\SlaCalculator;
use App\Models\Ltk\PermohonanModel;

/**
 * SlaController — Status dan info SLA per permohonan.
 */
class SlaController extends BaseApiController
{
    // GET /api/v1/ltk/sla/:id
    public function show(int $id)
    {
        $this->requirePermission('ltk.view');

        $permohonan = (new PermohonanModel())->find($id);
        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);

        $calc    = new SlaCalculator();
        $slaInfo = $calc->getStatusSla(
            $permohonan['tgl_deadline'] ?? null,
            (bool)$permohonan['is_sla_breach']
        );

        return $this->success([
            'permohonan_id'  => $id,
            'kode'           => $permohonan['kode'],
            'skenario'       => $permohonan['skenario'],
            'sla_hari_kerja' => $permohonan['sla_hari_kerja'],
            'tgl_terima_pic' => $permohonan['tgl_terima_pic'],
            'tgl_deadline'   => $permohonan['tgl_deadline'],
            'is_sla_breach'  => (bool)$permohonan['is_sla_breach'],
            'sla_info'       => $slaInfo,
        ]);
    }
}
