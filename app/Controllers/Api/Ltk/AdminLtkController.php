<?php

namespace App\Controllers\Api\Ltk;

use App\Controllers\Api\BaseApiController;
use App\Models\Ltk\HariLiburModel;
use App\Models\Ltk\LtkNotifikasiModel;
use App\Models\Ltk\NotaDinasTemplateModel;
use App\Models\Ltk\SlaConfigModel;

/**
 * AdminLtkController — Konfigurasi SLA, hari libur, notifikasi, dan
 *                      informasi template Nota Dinas untuk Admin/TU.
 */
class AdminLtkController extends BaseApiController
{
    // ──────────────────────────────────────────────────────────────
    // SLA Config
    // ──────────────────────────────────────────────────────────────

    // GET /api/v1/ltk/admin/sla-config
    public function slaConfigIndex()
    {
        $this->requirePermission('ltk.admin');
        return $this->success(['items' => (new SlaConfigModel())->findAll()]);
    }

    // PUT /api/v1/ltk/admin/sla-config
    // Body: { "A": 6, "B": 28 }
    public function slaConfigUpdate()
    {
        $this->requirePermission('ltk.admin');

        $data  = $this->request->getJSON(true) ?? [];
        $model = new SlaConfigModel();

        foreach (['A', 'B'] as $sk) {
            if (isset($data[$sk]) && is_numeric($data[$sk]) && (int)$data[$sk] > 0) {
                $row = $model->getBySkenario($sk);
                if ($row) {
                    $model->update($row['id'], [
                        'hari_kerja' => (int)$data[$sk],
                        'updated_by' => $this->userId(),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        $this->auditLog('Update SLA Config LTK', 'ltk_sla_config');
        return $this->success(null, 'Konfigurasi SLA berhasil diperbarui.');
    }

    // ──────────────────────────────────────────────────────────────
    // Hari Libur
    // ──────────────────────────────────────────────────────────────

    // GET /api/v1/ltk/admin/hari-libur?tahun=2025
    public function hariLiburIndex()
    {
        $this->requirePermission('ltk.admin');
        $tahun = (int)($this->request->getGet('tahun') ?? date('Y'));
        return $this->success(['items' => (new HariLiburModel())->where('tanggal LIKE', $tahun . '%')->orderBy('tanggal')->findAll()]);
    }

    // POST /api/v1/ltk/admin/hari-libur
    // Body: { "tanggal": "2025-01-01", "keterangan": "Tahun Baru" }
    public function hariLiburCreate()
    {
        $this->requirePermission('ltk.admin');

        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'tanggal'    => 'required|valid_date',
            'keterangan' => 'permit_empty|max_length[200]',
        ];
        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $model = new HariLiburModel();
        // Cegah duplikat
        if ($model->where('tanggal', $data['tanggal'])->first()) {
            return $this->fail('Tanggal ' . $data['tanggal'] . ' sudah terdaftar sebagai hari libur.', 422);
        }

        $model->insert(['tanggal' => $data['tanggal'], 'keterangan' => $data['keterangan'] ?? null]);
        return $this->success(null, 'Hari libur berhasil ditambahkan.', 201);
    }

    // POST /api/v1/ltk/admin/hari-libur/batch
    // Body: { "items": [ { "tanggal": "...", "keterangan": "..." }, ... ] }
    public function hariLiburBatch()
    {
        $this->requirePermission('ltk.admin');

        $data  = $this->request->getJSON(true) ?? [];
        $items = $data['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            return $this->fail('Data items tidak valid.', 422);
        }

        $model   = new HariLiburModel();
        $inserted = 0;
        $skipped  = 0;

        foreach ($items as $item) {
            if (empty($item['tanggal'])) continue;
            if ($model->where('tanggal', $item['tanggal'])->first()) {
                $skipped++;
                continue;
            }
            $model->insert(['tanggal' => $item['tanggal'], 'keterangan' => $item['keterangan'] ?? null]);
            $inserted++;
        }

        return $this->success([
            'inserted' => $inserted,
            'skipped'  => $skipped,
        ], "{$inserted} hari libur ditambahkan, {$skipped} dilewati (sudah ada).");
    }

    // DELETE /api/v1/ltk/admin/hari-libur/:id
    public function hariLiburDelete(int $id)
    {
        $this->requirePermission('ltk.admin');

        $model = new HariLiburModel();
        $row   = $model->find($id);
        if (!$row) return $this->fail('Hari libur tidak ditemukan.', 404);

        $model->delete($id);
        return $this->success(null, 'Hari libur berhasil dihapus.');
    }

    // ──────────────────────────────────────────────────────────────
    // Info Template Nota Dinas (read-only dari sisi API)
    // File template dikelola manual di writable/templates/ltk/
    // ──────────────────────────────────────────────────────────────

    // GET /api/v1/ltk/admin/nd-template-status
    public function ndTemplateStatus()
    {
        $this->requirePermission('ltk.admin');

        $gen    = new \App\Libraries\Ltk\NotaDinasGenerator();
        $tipes  = array_keys(\App\Libraries\Ltk\NotaDinasGenerator::TEMPLATE_VARS);
        $status = [];

        foreach ($tipes as $tipe) {
            $status[] = [
                'tipe'       => $tipe,
                'tersedia'   => $gen->templateTersedia($tipe),
                'variabel'   => \App\Libraries\Ltk\NotaDinasGenerator::TEMPLATE_VARS[$tipe],
            ];
        }

        return $this->success(['items' => $status]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/ltk/admin/nd-template/upload
    // Admin upload file .docx template Nota Dinas
    // Form-data: tipe (string), file (file .docx)
    // ──────────────────────────────────────────────────────────────
    public function ndTemplateUpload()
    {
        $this->requirePermission('ltk.admin');

        $tipeOptions = array_keys(\App\Libraries\Ltk\NotaDinasGenerator::TEMPLATE_VARS);
        $tipe        = $this->request->getPost('tipe');

        if (!$tipe || !in_array($tipe, $tipeOptions, true)) {
            return $this->fail('Tipe template tidak valid. Pilih: ' . implode(', ', $tipeOptions), 422);
        }

        $file = $this->request->getFile('file');
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            return $this->fail('File tidak valid atau belum diupload.', 422);
        }

        // Validasi ekstensi dan MIME
        $ext  = strtolower($file->getClientExtension());
        $mime = $file->getMimeType();
        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/octet-stream', // beberapa browser kirim ini untuk .docx
        ];
        if ($ext !== 'docx' || !in_array($mime, $allowedMimes, true)) {
            return $this->fail('File harus berformat .docx (Microsoft Word).', 422);
        }

        // Simpan ke writable/templates/ltk/<nama_file_tetap>.docx
        $templateDir  = WRITEPATH . 'templates/ltk/';
        if (!is_dir($templateDir)) {
            mkdir($templateDir, 0755, true);
        }

        // Nama file sesuai mapping di NotaDinasGenerator::TEMPLATE_FILES (private const)
        // Gunakan konvensi: nd_<tipe_singkat>.docx
        $namaMap = [
            'nota_dinas_pengajuan'                => 'nd_pengajuan.docx',
            'nota_dinas_tanggapan'                => 'nd_tanggapan.docx',
            'nota_dinas_permintaan_penyesuaian'   => 'nd_permintaan_penyesuaian.docx',
            'nota_dinas_hasil_telaah_akhir'       => 'nd_hasil_telaah_akhir.docx',
        ];

        $namaFile = $namaMap[$tipe];
        $destPath = $templateDir . $namaFile;

        // Hapus file lama jika ada (akan ditimpa)
        if (file_exists($destPath)) {
            unlink($destPath);
        }

        if (!$file->move($templateDir, $namaFile)) {
            return $this->fail('Gagal menyimpan file template.', 500);
        }

        $this->auditLog("Upload template ND: {$tipe} ({$namaFile})", 'ltk_nota_dinas_template');

        $gen = new \App\Libraries\Ltk\NotaDinasGenerator();
        return $this->success([
            'tipe'     => $tipe,
            'file'     => $namaFile,
            'tersedia' => $gen->templateTersedia($tipe),
            'variabel' => \App\Libraries\Ltk\NotaDinasGenerator::TEMPLATE_VARS[$tipe],
        ], "Template '{$tipe}' berhasil diupload. Placeholder yang tersedia ada di field 'variabel'.");
    }

    // ──────────────────────────────────────────────────────────────
    // Notifikasi LTK
    // ──────────────────────────────────────────────────────────────

    // GET /api/v1/ltk/notifikasi
    public function notifikasiIndex()
    {
        $model       = new LtkNotifikasiModel();
        $items       = $model->getByUser($this->userId());
        $unreadCount = $model->countUnread($this->userId());
        return $this->success(['items' => $items, 'unread_count' => $unreadCount]);
    }

    // PUT /api/v1/ltk/notifikasi/read-all
    public function notifikasiReadAll()
    {
        (new LtkNotifikasiModel())->markAllRead($this->userId());
        return $this->success(null, 'Semua notifikasi ditandai dibaca.');
    }
}
