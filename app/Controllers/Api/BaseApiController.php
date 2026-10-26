<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AuditLogModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Base class for all API controllers.
 * Provides JWT payload, RBAC helpers, and unified JSON responses.
 */
abstract class BaseApiController extends BaseController
{
    protected array $jwtPayload = [];
    protected \CodeIgniter\Database\BaseConnection $db;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        // Database connection — available to all child controllers via $this->db
        $this->db = \Config\Database::connect();

        // JWT payload is set by JwtAuthFilter
        $this->jwtPayload = $request->jwtPayload ?? [];
    }

    protected function userId(): int
    {
        return (int) ($this->jwtPayload['sub'] ?? 0);
    }

    /** Role utama dari JWT (untuk backward-compat requireRole) */
    protected function userRole(): string
    {
        return $this->jwtPayload['role'] ?? '';
    }

    /** Semua slug role yang dimiliki user (dari tabel user_roles) */
    protected function userRoles(): array
    {
        static $cache = [];
        $uid = $this->userId();
        if (!$uid) return [];
        if (!isset($cache[$uid])) {
            // Admin selalu punya role admin
            if ($this->userRole() === 'admin') {
                $cache[$uid] = ['admin'];
            } else {
                $rows = $this->db->table('user_roles')
                                 ->select('role_slug')
                                 ->where('user_id', $uid)
                                 ->get()->getResultArray();
                $cache[$uid] = array_column($rows, 'role_slug');
                // Fallback ke JWT role jika tabel kosong (misal user lama)
                if (empty($cache[$uid]) && $this->userRole()) {
                    $cache[$uid] = [$this->userRole()];
                }
            }
        }
        return $cache[$uid];
    }

    protected function requireRole(array $roles): void
    {
        // Admin melewati semua pembatasan role
        if ($this->userRole() === 'admin') return;

        // Lolos jika salah satu role user ada di daftar yang diizinkan
        foreach ($this->userRoles() as $r) {
            if (in_array($r, $roles, true)) return;
        }

        $this->response->setStatusCode(403)
                       ->setJSON(['status' => false, 'message' => 'Akses tidak diizinkan untuk role Anda.'])
                       ->send();
        exit;
    }

    /**
     * Cek apakah user memiliki permission — union dari semua role yang dimiliki.
     * Admin selalu lolos.
     */
    protected function hasPermission(string $permission): bool
    {
        if ($this->userRole() === 'admin') return true;

        $roles = $this->userRoles();
        if (empty($roles)) return false;

        $count = $this->db->table('role_permissions')
                          ->whereIn('role_slug', $roles)
                          ->where('permission', $permission)
                          ->countAllResults();
        return $count > 0;
    }

    /**
     * Blokir akses jika tidak punya permission.
     */
    protected function requirePermission(string $permission): void
    {
        if (! $this->hasPermission($permission)) {
            $this->response->setStatusCode(403)
                           ->setJSON(['status' => false, 'message' => 'Akses tidak diizinkan.'])
                           ->send();
            exit;
        }
    }

    /**
     * Load permissions dari semua role yang dimiliki user (union).
     * Dipakai oleh AuthController untuk response login/me.
     */
    protected function _loadPermissions(string $role, ?int $userId = null): array
    {
        if ($role === 'admin') {
            return array_keys(\App\Database\Seeds\RolesSeeder::ALL_PERMISSIONS);
        }

        // Kumpulkan semua role user (dari user_roles jika ada userId)
        $slugs = [$role];
        if ($userId) {
            $rows = $this->db->table('user_roles')
                             ->select('role_slug')
                             ->where('user_id', $userId)
                             ->get()->getResultArray();
            $fromTable = array_column($rows, 'role_slug');
            if (!empty($fromTable)) {
                $slugs = $fromTable;
            }
        }

        if (empty($slugs)) return [];

        $rows = $this->db->table('role_permissions')
                         ->select('permission')
                         ->whereIn('role_slug', $slugs)
                         ->get()->getResultArray();

        // Deduplikasi
        return array_values(array_unique(array_column($rows, 'permission')));
    }

    protected function success(mixed $data = null, string $message = 'Berhasil', int $code = 200): ResponseInterface
    {
        $payload = ['status' => true, 'message' => $message];
        if ($data !== null) $payload['data'] = $data;

        return $this->response->setStatusCode($code)->setJSON($payload);
    }

    protected function fail(string $message, int $code = 400): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON(['status' => false, 'message' => $message]);
    }

    protected function auditLog(string $aktivitas, string $tabel = '', ?int $recordId = null, mixed $before = null, mixed $after = null): void
    {
        if ($this->userId()) {
            (new AuditLogModel())->log($this->userId(), $aktivitas, $tabel, $recordId, $before, $after);
        }
    }
}
