<?php

namespace App\Controllers\Api;

use App\Libraries\EmailService;
use App\Models\AppSettingsModel;
use App\Models\UserModel;

/**
 * AdminController — Pengaturan aplikasi & manajemen pengguna.
 * Semua endpoint hanya bisa diakses role 'admin'.
 */
class AdminController extends BaseApiController
{
    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/admin/stats
    // ──────────────────────────────────────────────────────────────
    public function stats()
    {
        $this->requireRole(['admin']);

        $db    = \Config\Database::connect();
        $users = (new UserModel())->select('role, COUNT(*) as total')
                                  ->groupBy('role')
                                  ->findAll();

        $byRole = [];
        foreach ($users as $u) {
            $byRole[$u['role']] = (int) $u['total'];
        }

        $totalUsers = array_sum($byRole);

        return $this->success([
            'total_users' => $totalUsers,
            'by_role'     => $byRole,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/admin/settings
    // ──────────────────────────────────────────────────────────────
    public function settingsIndex()
    {
        $this->requireRole(['admin']);

        $model    = new AppSettingsModel();
        $settings = $model->getAll();

        // Sembunyikan nilai password dari response langsung
        foreach ($settings as &$s) {
            if (in_array($s['setting_key'], ['smtp_pass'], true) && !empty($s['setting_value'])) {
                $s['setting_value'] = '••••••••';
                $s['masked']        = true;
            }
        }
        unset($s);

        return $this->success(['items' => $settings]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/admin/settings
    // Body: { "settings": { "key": "value", ... } }
    // ──────────────────────────────────────────────────────────────
    public function settingsUpdate()
    {
        $this->requireRole(['admin']);

        $data     = $this->request->getJSON(true) ?? [];
        $incoming = $data['settings'] ?? [];

        if (empty($incoming) || !is_array($incoming)) {
            return $this->fail('Tidak ada data settings yang dikirim.');
        }

        $model  = new AppSettingsModel();
        $userId = $this->userId();

        // Whitelist setting yang boleh diubah lewat API — mencegah perubahan key arbitrer
        $allowedKeys = [
            'app_name', 'app_url',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass',
            'smtp_crypto', 'smtp_from_email', 'smtp_from_name',
            'registration_enabled', 'logo_path',
        ];

        $rejected = array_diff(array_keys($incoming), $allowedKeys);
        if (!empty($rejected)) {
            return $this->fail('Pengaturan tidak diizinkan: ' . implode(', ', $rejected), 422);
        }

        foreach ($incoming as $key => $value) {
            // Jangan overwrite smtp_pass jika dikirim masked
            if ($key === 'smtp_pass' && $value === '••••••••') continue;
            $model->setValue((string)$key, (string)$value, $userId);
        }

        $this->auditLog('Update Settings', 'app_settings', null, null, array_keys($incoming));

        return $this->success(null, 'Pengaturan berhasil disimpan.');
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/admin/test-email
    // Body: { "to_email": "...", "to_name": "..." }
    // ──────────────────────────────────────────────────────────────
    public function testEmail()
    {
        $this->requireRole(['admin']);

        $data    = $this->request->getJSON(true) ?? [];
        $toEmail = trim($data['to_email'] ?? '');
        $toName  = trim($data['to_name']  ?? 'Test');

        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Alamat email tujuan tidak valid.');
        }

        $emailService = new EmailService();
        $appName      = (new AppSettingsModel())->get('app_name', 'MKA System');

        // Kirim ke alamat yang diminta (bukan ke fromEmail)
        try {
            $config = $this->_buildEmailConfig();
            $email  = \Config\Services::email();
            $email->initialize($config);
            $email->setFrom($config['fromEmail'], $config['fromName']);
            $email->setTo($toEmail, $toName);
            $appNameSafe = htmlspecialchars($appName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $email->setSubject("[{$appNameSafe}] Test Koneksi Email SMTP");
            $email->setMessage("
                <html><body style='font-family:sans-serif;color:#1f2328;'>
                <h2 style='color:#1e40af;'>✅ Koneksi Email Berhasil</h2>
                <p>Email ini dikirim sebagai tes konfigurasi SMTP dari <b>{$appNameSafe}</b>.</p>
                <p>Jika Anda menerima email ini, konfigurasi SMTP sudah benar.</p>
                <hr style='border:1px solid #e5e7eb;margin:16px 0;'>
                <p style='color:#57606a;font-size:12px;'>Dikirim pada: " . date('d M Y H:i:s') . "</p>
                </body></html>
            ");
            $ok = $email->send(false);
        } catch (\Throwable $e) {
            return $this->fail('Gagal mengirim email: ' . $e->getMessage());
        }

        if (!$ok) {
            return $this->fail('Email gagal terkirim. Periksa konfigurasi SMTP.');
        }

        return $this->success(null, "Email test berhasil dikirim ke {$toEmail}.");
    }

    // ──────────────────────────────────────────────────────────────
    // Users CRUD (admin dapat kelola semua role termasuk admin)
    // ──────────────────────────────────────────────────────────────

    // GET /api/v1/admin/users
    public function usersIndex()
    {
        $this->requireRole(['admin']);

        $model = new UserModel();
        $role  = $this->request->getGet('role');
        $q     = trim($this->request->getGet('q') ?? '');

        $query = $model->select('id, nama, email, jabatan, role, status, must_change_password, created_at');

        if ($role) $query->where('role', $role);
        if ($q)    $query->groupStart()
                         ->like('nama', $q)
                         ->orLike('email', $q)
                         ->groupEnd();

        return $this->success(['items' => $query->orderBy('nama')->findAll()]);
    }

    // POST /api/v1/admin/users
    public function usersCreate()
    {
        $this->requireRole(['admin']);

        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'nama'     => 'required|max_length[150]',
            'email'    => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[8]',
            'role'     => 'required|in_list[direktur,ketua_tim,anggota_tim,perencana_anggaran,admin,tu]',
        ];

        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $model = new UserModel();
        $model->insert([
            'nama'                 => $data['nama'],
            'email'                => $data['email'],
            'password'             => password_hash($data['password'], PASSWORD_BCRYPT),
            'jabatan'              => $data['jabatan'] ?? null,
            'role'                 => $data['role'],
            'status'               => $data['status'] ?? 'aktif',
            'must_change_password' => isset($data['must_change_password']) && $data['must_change_password'] ? 1 : 0,
        ]);
        $newId = $model->insertID();

        $this->auditLog("Admin buat user: {$data['nama']}", 'users', $newId, null, [
            'nama' => $data['nama'], 'role' => $data['role'],
        ]);

        return $this->success(['id' => $newId], 'User berhasil dibuat.', 201);
    }

    // PUT /api/v1/admin/users/:id
    public function usersUpdate(int $id)
    {
        $this->requireRole(['admin']);

        $model = new UserModel();
        $user  = $model->find($id);
        if (!$user) return $this->fail('User tidak ditemukan.', 404);

        // Admin tidak bisa downgrade akun admin lain kecuali dirinya sendiri
        if ($user['role'] === 'admin' && $id !== $this->userId()) {
            $data = $this->request->getJSON(true) ?? [];
            if (isset($data['role']) && $data['role'] !== 'admin') {
                return $this->fail('Tidak dapat mengubah role admin lain.', 403);
            }
        }

        $data    = $this->request->getJSON(true) ?? [];
        $allowed = ['nama', 'email', 'jabatan', 'role', 'status', 'must_change_password'];
        $update  = array_intersect_key($data, array_flip($allowed));

        // Normalisasi must_change_password ke integer 0/1
        if (isset($update['must_change_password'])) {
            $update['must_change_password'] = $update['must_change_password'] ? 1 : 0;
        }

        // Validasi role jika diubah
        if (isset($update['role']) &&
            !in_array($update['role'], ['direktur','ketua_tim','anggota_tim','perencana_anggaran','admin','tu'], true)) {
            return $this->fail('Role tidak valid.', 422);
        }

        if (!empty($data['password'])) {
            $update['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (empty($update)) return $this->fail('Tidak ada data untuk diperbarui.', 422);

        $model->update($id, $update);
        $this->auditLog("Admin update user: {$user['nama']}", 'users', $id, $user, $update);

        return $this->success(null, 'User berhasil diperbarui.');
    }

    // DELETE /api/v1/admin/users/:id  — soft delete (status=nonaktif)
    public function usersDelete(int $id)
    {
        $this->requireRole(['admin']);

        // Tidak bisa hapus diri sendiri
        if ($id === $this->userId()) {
            return $this->fail('Tidak dapat menonaktifkan akun sendiri.', 403);
        }

        $model = new UserModel();
        $user  = $model->find($id);
        if (!$user) return $this->fail('User tidak ditemukan.', 404);

        $model->update($id, ['status' => 'nonaktif']);
        $this->auditLog("Admin nonaktifkan user: {$user['nama']}", 'users', $id, $user, ['status' => 'nonaktif']);

        return $this->success(null, "User {$user['nama']} dinonaktifkan.");
    }

    // ──────────────────────────────────────────────────────────────
    // Helper internal — baca config email dari DB
    // ──────────────────────────────────────────────────────────────
    private function _buildEmailConfig(): array
    {
        $s = new AppSettingsModel();
        return [
            'protocol'   => 'smtp',
            'SMTPHost'   => $s->get('smtp_host',       env('email.SMTPHost',   'localhost')),
            'SMTPPort'   => (int) $s->get('smtp_port', env('email.SMTPPort',   587)),
            'SMTPUser'   => $s->get('smtp_user',        env('email.SMTPUser',   '')),
            'SMTPPass'   => $s->get('smtp_pass',        env('email.SMTPPass',   '')),
            'SMTPCrypto' => $s->get('smtp_crypto',      env('email.SMTPCrypto', 'tls')),
            'fromEmail'  => $s->get('smtp_from_email',  env('email.fromEmail',  '')),
            'fromName'   => $s->get('smtp_from_name',   env('email.fromName',   'MKA System')),
            'mailType'   => 'html',
            'charset'    => 'utf-8',
        ];
    }
}
