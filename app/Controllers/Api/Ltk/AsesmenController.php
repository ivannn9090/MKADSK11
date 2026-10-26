<?php

namespace App\Controllers\Api\Ltk;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Ltk\SlaCalculator;
use App\Libraries\Ltk\WorkflowEngine;
use App\Models\Ltk\AsesmenModel;
use App\Models\Ltk\LtkNotifikasiModel;
use App\Models\Ltk\PermohonanModel;
use App\Models\Ltk\SlaConfigModel;

/**
 * AsesmenController — PIC melakukan asesmen awal dan memilih skenario.
 * Setelah skenario dipilih, SLA di-recalculate ke nilai yang tepat (A=6HK, B=28HK).
 */
class AsesmenController extends BaseApiController
{
    // POST /api/v1/ltk/asesmen
    // Body: { "permohonan_id": 1, "skenario": "A"|"B", "catatan_asesmen": "..." }
    public function store()
    {
        $this->requirePermission('ltk.pic');

        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'permohonan_id'  => 'required|integer',
            'skenario'       => 'required|in_list[A,B]',
            'catatan_asesmen'=> 'permit_empty|max_length[2000]',
        ];

        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $permohonanId = (int)$data['permohonan_id'];
        $model        = new PermohonanModel();
        $permohonan   = $model->find($permohonanId);

        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);
        if ((int)$permohonan['pic_user_id'] !== $this->userId()) return $this->fail('Bukan tugas Anda.', 403);
        if ($permohonan['status'] !== 'asesmen_awal') {
            return $this->fail('Asesmen hanya bisa disubmit saat status "asesmen_awal".', 422);
        }

        $skenario = $data['skenario'];

        // Simpan record asesmen
        (new AsesmenModel())->insert([
            'permohonan_id'  => $permohonanId,
            'pic_user_id'    => $this->userId(),
            'skenario'       => $skenario,
            'catatan_asesmen'=> $data['catatan_asesmen'] ?? null,
            'tgl_asesmen'    => date('Y-m-d H:i:s'),
        ]);

        // Recalculate SLA dengan skenario yang tepat
        $slaHk    = (new SlaConfigModel())->getHariKerja($skenario);
        $calc     = new SlaCalculator();
        $deadline = $calc->hitungDeadline($permohonan['tgl_terima_pic'] ?? date('Y-m-d H:i:s'), $slaHk);

        // Transisi ke status berdasarkan skenario
        $statusBaru = ($skenario === 'A') ? 'tidak_perlu_tindak_lanjut' : 'perlu_tindak_lanjut';
        $catatan    = $skenario === 'A'
            ? 'Asesmen: Kajian tidak memerlukan tindak lanjut — SLA 6 HK'
            : 'Asesmen: Kajian memerlukan tindak lanjut — SLA 28 HK';

        $workflow = new WorkflowEngine();
        $workflow->transition($permohonanId, $statusBaru, $this->userId(), $catatan, [
            'skenario'       => $skenario,
            'sla_hari_kerja' => $slaHk,
            'tgl_deadline'   => $deadline,
        ]);

        // Notifikasi Klien
        $pesanKlien = $skenario === 'A'
            ? "Permohonan {$permohonan['kode']} sedang dalam penyusunan Policy Brief (tidak memerlukan tindak lanjut)."
            : "Permohonan {$permohonan['kode']} memerlukan kajian lanjutan. Proses telaah sedang berjalan.";

        (new LtkNotifikasiModel())->kirim(
            (int)$permohonan['klien_user_id'],
            'asesmen_selesai',
            'Asesmen Awal Selesai',
            $pesanKlien,
            $permohonanId
        );

        $this->auditLog("Asesmen skenario {$skenario} permohonan #{$permohonanId}", 'ltk_asesmen', $permohonanId);

        return $this->success([
            'skenario'    => $skenario,
            'sla_hk'      => $slaHk,
            'tgl_deadline'=> $deadline,
        ], "Asesmen skenario {$skenario} berhasil disimpan.");
    }

    // GET /api/v1/ltk/asesmen/:permohonanId
    public function show(int $permohonanId)
    {
        $this->requirePermission('ltk.view');

        $asesmen = (new AsesmenModel())->getByPermohonan($permohonanId);
        if (!$asesmen) return $this->fail('Asesmen belum dilakukan.', 404);

        return $this->success($asesmen);
    }
}
