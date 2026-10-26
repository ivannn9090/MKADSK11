<?php

namespace App\Models;

use CodeIgniter\Model;

class TaskAttachmentModel extends Model
{
    protected $table         = 'task_attachments';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['task_id', 'nama_file', 'file_url', 'uploaded_by'];
    protected $useTimestamps = false;

    public function getByTask(int $taskId): array
    {
        return $this->select('task_attachments.*, users.nama AS uploaded_by_nama')
                    ->join('users', 'users.id = task_attachments.uploaded_by', 'left')
                    ->where('task_id', $taskId)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }
}
