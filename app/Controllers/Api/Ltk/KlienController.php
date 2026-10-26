<?php

namespace App\Controllers\Api\Ltk;

use App\Controllers\Api\BaseApiController;
use App\Models\Ltk\KlienProfilModel;
use App\Models\Ltk\LtkNotifikasiModel;
use App\Models\Ltk\PermohonanModel;
use App\Models\UserModel;

/**
 * KlienController — Registrasi akun Klien dan verifikasi oleh TU.
 */
class KlienController extends BaseApiController
{
    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/ltk/klien/register  (public — tidak butuh JWT)
    // Body: { nama, email, password, unit_kerja, jabatan, no_telepon }
    // ──────────────────────────────────────────────────────────────
    public function register()
    {
        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'nama'       => 'required|max_length[150]',
            'email'      => 'required|valid_email|is_unique[users.email]',
            'password'   => 'required|min_length[8]',
            'unit_kerja' => 'required|max_length[200]',
            'jabatan'    => 'permit_empty|max_length[200]',
            'no_telepon' => 'permit_empty|max_length[20]',
        ];

        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $userModel = new UserModel();
        $userId    = $userModel->insert([
            'nama'     => $data['nama'],
            'email'    => trim(strtolower($data['email'])),
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'jabatan'  => $data['jabatan'] ?? null,
            'role'     => 'klien',
            'status'   => 'aktif',
        ]);
        $newId = (int)$userModel->insertID();

        // Buat profil klien (belum terverifikasi)
        (new KlienProfilModel())->insert([
            'user_id'    => $newId,
            'unit_kerja' => $data['unit_kerja'],
            'jabatan'    => $data['jabatan'] ?? null,
            'no_telepon' => $data['no_telepon'] ?? null,
            'is_verified'=> 0,
        ]);

        // Notifikasi ke semua TU untuk verifikasi
        $this->_notifikasiTuRegistrasiBaru($newId, $data['nama'], $data['unit_kerja']);

        $this->auditLog("Registrasi klien baru: {$data['email']}", 'users', $newId);

        return $this->success(
            ['id' => $newId],
            'Registrasi berhasil. Akun Anda menunggu verifikasi oleh Tata Usaha.',
            201
        );
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/ltk/klien/pending  (TU/Admin — daftar akun belum diverifikasi)
    // ──────────────────────────────────────────────────────────────
    public function pending()
    {
        $this->requireRole(['admin', 'tu']);
        return $this->success(['items' => (new KlienProfilModel())->getDaftarBelumVerifikasi()]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/ltk/klien/:userId/verifikasi  (TU/Admin)
    // ──────────────────────────────────────────────────────────────
    public function verifikasi(int $userId)
    {
        $this->requireRole(['admin', 'tu']);

        $profilModel = new KlienProfilModel();
        $profil      = $profilModel->getByUserId($userId);
        if (!$profil) return $this->fail('Profil klien tidak ditemukan.', 404);
        if ($profil['is_verified']) return $this->fail('Akun sudah terverifikasi.', 422);

        $profilModel->verifikasi($userId, $this->userId());

        // Notifikasi ke Klien
        (new LtkNotifikasiModel())->kirim(
            $userId,
            'akun_terverifikasi',
            'Akun Anda Telah Diverifikasi',
            'Akun Anda telah diverifikasi oleh Tata Usaha. Anda sekarang dapat mengajukan permohonan telaah.',
            null
        );

        $this->auditLog("Verifikasi akun klien user #{$userId}", 'ltk_klien_profil', $profil['id']);

        return $this->success(null, 'Akun klien berhasil diverifikasi.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/ltk/klien/profil  (Klien sendiri)
    // ──────────────────────────────────────────────────────────────
    public function profil()
    {
        $this->requireRole(['klien']);

        $profil = (new KlienProfilModel())->getByUserId($this->userId());
        if (!$profil) return $this->fail('Profil tidak ditemukan.', 404);

        return $this->success($profil);
    }

    // ──────────────────────────────────────────────────────────────
    // Notifikasi in-app ke semua user ber-role TU
    // ──────────────────────────────────────────────────────────────
    private function _notifikasiTuRegistrasiBaru(int $klienId, string $namaKlien, string $unitKerja): void
    {
        $tuUsers = (new UserModel())
            ->select('id')
            ->where('role', 'tu')
            ->where('status', 'aktif')
            ->findAll();

        if (empty($tuUsers)) return;

        $userIds = array_column($tuUsers, 'id');
        (new LtkNotifikasiModel())->kirimBulk(
            $userIds,
            'registrasi_klien',
            'Registrasi Akun Klien Baru',
            "{$namaKlien} dari {$unitKerja} mendaftar sebagai Klien. Silakan lakukan verifikasi akun.",
            null
        );
    }
}
