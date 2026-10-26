<?php

namespace App\Controllers\Api;

use App\Models\KegiatanModel;
use App\Models\NotifikasiModel;
use App\Models\TaskModel;
use App\Models\TaskAttachmentModel;
use CodeIgniter\HTTP\ResponseInterface;

class TaskController extends BaseApiController
{
    private TaskModel $model;

    public function __construct()
    {
        $this->model = new TaskModel();
    }

    public function index(int $kegiatanId): ResponseInterface
    {
        return $this->success($this->model->getByKegiatanWithSubtasks($kegiatanId));
    }

    public function show(int $kegiatanId, int $id): ResponseInterface
    {
        $task = $this->model->select('tasks.*, users.nama AS pic_nama')
                            ->join('users', 'users.id = tasks.pic_id', 'left')
                            ->find($id);

        if (! $task || (int) $task['kegiatan_id'] !== $kegiatanId) {
            return $this->fail('Task tidak ditemukan.', 404);
        }

        $task['lampiran'] = (new TaskAttachmentModel())->getByTask($id);

        return $this->success($task);
    }

    public function create(int $kegiatanId): ResponseInterface
    {
        $this->requireRole(['direktur', 'ketua_tim', 'anggota_tim']);

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        if (empty($data['nama_task'])) {
            return $this->fail('Nama task wajib diisi.', 422);
        }

        $parentTaskId = isset($data['parent_task_id']) ? (int) $data['parent_task_id'] : null;

        $insertData = [
            'kegiatan_id'    => $kegiatanId,
            'parent_task_id' => $parentTaskId,
            'nama_task'      => $data['nama_task'],
            'pic_id'         => $data['pic_id'] ?? null,
            'status'         => 'belum_dimulai',
            'target_date'    => $data['target_date'] ?? null,
            'catatan'        => $data['catatan'] ?? null,
            'urutan'         => $this->model->getNextUrutan($kegiatanId, $parentTaskId),
        ];

        $this->model->insert($insertData);
        $newId = $this->model->insertID();

        $this->auditLog("Membuat task: {$data['nama_task']}", 'tasks', $newId, null, $insertData);

        // Jika ini subtask baru, sync status parent (subtask baru = belum_dimulai,
        // tapi parent mungkin perlu berubah jika sebelumnya 'selesai')
        if ($parentTaskId) {
            $this->_syncParentTaskStatus($parentTaskId, $kegiatanId);
        }

        if (! empty($data['pic_id'])) {
            (new NotifikasiModel())->insert([
                'user_id'    => $data['pic_id'],
                'judul'      => 'Task Baru Ditugaskan',
                'pesan'      => "Anda ditugaskan untuk task: {$data['nama_task']}",
                'tipe'       => 'task_baru',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->success(['id' => $newId], 'Task berhasil dibuat.', 201);
    }

    public function update(int $kegiatanId, int $id): ResponseInterface
    {
        $task = $this->model->find($id);
        if (! $task || (int) $task['kegiatan_id'] !== $kegiatanId) {
            return $this->fail('Task tidak ditemukan.', 404);
        }

        $data = $this->request->getJSON(true) ?? [];

        // anggota_tim dapat mengubah semua field kecuali urutan dan pic_id
        $allowed = in_array($this->userRole(), ['direktur', 'ketua_tim'])
            ? ['nama_task', 'pic_id', 'status', 'target_date', 'catatan', 'urutan']
            : ['nama_task', 'status', 'target_date', 'catatan'];

        $updateData = array_intersect_key($data, array_flip($allowed));

        // Catat started_at saat pertama kali berjalan
        if (isset($updateData['status']) && $updateData['status'] === 'sedang_berjalan'
            && empty($task['started_at'])) {
            $updateData['started_at'] = date('Y-m-d H:i:s');
        }
        // Catat completed_date saat selesai
        if (isset($updateData['status']) && $updateData['status'] === 'selesai') {
            $updateData['completed_date'] = date('Y-m-d');
            // Jika belum pernah started, set started_at juga
            if (empty($task['started_at'])) {
                $updateData['started_at'] = date('Y-m-d H:i:s');
            }
        }
        // Reset started_at & completed_date jika dikembalikan ke belum_dimulai
        if (isset($updateData['status']) && $updateData['status'] === 'belum_dimulai') {
            $updateData['started_at']     = null;
            $updateData['completed_date'] = null;
        }

        if (empty($updateData)) return $this->fail('Tidak ada data yang dapat diubah.', 422);

        $this->model->update($id, $updateData);
        $this->auditLog("Mengubah task: {$task['nama_task']}", 'tasks', $id, $task, $updateData);

        // ── Jika ini adalah SUBTASK (punya parent), perbarui status parent secara otomatis ──
        // Kemudian lewati cek kegiatan di sini — parent yang baru di-sync akan
        // merepresentasikan progres subtask dengan benar saat getProgress dipanggil.
        if (isset($updateData['status']) && ! empty($task['parent_task_id'])) {
            $this->_syncParentTaskStatus((int) $task['parent_task_id'], $kegiatanId);
        }

        // Notifikasi & cek auto-selesai kegiatan — HANYA untuk task induk (bukan subtask)
        if (isset($updateData['status']) && $updateData['status'] !== $task['status']
            && empty($task['parent_task_id'])) {
            $kegiatan = (new KegiatanModel())->find($kegiatanId);
            if ($kegiatan) {
                $statusLabel = match($updateData['status']) {
                    'selesai'         => 'Selesai',
                    'sedang_berjalan' => 'Sedang Berjalan',
                    default           => 'Belum Dimulai',
                };
                (new NotifikasiModel())->createForTeam(
                    (int) $kegiatan['team_id'],
                    'Status Sub-Kegiatan Diperbarui',
                    "Sub-kegiatan '{$task['nama_task']}' berubah menjadi: {$statusLabel}",
                    'task_update',
                    $this->userId()
                );

                // Cek apakah kegiatan 100% selesai → otomatis ubah status kegiatan
                $progress = $this->model->getProgress($kegiatanId);
                if ($progress >= 100 && $kegiatan['status'] !== 'selesai') {
                    (new KegiatanModel())->update($kegiatanId, ['status' => 'selesai']);
                    $this->auditLog(
                        "Status kegiatan '{$kegiatan['nama_kegiatan']}' otomatis berubah menjadi Selesai (semua task selesai)",
                        'kegiatan', $kegiatanId,
                        ['status' => $kegiatan['status']],
                        ['status' => 'selesai']
                    );
                    (new NotifikasiModel())->createForTeam(
                        (int) $kegiatan['team_id'],
                        'Kegiatan Selesai 100%',
                        "Semua sub-kegiatan pada kegiatan '{$kegiatan['nama_kegiatan']}' telah selesai. Status kegiatan diubah menjadi Selesai.",
                        'selesai'
                    );
                } elseif ($progress < 100 && $kegiatan['status'] === 'selesai') {
                    // Jika ada task yang di-uncheck kembali, kembalikan status ke berjalan
                    (new KegiatanModel())->update($kegiatanId, ['status' => 'berjalan']);
                    $this->auditLog(
                        "Status kegiatan '{$kegiatan['nama_kegiatan']}' dikembalikan ke Berjalan (task di-uncheck)",
                        'kegiatan', $kegiatanId,
                        ['status' => 'selesai'],
                        ['status' => 'berjalan']
                    );
                }
            }
        }

        return $this->success(null, 'Task berhasil diperbarui.');
    }

    public function delete(int $kegiatanId, int $id): ResponseInterface
    {
        $this->requireRole(['direktur', 'ketua_tim', 'anggota_tim']);

        $task = $this->model->find($id);
        if (! $task || (int) $task['kegiatan_id'] !== $kegiatanId) {
            return $this->fail('Task tidak ditemukan.', 404);
        }

        $parentTaskId = $task['parent_task_id'] ? (int)$task['parent_task_id'] : null;

        $this->model->delete($id);
        $this->auditLog("Menghapus task: {$task['nama_task']}", 'tasks', $id, $task, null);

        // Jika subtask dihapus, re-sync status parent task
        if ($parentTaskId) {
            $this->_syncParentTaskStatus($parentTaskId, $kegiatanId);
        }

        return $this->success(null, 'Task berhasil dihapus.');
    }

    // ─────────────────────────────────────────────────────────────────
    // Sinkronisasi status task induk berdasarkan status subtask-nya.
    //
    // Aturan:
    //  - Semua subtask selesai              → parent = selesai
    //  - Ada ≥1 subtask sedang_berjalan
    //    atau campuran selesai+belum_dimulai → parent = sedang_berjalan
    //  - Semua subtask belum_dimulai        → parent = belum_dimulai
    // ─────────────────────────────────────────────────────────────────
    private function _syncParentTaskStatus(int $parentId, int $kegiatanId): void
    {
        $parent   = $this->model->find($parentId);
        if (! $parent) return;

        // Ambil semua subtask milik parent ini
        $subtasks = $this->model->where('parent_task_id', $parentId)
                                ->where('kegiatan_id', $kegiatanId)
                                ->findAll();

        if (empty($subtasks)) return;

        $total    = count($subtasks);
        $selesai  = count(array_filter($subtasks, fn($s) => $s['status'] === 'selesai'));
        $berjalan = count(array_filter($subtasks, fn($s) => $s['status'] === 'sedang_berjalan'));

        // Tentukan status baru untuk parent
        $newStatus = match(true) {
            $selesai === $total                  => 'selesai',
            $berjalan > 0 || $selesai > 0        => 'sedang_berjalan',
            default                              => 'belum_dimulai',
        };

        if ($newStatus === $parent['status']) return; // tidak ada perubahan

        $parentUpdate = ['status' => $newStatus];

        // Atur timestamps parent ikut logika yg sama dengan update biasa
        if ($newStatus === 'sedang_berjalan' && empty($parent['started_at'])) {
            $parentUpdate['started_at'] = date('Y-m-d H:i:s');
        }
        if ($newStatus === 'selesai') {
            $parentUpdate['completed_date'] = date('Y-m-d');
            if (empty($parent['started_at'])) {
                $parentUpdate['started_at'] = date('Y-m-d H:i:s');
            }
        }
        if ($newStatus === 'belum_dimulai') {
            $parentUpdate['started_at']     = null;
            $parentUpdate['completed_date'] = null;
        }

        $this->model->update($parentId, $parentUpdate);
        $this->auditLog(
            "Status task induk '{$parent['nama_task']}' otomatis diperbarui ke '{$newStatus}' karena perubahan subtask",
            'tasks', $parentId, ['status' => $parent['status']], $parentUpdate
        );

        // Setelah parent di-sync, periksa apakah status kegiatan perlu diperbarui
        $kegModel = new KegiatanModel();
        $kegiatan = $kegModel->find($kegiatanId);
        if ($kegiatan) {
            $progress = $this->model->getProgress($kegiatanId);
            if ($progress >= 100 && $kegiatan['status'] !== 'selesai') {
                $kegModel->update($kegiatanId, ['status' => 'selesai']);
                $this->auditLog(
                    "Status kegiatan '{$kegiatan['nama_kegiatan']}' otomatis berubah menjadi Selesai (semua task selesai)",
                    'kegiatan', $kegiatanId,
                    ['status' => $kegiatan['status']],
                    ['status' => 'selesai']
                );
            } elseif ($progress < 100 && $kegiatan['status'] === 'selesai') {
                $kegModel->update($kegiatanId, ['status' => 'berjalan']);
                $this->auditLog(
                    "Status kegiatan '{$kegiatan['nama_kegiatan']}' dikembalikan ke Berjalan (komponen di-uncheck)",
                    'kegiatan', $kegiatanId,
                    ['status' => 'selesai'],
                    ['status' => 'berjalan']
                );
            }
        }
    }
}
