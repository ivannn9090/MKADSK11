<?php

namespace App\Models;

use CodeIgniter\Model;

class TaskModel extends Model
{
    protected $table         = 'tasks';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['kegiatan_id', 'parent_task_id', 'nama_task', 'pic_id', 'status', 'target_date', 'started_at', 'completed_date', 'catatan', 'urutan'];
    protected $useTimestamps = true;

    /**
     * Ambil semua task INDUK beserta subtask dan lampiran masing-masing.
     * Lampiran di-load sekaligus (batch) — bukan N+1 query.
     */
    public function getByKegiatanWithSubtasks(int $kegiatanId): array
    {
        // 1. Ambil semua task (parent + subtask)
        $all = $this->select('tasks.*, users.nama AS pic_nama')
                    ->join('users', 'users.id = tasks.pic_id', 'left')
                    ->where('tasks.kegiatan_id', $kegiatanId)
                    ->orderBy('tasks.urutan', 'ASC')
                    ->orderBy('tasks.id', 'ASC')
                    ->findAll();

        if (empty($all)) return [];

        // 2. Ambil semua lampiran sekaligus untuk kegiatan ini (1 query)
        $taskIds    = array_column($all, 'id');
        $allLampiran = $this->db->table('task_attachments ta')
            ->select('ta.*, u.nama AS uploaded_by_nama')
            ->join('users u', 'u.id = ta.uploaded_by', 'left')
            ->whereIn('ta.task_id', $taskIds)
            ->orderBy('ta.created_at', 'ASC')
            ->get()->getResultArray();

        // Index lampiran per task_id
        $lampiranByTask = [];
        foreach ($allLampiran as $l) {
            $lampiranByTask[(int)$l['task_id']][] = $l;
        }

        // 3. Pisahkan parent dan subtask, gabungkan lampiran
        $parents  = [];
        $children = [];
        foreach ($all as $t) {
            $t['lampiran'] = $lampiranByTask[(int)$t['id']] ?? [];
            if ($t['parent_task_id'] === null) {
                $t['subtasks'] = [];
                $parents[$t['id']] = $t;
            } else {
                $children[] = $t;
            }
        }
        foreach ($children as $c) {
            if (isset($parents[$c['parent_task_id']])) {
                $parents[$c['parent_task_id']]['subtasks'][] = $c;
            }
        }

        return array_values($parents);
    }

    /** Untuk backward-compat (flat list, dipakai internal) */
    public function getByKegiatan(int $kegiatanId): array
    {
        return $this->select('tasks.*, users.nama AS pic_nama')
                    ->join('users', 'users.id = tasks.pic_id', 'left')
                    ->where('tasks.kegiatan_id', $kegiatanId)
                    ->orderBy('tasks.urutan', 'ASC')
                    ->orderBy('tasks.id', 'ASC')
                    ->findAll();
    }

    /**
     * Progress kegiatan = rata-rata progress setiap task induk.
     *
     * Progress setiap task induk dihitung:
     *   - Jika task punya subtask  → rata-rata % subtask
     *                                (selesai=100, sedang_berjalan=50, belum_dimulai=0)
     *   - Jika task tidak punya subtask → status task itu sendiri
     *                                (selesai=100, sedang_berjalan=50, belum_dimulai=0)
     *
     * Contoh: 2 task, Task A (2 subtask: 1 selesai 1 belum) → 50%,
     *         Task B selesai → 100%. Kegiatan = (50+100)/2 = 75%
     */
    public function getProgress(int $kegiatanId): float
    {
        // Ambil semua task milik kegiatan ini (parent + subtask sekaligus)
        $all = $this->db->table('tasks')
                        ->select('id, parent_task_id, status')
                        ->where('kegiatan_id', $kegiatanId)
                        ->get()->getResultArray();

        if (empty($all)) return 0.0;

        // Pisahkan parent dan kumpulkan subtask per parent
        $parents  = [];   // parent_id => status
        $children = [];   // parent_id => [status, ...]

        foreach ($all as $t) {
            if ($t['parent_task_id'] === null) {
                $parents[$t['id']] = $t['status'];
            } else {
                $children[(int)$t['parent_task_id']][] = $t['status'];
            }
        }

        if (empty($parents)) return 0.0;

        $statusPct = static function(string $status): float {
            return match($status) {
                'selesai'         => 100.0,
                'sedang_berjalan' => 50.0,
                default           => 0.0,
            };
        };

        $totalPct = 0.0;
        foreach ($parents as $pid => $pStatus) {
            if (!empty($children[$pid])) {
                // Ada subtask → progress task = rata-rata % subtask
                $sum = array_sum(array_map($statusPct, $children[$pid]));
                $totalPct += $sum / count($children[$pid]);
            } else {
                // Tidak ada subtask → progress dari status task itu sendiri
                $totalPct += $statusPct($pStatus);
            }
        }

        return round($totalPct / count($parents), 2);
    }

    public function getNextUrutan(int $kegiatanId, ?int $parentTaskId = null): int
    {
        $builder = $this->db->table('tasks')
                            ->select('COALESCE(MAX(urutan), 0) + 1 AS next_urutan')
                            ->where('kegiatan_id', $kegiatanId);

        if ($parentTaskId) {
            $builder->where('parent_task_id', $parentTaskId);
        } else {
            $builder->where('parent_task_id IS NULL', null, false);
        }

        $row = $builder->get()->getFirstRow('array');
        return (int) ($row['next_urutan'] ?? 1);
    }
}
