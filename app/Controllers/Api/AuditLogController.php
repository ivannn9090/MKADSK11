<?php

namespace App\Controllers\Api;

use App\Models\AuditLogModel;
use CodeIgniter\HTTP\ResponseInterface;

class AuditLogController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $this->requireRole(['direktur', 'tu']);

        $db      = \Config\Database::connect();
        $page    = max(1, (int) $this->request->getGet('page'));
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;

        $userId = $this->request->getGet('user_id');
        $tabel  = $this->request->getGet('tabel');
        $q      = trim($this->request->getGet('q') ?? '');

        $builder = $db->table('audit_log a')
                      ->select('a.*, u.nama AS user_nama, u.role AS user_role')
                      ->join('users u', 'u.id = a.user_id', 'left')
                      ->orderBy('a.waktu', 'DESC');

        if ($userId) $builder->where('a.user_id', $userId);
        if ($tabel)  $builder->where('a.tabel_terkait', $tabel);
        if ($q)      $builder->groupStart()
                             ->like('a.aktivitas', $q)
                             ->orLike('u.nama', $q)
                             ->groupEnd();

        // Count dengan filter yang sama
        $countBuilder = $db->table('audit_log a')
                           ->join('users u', 'u.id = a.user_id', 'left');
        if ($userId) $countBuilder->where('a.user_id', $userId);
        if ($tabel)  $countBuilder->where('a.tabel_terkait', $tabel);
        if ($q)      $countBuilder->groupStart()
                                  ->like('a.aktivitas', $q)
                                  ->orLike('u.nama', $q)
                                  ->groupEnd();

        $total = $countBuilder->countAllResults();
        $rows  = $builder->limit($perPage, $offset)->get()->getResultArray();

        return $this->success([
            'rows'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }
}
