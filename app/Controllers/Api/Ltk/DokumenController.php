<?php

namespace App\Controllers\Api\Ltk;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Ltk\NotaDinasGenerator;
use App\Libraries\Ltk\WorkflowEngine;
use App\Models\Ltk\DokumenOutputModel;
use App\Models\Ltk\LtkNotifikasiModel;
use App\Models\Ltk\PermohonanModel;

/**
 * DokumenController — Upload Policy Brief/Paper (link cloud) dan generate Nota Dinas output.
 *
 * Klien tidak mengupload file. Dokumen rujukan dari Klien berupa link OneDrive/Google Drive
 * yang disimpan di ltk_jawaban_field.nilai_link (sudah ditangani di PermohonanController::create).
 *
 * PIC meng-input link cloud untuk Policy Brief / Policy Paper.
 * Nota Dinas tetap di-generate dari template statis Tata Naskah Dinas.
 */
class DokumenController extends BaseApiController
{
    // GET /api/v1/ltk/dokumen/:permohonanId
    public function index(int $permohonanId)
    {
        $this->requirePermission('ltk.view');

        $permohonan = (new PermohonanModel())->find($permohonanId);
        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);

        // Klien hanya boleh lihat miliknya
        if ($this->userRole() === 'klien' && (int)$permohonan['klien_user_id'] !== $this->userId()) {
            return $this->fail('Akses ditolak.', 403);
        }

        return $this->success(['items' => (new DokumenOutputModel())->getByPermohonan($permohonanId)]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/ltk/dokumen/policy-brief
    // PIC submit link cloud Policy Brief + transisi status
    // Body: { "permohonan_id": 1, "link_cloud": "https://...", "keterangan_link": "..." }
    // ──────────────────────────────────────────────────────────────
    public function submitPolicyBrief()
    {
        $this->requirePermission('ltk.pic');
        return $this->_submitLinkDokumen(
            'policy_brief',
            ['tidak_perlu_tindak_lanjut', 'perlu_tindak_lanjut'],
            'penyusunan_policy_brief',
            'Policy Brief telah disubmit.'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/ltk/dokumen/policy-paper
    // PIC submit link cloud Policy Paper + transisi status
    // ──────────────────────────────────────────────────────────────
    public function submitPolicyPaper()
    {
        $this->requirePermission('ltk.pic');
        return $this->_submitLinkDokumen(
            'policy_paper',
            ['kajian_lanjutan'],
            'penyusunan_policy_paper',
            'Policy Paper telah disubmit.'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/ltk/dokumen/generate-nota-dinas
    // PIC/TU generate Nota Dinas output setelah dokumen siap
    // Body: { "permohonan_id": 1, "tipe": "nota_dinas_tanggapan", "variabel": {...}, "nomor_nd": "..." }
    // ──────────────────────────────────────────────────────────────
    public function generateNotaDinas()
    {
        $this->requirePermission('ltk.pic');

        $data  = $this->request->getJSON(true) ?? [];
        $tipeOptions = [
            'nota_dinas_tanggapan',
            'nota_dinas_permintaan_penyesuaian',
            'nota_dinas_hasil_telaah_akhir',
        ];

        $rules = [
            'permohonan_id' => 'required|integer',
            'tipe'          => 'required|in_list[' . implode(',', $tipeOptions) . ']',
            'nomor_nd'      => 'permit_empty|max_length[100]',
            'variabel'      => 'required',
        ];

        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $permohonanId = (int)$data['permohonan_id'];
        $permohonan   = (new PermohonanModel())->find($permohonanId);
        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);

        // PIC hanya bisa generate untuk permohonan miliknya
        $picRoles = ['pic_telaah', 'direktur', 'ketua_tim', 'anggota_tim', 'perencana_anggaran'];
        if (in_array($this->userRole(), $picRoles) && (int)$permohonan['pic_user_id'] !== $this->userId()) {
            return $this->fail('Bukan tugas Anda.', 403);
        }

        $tipe     = $data['tipe'];
        $variabel = (array)($data['variabel'] ?? []);
        $nomorNd  = $data['nomor_nd'] ?? null;

        // Tambahkan variabel konteks dari data permohonan
        $variabel = array_merge([
            'KODE_PERMOHONAN'     => $permohonan['kode'],
            'PERIHAL'             => $permohonan['judul'],
            'UNIT_KERJA_PENGUSUL' => $permohonan['unit_kerja'] ?? '',
            'TANGGAL_ND'          => (new NotaDinasGenerator())->formatTanggalIndonesia(date('Y-m-d')),
            'TAHUN'               => date('Y'),
        ], $variabel);

        try {
            $gen    = new NotaDinasGenerator();
            $result = $gen->generate($permohonanId, $tipe, $variabel, $this->userId(), $nomorNd);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        // Transisi status workflow setelah Nota Dinas diterbitkan
        $transisiMap = [
            'nota_dinas_tanggapan'                => [
                'dari'    => 'penyusunan_policy_brief',
                // Cek skenario A → langsung selesai setelah ND Tanggapan
                'ke'      => 'penyusunan_nota_dinas_tanggapan',
                'catatan' => 'Nota Dinas Tanggapan diterbitkan',
            ],
            'nota_dinas_permintaan_penyesuaian' => [
                'ke'      => 'nota_dinas_permintaan_penyesuaian',
                'catatan' => 'Nota Dinas Permintaan Penyesuaian diterbitkan — Kajian lanjutan dimulai',
            ],
            'nota_dinas_hasil_telaah_akhir' => [
                'ke'      => 'nota_dinas_hasil_telaah_akhir',
                'catatan' => 'Nota Dinas Hasil Telaah Akhir diterbitkan',
            ],
        ];

        if (isset($transisiMap[$tipe])) {
            try {
                $map      = $transisiMap[$tipe];
                $workflow = new WorkflowEngine();
                $workflow->transition($permohonanId, $map['ke'], $this->userId(), $map['catatan']);

                // Untuk nota_dinas_permintaan_penyesuaian → otomatis lanjut ke kajian_lanjutan
                if ($tipe === 'nota_dinas_permintaan_penyesuaian') {
                    $workflow->transition($permohonanId, 'kajian_lanjutan', $this->userId(), 'Kajian lanjutan dimulai');
                }
            } catch (\RuntimeException $e) {
                // Jika transisi gagal (status sudah berubah), log saja — ND tetap tersimpan
                log_message('warning', "[LTK] Transisi setelah generate ND gagal: {$e->getMessage()}");
            }
        }

        // Notifikasi Klien
        (new LtkNotifikasiModel())->kirim(
            (int)$permohonan['klien_user_id'],
            'nota_dinas_terbit',
            'Nota Dinas Diterbitkan',
            "Nota Dinas untuk permohonan {$permohonan['kode']} telah diterbitkan.",
            $permohonanId
        );

        $this->auditLog("Generate {$tipe} permohonan #{$permohonanId}", 'ltk_dokumen_output', $result['dokumen_id']);

        return $this->success([
            'dokumen_id' => $result['dokumen_id'],
            'docx_rel'   => $result['docx_rel'],
            'pdf_rel'    => $result['pdf_rel'],
        ], 'Nota Dinas berhasil digenerate.');
    }

    // POST /api/v1/ltk/dokumen/:id/selesai
    // TU/PIC tutup permohonan setelah semua output diterbitkan
    public function selesai(int $id)
    {
        $this->requirePermission('ltk.pic');

        $model      = new PermohonanModel();
        $permohonan = $model->find($id);
        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);

        $workflow = new WorkflowEngine();

        // Transisi ke selesai jika belum
        if ($permohonan['status'] !== 'selesai' && $permohonan['status'] !== 'ditutup') {
            try {
                $workflow->transition($id, 'selesai', $this->userId(), 'Semua output telah diterbitkan', [
                    'tgl_selesai' => date('Y-m-d H:i:s'),
                ]);
            } catch (\RuntimeException $e) {
                return $this->fail($e->getMessage(), 422);
            }
        }

        $workflow->transition($id, 'ditutup', $this->userId(), 'Permohonan resmi ditutup');

        (new LtkNotifikasiModel())->kirim(
            (int)$permohonan['klien_user_id'],
            'permohonan_selesai',
            'Permohonan Telaah Selesai',
            "Permohonan {$permohonan['kode']} telah selesai. Anda dapat mengunduh seluruh dokumen output.",
            $id
        );

        return $this->success(null, 'Permohonan berhasil diselesaikan dan ditutup.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/ltk/dokumen/download/:dokumenId/:format
    // format: docx | pdf
    // Serve generated Nota Dinas dari writable/ (tidak web-accessible).
    // ──────────────────────────────────────────────────────────────
    public function download(int $dokumenId, string $format)
    {
        $this->requirePermission('ltk.view');

        $format = strtolower($format);
        if (!in_array($format, ['docx', 'pdf'], true)) {
            return $this->fail('Format tidak valid.', 422);
        }

        $dokumen = (new DokumenOutputModel())->find($dokumenId);
        if (!$dokumen) return $this->fail('Dokumen tidak ditemukan.', 404);

        // Klien hanya boleh mengunduh dokumen miliknya
        if ($this->userRole() === 'klien') {
            $permohonan = (new PermohonanModel())->find((int)$dokumen['permohonan_id']);
            if (!$permohonan || (int)$permohonan['klien_user_id'] !== $this->userId()) {
                return $this->fail('Akses ditolak.', 403);
            }
        }

        $relPath = $format === 'pdf'
            ? ($dokumen['file_pdf_path']  ?? null)
            : ($dokumen['file_docx_path'] ?? null);

        if (!$relPath) return $this->fail('File ' . strtoupper($format) . ' tidak tersedia.', 404);

        // Sanitasi path: hanya izinkan file di dalam WRITEPATH/uploads/
        // Cegah path traversal (../../etc/passwd, dll.)
        $baseDir  = realpath(WRITEPATH . 'uploads');
        $absPath  = realpath(
            str_starts_with($relPath, '/')
                ? $relPath
                : WRITEPATH . 'uploads/' . $relPath
        );

        if (!$absPath || !str_starts_with($absPath, $baseDir . DIRECTORY_SEPARATOR)) {
            log_message('warning', "[LTK] Akses file ditolak (path traversal?): {$relPath} oleh user #{$this->userId()}");
            return $this->fail('File tidak ditemukan di server.', 404);
        }

        if (!file_exists($absPath)) {
            return $this->fail('File tidak ditemukan di server.', 404);
        }

        $mimeMap = [
            'pdf'  => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        $filename = basename($absPath);
        if (!str_ends_with($filename, '.' . $format)) {
            $filename .= '.' . $format;
        }
        // Sanitasi nama file — hanya karakter aman untuk Content-Disposition header
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $filename);

        return $this->response
            ->setHeader('Content-Type',        $mimeMap[$format])
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Content-Length',      (string)filesize($absPath))
            ->setHeader('Cache-Control',       'no-cache, must-revalidate')
            ->setBody(file_get_contents($absPath));
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Submit dokumen Policy Brief / Policy Paper berupa link cloud.
     * Validasi URL, simpan ke ltk_dokumen_output, lakukan transisi workflow.
     */
    private function _submitLinkDokumen(
        string $tipeDokumen,
        array  $statusDariYangDiizinkan,
        string $statusKe,
        string $pesan
    ) {
        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'permohonan_id'  => 'required|integer',
            'link_cloud'     => 'required|valid_url|max_length[2000]',
            'keterangan_link'=> 'permit_empty|max_length[255]',
        ];

        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $permohonanId = (int)$data['permohonan_id'];
        $permohonan   = (new PermohonanModel())->find($permohonanId);
        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);
        if ((int)$permohonan['pic_user_id'] !== $this->userId()) return $this->fail('Bukan tugas Anda.', 403);

        if (!in_array($permohonan['status'], $statusDariYangDiizinkan, true)) {
            return $this->fail("Status permohonan tidak memungkinkan submit {$tipeDokumen}.", 422);
        }

        // Simpan dokumen dengan link cloud
        $dokumenModel = new DokumenOutputModel();
        $dokumenModel->insert([
            'permohonan_id'   => $permohonanId,
            'tipe_dokumen'    => $tipeDokumen,
            'tgl_dokumen'     => date('Y-m-d'),
            'link_cloud'      => trim($data['link_cloud']),
            'keterangan_link' => $data['keterangan_link'] ?? null,
            'upload_manual'   => 1,
            'digenerate_oleh' => $this->userId(),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
        $newDokumenId = (int)$dokumenModel->insertID();

        // Transisi workflow
        $workflow = new WorkflowEngine();
        $workflow->transition($permohonanId, $statusKe, $this->userId(),
            ucwords(str_replace('_', ' ', $tipeDokumen)) . ' disubmit — link: ' . $data['link_cloud']
        );

        $this->auditLog("Submit {$tipeDokumen} link cloud permohonan #{$permohonanId}", 'ltk_dokumen_output', $newDokumenId);

        return $this->success(['dokumen_id' => $newDokumenId], $pesan, 201);
    }
}
