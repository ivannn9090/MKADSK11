<?php

namespace App\Controllers\Api\Ltk;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Ltk\NotaDinasGenerator;
use App\Libraries\Ltk\WorkflowEngine;
use App\Models\Ltk\FormFieldModel;
use App\Models\Ltk\JawabanFieldModel;
use App\Models\Ltk\KlienProfilModel;
use App\Models\Ltk\PermohonanModel;

/**
 * PermohonanController — CRUD dan aksi utama permohonan telaah.
 */
class PermohonanController extends BaseApiController
{
    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/ltk/permohonan
    // ──────────────────────────────────────────────────────────────
    public function index()
    {
        $this->requirePermission('ltk.view');

        $filter = [
            'status'        => $this->request->getGet('status'),
            'jenis_antrian' => $this->request->getGet('jenis_antrian'),
            'pic_user_id'   => $this->request->getGet('pic_user_id'),
            'is_sla_breach' => $this->request->getGet('sla_breach'),
            'q'             => trim($this->request->getGet('q') ?? ''),
        ];

        // PIC hanya melihat yang ditugaskan kepadanya
        if ($this->userRole() === 'pic_telaah') {
            $filter['pic_user_id'] = $this->userId();
        }

        $items = (new PermohonanModel())->getListLengkap(array_filter($filter));
        return $this->success(['items' => $items]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/ltk/permohonan/saya  (Klien)
    // ──────────────────────────────────────────────────────────────
    public function saya()
    {
        $this->requireRole(['klien']);

        $items = (new PermohonanModel())->getListLengkap(['klien_user_id' => $this->userId()]);
        return $this->success(['items' => $items]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/ltk/permohonan/:id
    // ──────────────────────────────────────────────────────────────
    public function show(int $id)
    {
        $model       = new PermohonanModel();
        $permohonan  = $model->find($id);
        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);

        // Klien hanya boleh lihat miliknya
        if ($this->userRole() === 'klien' && (int)$permohonan['klien_user_id'] !== $this->userId()) {
            return $this->fail('Akses ditolak.', 403);
        }

        $permohonan['jawaban'] = (new JawabanFieldModel())->getByPermohonan($id);
        return $this->success($permohonan);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/ltk/permohonan
    // Klien submit permohonan baru
    // ──────────────────────────────────────────────────────────────
    public function create()
    {
        $this->requireRole(['klien']);

        // Pastikan profil klien sudah terverifikasi
        $profil = (new KlienProfilModel())->getByUserId($this->userId());
        if (!$profil || !$profil['is_verified']) {
            return $this->fail('Akun Anda belum diverifikasi oleh TU. Silakan hubungi TU.', 403);
        }

        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'judul'            => 'required|max_length[500]',
            'form_template_id' => 'required|integer',
            'jawaban'          => 'required',
        ];

        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $fieldModel = new FormFieldModel();
        $fields     = $fieldModel->getByTemplate((int)$data['form_template_id']);
        $jawaban    = (array)($data['jawaban'] ?? []);

        // Validasi setiap field wajib; untuk link_cloud validasi format URL
        foreach ($fields as $field) {
            $nilai = $jawaban[$field['id']] ?? null;

            if ($field['is_required'] && empty($nilai)) {
                return $this->fail("Field \"{$field['label']}\" wajib diisi.", 422);
            }

            if ($field['tipe'] === 'link_cloud' && !empty($nilai)) {
                if (!filter_var($nilai, FILTER_VALIDATE_URL)) {
                    return $this->fail("Field \"{$field['label']}\" harus berupa URL yang valid (contoh: https://drive.google.com/...).", 422);
                }
            }
        }

        $model = new PermohonanModel();
        $kode  = $model->generateKode();

        $model->insert([
            'kode'             => $kode,
            'klien_user_id'    => $this->userId(),
            'unit_kerja'       => $profil['unit_kerja'],
            'judul'            => $data['judul'],
            'deskripsi'        => $data['deskripsi'] ?? null,
            'form_template_id' => (int)$data['form_template_id'],
            'status'           => 'draft',
            'created_by'       => $this->userId(),
        ]);
        $newId = (int)$model->insertID();

        // Simpan jawaban (teruskan $fields agar insertBulk tahu tipe per field)
        (new JawabanFieldModel())->insertBulk($newId, $jawaban, $fields);

        // Transisi ke menunggu_srikandi
        $workflow = new WorkflowEngine();
        $workflow->transition($newId, 'menunggu_srikandi', $this->userId(), 'Formulir disubmit oleh Klien');

        // ── Petakan jawaban form ke variabel Nota Dinas Pengajuan ──────
        // Ambil nilai dari jawaban berdasarkan field_name
        $jawabanByName = [];
        foreach ($fields as $field) {
            $jawabanByName[$field['field_name']] = $jawaban[$field['id']] ?? '';
        }

        $tglNd = !empty($jawabanByName['tanggal_nd_pengusul'])
            ? (new NotaDinasGenerator())->formatTanggalIndonesia($jawabanByName['tanggal_nd_pengusul'])
            : (new NotaDinasGenerator())->formatTanggalIndonesia(date('Y-m-d'));

        // Generate Nota Dinas Pengajuan dari template Tata Naskah Dinas
        $ndError = null;
        try {
            $gen    = new NotaDinasGenerator();
            $result = $gen->generate($newId, 'nota_dinas_pengajuan', [
                // Header surat
                'nomor_naskah'    => $jawabanByName['nomor_nd_pengusul'] ?? '',
                'yth'             => 'Direktur Sistem Kearsipan',
                'dari'            => trim(
                                       ($jawabanByName['jabatan_pejabat_pengusul'] ?? '') . ' ' .
                                       ($profil['unit_kerja'] ?? '')
                                    ),
                'hal'             => $data['judul'],
                'tanggal'         => $tglNd,
                'lampiran'        => '1 (satu) berkas',
                // Isi dokumen
                'jenis_dokumen'   => $jawabanByName['jenis_kajian'] ?? '',
                'judul_dokumen'   => $data['judul'],
                'uraian_tambahan' => $jawabanByName['uraian_permohonan'] ?? '',
                // Penandatangan — dari jawaban form
                'jabatan_pengirim'=> $jawabanByName['jabatan_pejabat_pengusul'] ?? ($profil['jabatan'] ?? ''),
                'nama_pengirim'   => $jawabanByName['nama_pejabat_pengusul'] ?? ($this->jwtPayload['nama'] ?? ''),
            ], $this->userId());
        } catch (\Throwable $e) {
            $ndError = $e->getMessage();
            log_message('warning', '[LTK] Generate ND Pengajuan gagal: ' . $e->getMessage());
        }

        $this->auditLog("Submit permohonan telaah: {$kode}", 'ltk_permohonan', $newId);

        $responseData = ['id' => $newId, 'kode' => $kode];
        if ($ndError) {
            $responseData['nd_warning'] = 'Nota Dinas belum dapat digenerate: ' . $ndError;
        }

        return $this->success($responseData, 'Permohonan berhasil disubmit.', 201);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/ltk/permohonan/tu-input
    // TU input permohonan langsung dari NODIN SRIKANDI masuk
    // — skip alur klien, langsung masuk_antrian
    // ──────────────────────────────────────────────────────────────
    public function tuInput()
    {
        $this->requirePermission('ltk.input');

        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'judul'         => 'required|max_length[500]',
            'unit_kerja'    => 'required|max_length[200]',
            'nama_pengusul' => 'required|max_length[200]',
            'nodin_srikandi'=> 'required|max_length[100]',
            'tgl_nota_dinas_masuk' => 'required|valid_date',
            'jenis_antrian' => 'permit_empty|in_list[regular,prioritas]',
        ];

        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $model = new PermohonanModel();
        $kode  = $model->generateKode();

        $model->insert([
            'kode'                  => $kode,
            'sumber_permohonan'     => 'tu_input',
            'klien_user_id'         => null,
            'unit_kerja'            => $data['unit_kerja'],
            'nama_pengusul'         => $data['nama_pengusul'],
            'nodin_srikandi'        => $data['nodin_srikandi'],
            'judul'                 => $data['judul'],
            'deskripsi'             => $data['deskripsi'] ?? null,
            'form_template_id'      => null,
            'nomor_nota_dinas_masuk'=> $data['nodin_srikandi'],
            'tgl_nota_dinas_masuk'  => $data['tgl_nota_dinas_masuk'],
            'catatan_tu'            => $data['catatan_tu'] ?? null,
            'status'                => 'draft',
            'created_by'            => $this->userId(),
        ]);
        $newId = (int)$model->insertID();

        // Langsung masuk antrian — skip alur klien
        $workflow = new WorkflowEngine();
        $workflow->transition($newId, 'masuk_antrian', $this->userId(),
            'Permohonan diinput langsung oleh TU berdasarkan NODIN SRIKANDI: ' . $data['nodin_srikandi']);

        // Set jenis_antrian jika dikirim
        if (!empty($data['jenis_antrian'])) {
            $model->update($newId, ['jenis_antrian' => $data['jenis_antrian']]);
        }

        $this->auditLog("TU input permohonan telaah dari SRIKANDI: {$kode}", 'ltk_permohonan', $newId);

        return $this->success(['id' => $newId, 'kode' => $kode], 'Permohonan berhasil diinput dan masuk antrian.', 201);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/ltk/permohonan/:id/konfirmasi-srikandi
    // Klien konfirmasi bahwa Nota Dinas sudah dikirim via SRIKANDI
    // ──────────────────────────────────────────────────────────────
    public function konfirmasiSrikandi(int $id)
    {
        $this->requireRole(['klien']);

        $model      = new PermohonanModel();
        $permohonan = $model->find($id);
        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);
        if ((int)$permohonan['klien_user_id'] !== $this->userId()) return $this->fail('Akses ditolak.', 403);
        if ($permohonan['status'] !== 'menunggu_srikandi') return $this->fail('Status tidak sesuai.', 422);

        $workflow = new WorkflowEngine();
        $workflow->transition($id, 'menunggu_verifikasi_tu', $this->userId(), 'Klien mengkonfirmasi Nota Dinas telah dikirim via SRIKANDI');

        return $this->success(null, 'Konfirmasi berhasil. Menunggu verifikasi TU.');
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/ltk/permohonan/:id/verifikasi
    // TU melakukan verifikasi administrasi
    // ──────────────────────────────────────────────────────────────
    public function verifikasi(int $id)
    {
        $this->requirePermission('ltk.verifikasi');

        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'nomor_nota_dinas_masuk' => 'required|max_length[100]',
            'tgl_nota_dinas_masuk'   => 'required|valid_date',
            'catatan_tu'             => 'permit_empty|max_length[1000]',
        ];

        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $model      = new PermohonanModel();
        $permohonan = $model->find($id);
        if (!$permohonan) return $this->fail('Permohonan tidak ditemukan.', 404);

        $workflow = new WorkflowEngine();
        $workflow->transition($id, 'terverifikasi', $this->userId(),
            'Verifikasi TU — Nomor ND: ' . $data['nomor_nota_dinas_masuk'],
            [
                'nomor_nota_dinas_masuk' => $data['nomor_nota_dinas_masuk'],
                'tgl_nota_dinas_masuk'   => $data['tgl_nota_dinas_masuk'],
                'catatan_tu'             => $data['catatan_tu'] ?? null,
            ]
        );

        // Pindahkan ke antrian
        $workflow->transition($id, 'masuk_antrian', $this->userId(), 'Permohonan masuk antrian telaah');

        // Notifikasi Klien
        $workflow->sendNotif($id, 'terverifikasi', [(int)$permohonan['klien_user_id']],
            'Permohonan Anda telah diverifikasi oleh TU dan masuk antrian telaah.');

        $this->auditLog("Verifikasi permohonan #{$id}", 'ltk_permohonan', $id);
        return $this->success(null, 'Permohonan berhasil diverifikasi dan masuk antrian.');
    }
}
