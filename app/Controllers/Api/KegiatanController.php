<?php

namespace App\Controllers\Api;

use App\Models\KegiatanModel;
use App\Models\NotifikasiModel;
use App\Models\TaskModel;
use App\Models\RealisasiAnggaranModel;
use App\Models\TeamMemberModel;
use CodeIgniter\HTTP\ResponseInterface;

class KegiatanController extends BaseApiController
{
    private KegiatanModel $model;

    public function __construct()
    {
        $this->model = new KegiatanModel();
    }

    public function index(): ResponseInterface
    {
        $rows = $this->model->getWithSummary($this->userId(), $this->userRole());

        return $this->success($rows);
    }

    public function show(int $id): ResponseInterface
    {
        $row = $this->model->getDetail($id);
        if (! $row) return $this->fail('Kegiatan tidak ditemukan.', 404);

        $taskModel = new TaskModel();
        $row['tasks'] = $taskModel->getByKegiatanWithSubtasks($id);

        $anggaranModel = new RealisasiAnggaranModel();
        $row['realisasi'] = $anggaranModel->getByKegiatan($id);

        $memberModel = new TeamMemberModel();
        $row['anggota'] = $memberModel->getByTeam((int) $row['team_id']);

        return $this->success($row);
    }

    public function create(): ResponseInterface
    {
        $this->requirePermission('kegiatan.create');

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $tipe = $data['tipe'] ?? 'on_budget';

        // Perencana anggaran hanya boleh membuat off-budget
        if ($this->userRole() === 'perencana_anggaran' && $tipe !== 'off_budget') {
            return $this->fail('Perencana anggaran hanya dapat membuat kegiatan off-budget.', 403);
        }

        if (! in_array($tipe, ['on_budget', 'off_budget'])) {
            return $this->fail("Tipe harus 'on_budget' atau 'off_budget'.", 422);
        }

        $rules = [
            'nama_kegiatan'   => 'required|max_length[200]',
            'tanggal_mulai'   => 'required|valid_date[Y-m-d]',
            'tanggal_selesai' => 'required|valid_date[Y-m-d]',
            'team_id'         => 'required|is_natural_no_zero',
            // pagu_anggaran sengaja tidak diwajibkan di create —
            // Perencana Anggaran mengatur pagu via menu Rincian Anggaran
        ];

        if (! $this->validate($rules)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $insertData = [
            'nama_kegiatan'   => $data['nama_kegiatan'],
            'deskripsi'       => $data['deskripsi'] ?? null,
            'tanggal_mulai'   => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'pagu_anggaran'   => 0,   // default 0; diisi oleh Perencana Anggaran
            'status'          => 'draft',
            'tipe'            => $tipe,
            'team_id'         => $data['team_id'],
            'created_by'      => $this->userId(),
        ];

        $this->model->insert($insertData);
        $newId = $this->model->insertID();

        $label = $tipe === 'off_budget' ? 'kegiatan off-budget' : 'kegiatan';
        $this->auditLog("Membuat {$label}: {$data['nama_kegiatan']}", 'kegiatan', $newId, null, $insertData);

        (new NotifikasiModel())->createForTeam(
            (int) $data['team_id'],
            'Kegiatan Baru',
            "Kegiatan '{$data['nama_kegiatan']}' telah dibuat.",
            'task_baru'
        );

        return $this->success(['id' => $newId], 'Kegiatan berhasil dibuat.', 201);
    }

    public function update(int $id): ResponseInterface
    {
        $this->requirePermission('kegiatan.edit');

        $kegiatan = $this->model->find($id);
        if (! $kegiatan) return $this->fail('Kegiatan tidak ditemukan.', 404);

        $data    = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $allowed = ['nama_kegiatan', 'deskripsi', 'tanggal_mulai', 'tanggal_selesai', 'status', 'ro_id'];

        if (in_array($this->userRole(), ['direktur', 'perencana_anggaran', 'admin'])) {
            $allowed[] = 'pagu_anggaran';
        }
        if (in_array($this->userRole(), ['direktur', 'admin'])) {
            $allowed[] = 'team_id';
        }

        $updateData = array_intersect_key($data, array_flip($allowed));
        if (empty($updateData)) return $this->fail('Tidak ada data yang dapat diubah.', 422);

        // ro_id = null harus ditulis secara eksplisit (CI4 model skip null by default)
        if (array_key_exists('ro_id', $updateData) && $updateData['ro_id'] === null) {
            $this->db->table('kegiatan')->where('id', $id)->update(['ro_id' => null]);
            unset($updateData['ro_id']);
        }
        if (! empty($updateData)) {
            $this->model->update($id, $updateData);
        }
        $this->auditLog("Mengubah kegiatan: {$kegiatan['nama_kegiatan']}", 'kegiatan', $id, $kegiatan, $updateData);

        return $this->success(null, 'Kegiatan berhasil diperbarui.');
    }

    /**
     * PATCH /api/v1/kegiatan/:id/convert-to-on-budget
     * Ubah tipe kegiatan dari off_budget → on_budget.
     * Hanya direktur yang boleh melakukan konversi ini.
     */
    public function convertToOnBudget(int $id): ResponseInterface
    {
        $this->requirePermission('kegiatan.edit');

        $kegiatan = $this->model->find($id);
        if (! $kegiatan) return $this->fail('Kegiatan tidak ditemukan.', 404);
        if ($kegiatan['tipe'] !== 'off_budget') {
            return $this->fail('Kegiatan ini sudah bertipe on-budget.', 422);
        }

        // Pastikan tidak ada kegiatan lain yang memakai kegiatan ini sebagai sumber off-budget
        $db    = \Config\Database::connect();
        $countOB = $db->query(
            "SELECT COUNT(*) AS c FROM off_budget_sumber WHERE sumber_id = ?", [$id]
        )->getFirstRow('array')['c'] ?? 0;
        if ($countOB > 0) {
            return $this->fail(
                "Tidak dapat mengkonversi: kegiatan ini digunakan sebagai sumber oleh {$countOB} kegiatan off-budget.",
                422
            );
        }

        $this->model->update($id, ['tipe' => 'on_budget']);
        $this->auditLog(
            "Mengubah tipe kegiatan ke on-budget: {$kegiatan['nama_kegiatan']}",
            'kegiatan', $id, $kegiatan, ['tipe' => 'on_budget']
        );

        return $this->success(null, 'Kegiatan berhasil diubah menjadi On Budget.');
    }

    public function delete(int $id): ResponseInterface
    {
        $this->requirePermission('kegiatan.delete');

        $kegiatan = $this->model->find($id);
        if (! $kegiatan) return $this->fail('Kegiatan tidak ditemukan.', 404);

        // Cek apakah ada off-budget yang bergantung (via tabel pivot)
        $db = \Config\Database::connect();
        $countOB = $db->query("SELECT COUNT(*) AS c FROM off_budget_sumber WHERE sumber_id = ?", [$id])
                      ->getFirstRow('array')['c'] ?? 0;
        if ($countOB > 0) {
            return $this->fail("Tidak dapat menghapus: kegiatan ini digunakan sebagai sumber oleh {$countOB} kegiatan off-budget.", 422);
        }

        $this->model->delete($id);
        $this->auditLog("Menghapus kegiatan: {$kegiatan['nama_kegiatan']}", 'kegiatan', $id, $kegiatan, null);

        return $this->success(null, 'Kegiatan berhasil dihapus.');
    }

    /** Endpoint khusus: list kegiatan on_budget untuk dropdown sumber */
    public function onBudgetList(): ResponseInterface
    {
        $rows = $this->model->getOnBudgetList();
        return $this->success($rows);
    }
}
