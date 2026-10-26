<?php

namespace App\Controllers\Api;

use App\Libraries\EmailService;
use App\Libraries\JwtLibrary;
use App\Models\AppSettingsModel;
use App\Models\AuditLogModel;
use App\Models\OtpModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseApiController
{
    public function login(): ResponseInterface
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[8]',
        ];

        if (! $this->validate($rules)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $email    = $this->request->getJSON(true)['email']    ?? $this->request->getPost('email');
        $password = $this->request->getJSON(true)['password'] ?? $this->request->getPost('password');

        $userModel = new UserModel();
        $user      = $userModel->where('email', trim(strtolower($email)))->where('status', 'aktif')->first();

        if (! $user || ! password_verify($password, $user['password'])) {
            return $this->fail('Email atau password salah.', 401);
        }

        // Klien harus sudah diverifikasi TU sebelum bisa login
        if ($user['role'] === 'klien') {
            $profil = $this->db->table('ltk_klien_profil')
                               ->where('user_id', $user['id'])
                               ->get()->getRowArray();
            if (!$profil || ! (int)$profil['is_verified']) {
                return $this->fail(
                    'Akun Anda sedang menunggu verifikasi oleh Tata Usaha. ' .
                    'Silakan hubungi TU Direktorat Sistem Kearsipan.',
                    403
                );
            }
        }

        $jwt     = new JwtLibrary();
        $token   = $jwt->generate([
            'sub'   => $user['id'],
            'nama'  => $user['nama'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);

        (new AuditLogModel())->log($user['id'], 'Login', 'users', $user['id'], null, ['email' => $user['email']]);

        unset($user['password']);
        // Ambil semua role user dari tabel user_roles
        $userRoleRows = $this->db->table('user_roles')
                                 ->select('role_slug')
                                 ->where('user_id', $user['id'])
                                 ->get()->getResultArray();
        $user['roles']       = !empty($userRoleRows)
                                 ? array_column($userRoleRows, 'role_slug')
                                 : [$user['role']];
        $user['permissions'] = $this->_loadPermissions($user['role'], $user['id']);
        $user['posisi']      = $this->_loadUserPosisi($user['id']);

        return $this->success([
            'token' => $token,
            'user'  => $user,
        ], 'Login berhasil');
    }

    public function me(): ResponseInterface
    {
        $userModel = new UserModel();
        $user      = $userModel->select('id, nama, email, jabatan, role, status, must_change_password')->find($this->userId());

        if (! $user) {
            return $this->fail('User tidak ditemukan.', 404);
        }

        // Ambil semua role user dari tabel user_roles
        $userRoleRows = $this->db->table('user_roles')
                                 ->select('role_slug')
                                 ->where('user_id', $user['id'])
                                 ->get()->getResultArray();
        $user['roles']       = !empty($userRoleRows)
                                 ? array_column($userRoleRows, 'role_slug')
                                 : [$user['role']];
        $user['permissions'] = $this->_loadPermissions($user['role'], $user['id']);
        $user['posisi']      = $this->_loadUserPosisi($user['id']);

        return $this->success($user);
    }

    /** Ambil daftar posisi tim user, diurutkan by urutan ASC */
    private function _loadUserPosisi(int $userId): array
    {
        $rows = $this->db->table('user_posisi_tim up')
                         ->select('pt.id, pt.nama, pt.urutan')
                         ->join('posisi_tim pt', 'pt.id = up.posisi_tim_id')
                         ->where('up.user_id', $userId)
                         ->orderBy('pt.urutan', 'ASC')
                         ->get()->getResultArray();
        return $rows ?: [];
    }

    public function logout(): ResponseInterface
    {
        // Revoke token agar tidak dapat digunakan kembali setelah logout
        $jwt      = new JwtLibrary();
        $rawToken = $jwt->getRawTokenFromRequest($this->request);

        if ($rawToken) {
            $exp = $this->jwtPayload['exp'] ?? (time() + 86400);
            (new \App\Models\RevokedTokenModel())->revoke($rawToken, $this->userId(), $exp);
        }

        $this->auditLog('Logout', 'users', $this->userId());

        return $this->success(null, 'Logout berhasil');
    }

    // ---------------------------------------------------------------
    // Update Profile — PUT auth/profile
    // ---------------------------------------------------------------
    public function updateProfile(): ResponseInterface
    {
        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'nama'    => 'required|max_length[150]',
            'jabatan' => 'permit_empty|max_length[200]',
            'email'   => 'required|valid_email|max_length[150]',
        ];

        if (! $this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $userId    = $this->userId();
        $userModel = new UserModel();

        // Cek email unik — kecuali milik user sendiri
        $existing = $userModel->where('email', trim(strtolower($data['email'])))
                              ->where('id !=', $userId)
                              ->first();
        if ($existing) {
            return $this->fail('Email sudah digunakan oleh akun lain.', 422);
        }

        $updateData = [
            'nama'    => $data['nama'],
            'jabatan' => $data['jabatan'] ?? null,
            'email'   => trim(strtolower($data['email'])),
        ];

        $before = $userModel->select('id, nama, email, jabatan, role, status')->find($userId);
        $userModel->update($userId, $updateData);

        $this->auditLog('Update Profile', 'users', $userId, $before, $updateData);

        $updated = $userModel->select('id, nama, email, jabatan, role, status')->find($userId);

        return $this->success($updated, 'Profil berhasil diperbarui.');
    }

    // ---------------------------------------------------------------
    // Change Password — POST auth/change-password
    // ---------------------------------------------------------------
    public function changePassword(): ResponseInterface
    {
        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'current_password'      => 'required',
            'new_password'          => 'required|min_length[8]',
            'new_password_confirm'  => 'required|matches[new_password]',
        ];

        if (! $this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $userId    = $this->userId();
        $userModel = new UserModel();

        // Ambil dengan password untuk verifikasi
        $user = $userModel->select('id, password')->find($userId);
        if (! $user || ! password_verify($data['current_password'], $user['password'])) {
            return $this->fail('Password saat ini tidak sesuai.', 422);
        }

        $userModel->update($userId, [
            'password'             => password_hash($data['new_password'], PASSWORD_BCRYPT),
            'must_change_password' => 0,
        ]);

        $this->auditLog('Change Password', 'users', $userId);

        return $this->success(null, 'Password berhasil diubah.');
    }

    // ---------------------------------------------------------------
    // Registrasi — Step 1: validasi & kirim OTP (belum simpan user)
    // ---------------------------------------------------------------
    public function register(): ResponseInterface
    {
        // Cek apakah registrasi diaktifkan
        $settingsModel = new AppSettingsModel();
        if ($settingsModel->get('registration_enabled', '0') !== '1') {
            return $this->fail('Registrasi akun saat ini tidak tersedia.', 403);
        }

        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'nama'              => 'required|max_length[150]',
            'email'             => 'required|valid_email|max_length[150]',
            'password'          => 'required|min_length[8]',
            'password_confirm'  => 'required|matches[password]',
        ];

        if (! $this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $email = trim(strtolower($data['email']));

        // Cek email sudah terdaftar
        $userModel = new UserModel();
        if ($userModel->where('email', $email)->first()) {
            return $this->fail('Email sudah terdaftar.', 422);
        }

        // Buat & kirim OTP
        $otpModel = new OtpModel();
        $otp      = $otpModel->generate($email, 'register');

        $emailService = new EmailService();
        $sent = $emailService->sendOtp($email, $data['nama'], $otp, 'register');

        if (! $sent) {
            return $this->fail('Gagal mengirim email OTP. Pastikan konfigurasi SMTP sudah benar.', 500);
        }

        return $this->success(
            ['email' => $email],
            'Kode OTP telah dikirim ke email Anda. Berlaku 10 menit.',
            200
        );
    }

    // ---------------------------------------------------------------
    // Kirim Ulang OTP
    // ---------------------------------------------------------------
    public function sendOtp(): ResponseInterface
    {
        $settingsModel = new AppSettingsModel();
        if ($settingsModel->get('registration_enabled', '0') !== '1') {
            return $this->fail('Registrasi akun saat ini tidak tersedia.', 403);
        }

        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'email'   => 'required|valid_email',
            'purpose' => 'permit_empty|in_list[register,reset_password]',
        ];

        if (! $this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $email   = trim(strtolower($data['email']));
        $purpose = $data['purpose'] ?? 'register';

        // Untuk registrasi: cek email belum terdaftar
        if ($purpose === 'register') {
            $userModel = new UserModel();
            if ($userModel->where('email', $email)->first()) {
                return $this->fail('Email sudah terdaftar.', 422);
            }
        }

        $otpModel = new OtpModel();
        $otp      = $otpModel->generate($email, $purpose);

        $toName = $data['nama'] ?? 'Pengguna';
        $emailService = new EmailService();
        $sent = $emailService->sendOtp($email, $toName, $otp, $purpose);

        if (! $sent) {
            return $this->fail('Gagal mengirim email OTP.', 500);
        }

        return $this->success(null, 'Kode OTP berhasil dikirim ulang.');
    }

    // ---------------------------------------------------------------
    // Verifikasi OTP — Step 2: verifikasi OTP, simpan user, return JWT
    // ---------------------------------------------------------------
    public function verifyOtp(): ResponseInterface
    {
        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'email'    => 'required|valid_email',
            'otp'      => 'required|exact_length[8]',
            'nama'     => 'required|max_length[150]',
            'password' => 'required|min_length[8]',
        ];

        if (! $this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $email = trim(strtolower($data['email']));

        $otpModel = new OtpModel();
        if (! $otpModel->verify($email, $data['otp'], 'register')) {
            return $this->fail('Kode OTP tidak valid atau sudah kadaluarsa.', 422);
        }

        // Simpan user
        $userModel = new UserModel();

        // Double-check email unik (race condition guard)
        if ($userModel->where('email', $email)->first()) {
            return $this->fail('Email sudah terdaftar.', 422);
        }

        $insertData = [
            'nama'     => $data['nama'],
            'email'    => $email,
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'     => 'anggota_tim',
            'status'   => 'aktif',
        ];

        $userModel->insert($insertData);
        $newId = $userModel->insertID();

        $newUser = $userModel->select('id, nama, email, jabatan, role, status')->find($newId);

        // Kirim welcome email (fire & forget)
        try {
            (new EmailService())->sendWelcome($email, $data['nama']);
        } catch (\Throwable $e) {
            log_message('warning', '[AuthController::verifyOtp] Welcome email gagal: ' . $e->getMessage());
        }

        (new AuditLogModel())->log($newId, 'Register', 'users', $newId, null, ['email' => $email]);

        // Generate JWT
        $jwt   = new JwtLibrary();
        $token = $jwt->generate([
            'sub'   => $newUser['id'],
            'nama'  => $newUser['nama'],
            'email' => $newUser['email'],
            'role'  => $newUser['role'],
        ]);

        return $this->success([
            'token' => $token,
            'user'  => $newUser,
        ], 'Registrasi berhasil. Selamat datang!', 201);
    }
}
