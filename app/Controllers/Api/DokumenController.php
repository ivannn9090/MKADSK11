<?php

namespace App\Controllers\Api;

use App\Models\DokumenModel;
use App\Models\TaskAttachmentModel;
use CodeIgniter\HTTP\ResponseInterface;

class DokumenController extends BaseApiController
{
    public function index(int $kegiatanId): ResponseInterface
    {
        return $this->success((new DokumenModel())->getByKegiatan($kegiatanId));
    }

    /**
     * POST /api/v1/kegiatan/:id/dokumen
     * Simpan dokumen sebagai link OneDrive (tidak ada upload file fisik).
     * Body JSON: { nama_file, file_url }
     * - nama_file : nama tampilan dokumen
     * - file_url  : link OneDrive (wajib mengandung onedrive.live.com atau sharepoint.com)
     */
    public function upload(int $kegiatanId): ResponseInterface
    {
        $this->requireRole(['direktur', 'ketua_tim']);

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $namaFile = trim($data['nama_file'] ?? '');
        $fileUrl  = trim($data['file_url']  ?? '');

        if ($namaFile === '') {
            return $this->fail('Nama dokumen wajib diisi.', 422);
        }
        if ($fileUrl === '') {
            return $this->fail('Link OneDrive wajib diisi.', 422);
        }
        if (! $this->isOneDriveUrl($fileUrl)) {
            return $this->fail(
                'Link harus menggunakan OneDrive (onedrive.live.com, 1drv.ms, atau sharepoint.com).',
                422
            );
        }

        $model = new DokumenModel();
        $model->insert([
            'kegiatan_id' => $kegiatanId,
            'nama_file'   => $namaFile,
            'jenis_file'  => 'onedrive',
            'file_url'    => $fileUrl,
            'uploaded_by' => $this->userId(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $newId = $model->insertID();

        $this->auditLog(
            "Tambah dokumen: {$namaFile}",
            'dokumen', $newId, null, ['nama_file' => $namaFile, 'file_url' => $fileUrl]
        );

        return $this->success(['id' => $newId], 'Dokumen berhasil ditambahkan.', 201);
    }

    public function delete(int $kegiatanId, int $id): ResponseInterface
    {
        $this->requireRole(['direktur', 'ketua_tim']);

        $doc = (new DokumenModel())->find($id);
        if (! $doc || (int) $doc['kegiatan_id'] !== $kegiatanId) {
            return $this->fail('Dokumen tidak ditemukan.', 404);
        }

        (new DokumenModel())->delete($id);
        $this->auditLog("Menghapus dokumen: {$doc['nama_file']}", 'dokumen', $id, $doc, null);

        return $this->success(null, 'Dokumen berhasil dihapus.');
    }

    // Task Attachments — tidak berubah
    public function uploadTaskAttachment(int $kegiatanId, int $taskId): ResponseInterface
    {
        $this->requireRole(['direktur', 'ketua_tim', 'anggota_tim']);

        $data     = $this->request->getJSON(true) ?? [];
        $namaFile = trim($data['nama_file'] ?? '');
        $fileUrl  = trim($data['file_url']  ?? '');

        if (empty($namaFile)) {
            return $this->fail('Nama / keterangan bukti dukung wajib diisi.', 422);
        }
        if (empty($fileUrl) || !filter_var($fileUrl, FILTER_VALIDATE_URL)) {
            return $this->fail('URL bukti dukung tidak valid.', 422);
        }

        $model = new TaskAttachmentModel();
        $model->insert([
            'task_id'     => $taskId,
            'nama_file'   => $namaFile,
            'file_url'    => $fileUrl,
            'uploaded_by' => $this->userId(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $newId    = $model->insertID();
        $lampiran = $model->select('task_attachments.*, users.nama AS uploaded_by_nama')
                          ->join('users', 'users.id = task_attachments.uploaded_by', 'left')
                          ->find($newId);

        $this->auditLog("Tambah bukti dukung task #{$taskId}: {$namaFile}", 'task_attachments', $newId);
        return $this->success($lampiran, 'Bukti dukung berhasil ditambahkan.', 201);
    }

    // ──────────────────────────────────────────────────────────────
    // DELETE /api/v1/kegiatan/:kegiatanId/tasks/:taskId/attachments/:attId
    // Hapus satu lampiran task/subtask
    // ──────────────────────────────────────────────────────────────
    public function deleteTaskAttachment(int $kegiatanId, int $taskId, int $attId): ResponseInterface
    {
        $this->requireRole(['direktur', 'ketua_tim', 'anggota_tim']);

        $model = new TaskAttachmentModel();
        $att   = $model->find($attId);

        if (!$att || (int)$att['task_id'] !== $taskId) {
            return $this->fail('Lampiran tidak ditemukan.', 404);
        }

        // Hapus file fisik jika ada
        $filePath = FCPATH . 'uploads/attachments/' . basename($att['file_url'] ?? '');
        if (is_file($filePath)) @unlink($filePath);

        $model->delete($attId);
        $this->auditLog("Hapus lampiran task #{$taskId}: {$att['nama_file']}", 'task_attachments', $attId);

        return $this->success(null, 'Lampiran berhasil dihapus.');
    }

    // ----------------------------------------------------------------
    // Helper: cek apakah URL adalah OneDrive / SharePoint
    // ----------------------------------------------------------------
    private function isOneDriveUrl(string $url): bool
    {
        $allowed = ['onedrive.live.com', '1drv.ms', 'sharepoint.com', 'd.docs.live.net'];
        $host    = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        foreach ($allowed as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }
        return false;
    }
}
