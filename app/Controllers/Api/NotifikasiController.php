<?php

namespace App\Controllers\Api;

use App\Models\NotifikasiModel;
use CodeIgniter\HTTP\ResponseInterface;

class NotifikasiController extends BaseApiController
{
    private NotifikasiModel $model;

    public function __construct()
    {
        $this->model = new NotifikasiModel();
    }

    public function index(): ResponseInterface
    {
        $items       = $this->model->getByUser($this->userId());
        $unreadCount = $this->model->countUnread($this->userId());

        return $this->success(['items' => $items, 'unread_count' => $unreadCount]);
    }

    public function markRead(int $id): ResponseInterface
    {
        $this->model->where('id', $id)->where('user_id', $this->userId())->set(['is_read' => 1])->update();

        return $this->success(null, 'Notifikasi ditandai sudah dibaca.');
    }

    public function markAllRead(): ResponseInterface
    {
        $this->model->where('user_id', $this->userId())->set(['is_read' => 1])->update();

        return $this->success(null, 'Semua notifikasi sudah dibaca.');
    }
}
