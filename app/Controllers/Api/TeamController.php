<?php

namespace App\Controllers\Api;

use App\Models\TeamModel;
use App\Models\TeamMemberModel;
use CodeIgniter\HTTP\ResponseInterface;

class TeamController extends BaseApiController
{
    private TeamModel $model;

    public function __construct()
    {
        $this->model = new TeamModel();
    }

    public function index(): ResponseInterface
    {
        $db   = \Config\Database::connect();
        $rows = $db->query("
            SELECT t.*, u.nama AS ketua_tim_nama,
                   COUNT(tm.id) AS jumlah_anggota
            FROM teams t
            LEFT JOIN users u ON u.id = t.ketua_tim_id
            LEFT JOIN team_members tm ON tm.team_id = t.id
            GROUP BY t.id
            ORDER BY t.nama_tim
        ")->getResultArray();

        return $this->success($rows);
    }

    public function show(int $id): ResponseInterface
    {
        $team = $this->model->select('teams.*, users.nama AS ketua_tim_nama')
                            ->join('users', 'users.id = teams.ketua_tim_id', 'left')
                            ->find($id);

        if (! $team) return $this->fail('Tim tidak ditemukan.', 404);

        $team['anggota'] = (new TeamMemberModel())->getByTeam($id);

        return $this->success($team);
    }

    public function create(): ResponseInterface
    {
        $this->requireRole(['direktur']);

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        if (empty($data['nama_tim']) || empty($data['ketua_tim_id'])) {
            return $this->fail('Nama tim dan ketua tim wajib diisi.', 422);
        }

        $this->model->insert(['nama_tim' => $data['nama_tim'], 'ketua_tim_id' => $data['ketua_tim_id']]);
        $newId = $this->model->insertID();

        // Ketua otomatis jadi anggota
        $db = \Config\Database::connect();
        $db->table('team_members')->ignore(true)->insert(['team_id' => $newId, 'user_id' => $data['ketua_tim_id'], 'created_at' => date('Y-m-d H:i:s')]);

        if (! empty($data['anggota']) && is_array($data['anggota'])) {
            foreach ($data['anggota'] as $uid) {
                $db->table('team_members')->ignore(true)->insert(['team_id' => $newId, 'user_id' => $uid, 'created_at' => date('Y-m-d H:i:s')]);
            }
        }

        $this->auditLog("Membuat tim: {$data['nama_tim']}", 'teams', $newId, null, $data);

        return $this->success(['id' => $newId], 'Tim berhasil dibuat.', 201);
    }

    public function update(int $id): ResponseInterface
    {
        $this->requireRole(['direktur']);

        $team = $this->model->find($id);
        if (! $team) return $this->fail('Tim tidak ditemukan.', 404);

        $data = $this->request->getJSON(true) ?? [];

        $db = \Config\Database::connect();

        if (! empty($data['nama_tim'])) {
            $this->model->update($id, ['nama_tim' => $data['nama_tim']]);
        }
        if (! empty($data['ketua_tim_id'])) {
            $this->model->update($id, ['ketua_tim_id' => $data['ketua_tim_id']]);
            $db->table('team_members')->ignore(true)->insert(['team_id' => $id, 'user_id' => $data['ketua_tim_id'], 'created_at' => date('Y-m-d H:i:s')]);
        }
        if (isset($data['anggota']) && is_array($data['anggota'])) {
            $db->table('team_members')->where('team_id', $id)->delete();
            $ketua = $data['ketua_tim_id'] ?? $team['ketua_tim_id'];
            $db->table('team_members')->ignore(true)->insert(['team_id' => $id, 'user_id' => $ketua, 'created_at' => date('Y-m-d H:i:s')]);
            foreach ($data['anggota'] as $uid) {
                $db->table('team_members')->ignore(true)->insert(['team_id' => $id, 'user_id' => $uid, 'created_at' => date('Y-m-d H:i:s')]);
            }
        }

        $this->auditLog("Mengubah tim: {$team['nama_tim']}", 'teams', $id, $team, $data);

        return $this->success(null, 'Tim berhasil diperbarui.');
    }

    public function delete(int $id): ResponseInterface
    {
        $this->requireRole(['direktur']);

        $team = $this->model->find($id);
        if (! $team) return $this->fail('Tim tidak ditemukan.', 404);

        $this->model->delete($id);
        $this->auditLog("Menghapus tim: {$team['nama_tim']}", 'teams', $id, $team, null);

        return $this->success(null, 'Tim berhasil dihapus.');
    }
}
