<?php

namespace App\Models;

use CodeIgniter\Model;

class OtpModel extends Model
{
    protected $table         = 'otp_codes';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['email', 'otp', 'purpose', 'expires_at', 'used', 'created_at'];
    protected $useTimestamps = false;

    /**
     * Buat 8-digit OTP, simpan hash SHA-256 ke DB, return plain OTP.
     * 8 digit = 100 juta kombinasi — jauh lebih sulit di-brute force dari 6 digit.
     */
    public function generate(string $email, string $purpose = 'register'): string
    {
        // Hapus OTP lama untuk email+purpose yang sama
        $this->where('email', $email)->where('purpose', $purpose)->delete();

        // 8 digit: 00000000 – 99999999 (100 juta kombinasi)
        $plain = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

        $this->insert([
            'email'      => $email,
            'otp'        => hash('sha256', $plain),
            'purpose'    => $purpose,
            'expires_at' => date('Y-m-d H:i:s', time() + 600), // 10 menit
            'used'       => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $plain;
    }

    /**
     * Verifikasi OTP — cek valid, belum expired, belum digunakan.
     * Jika valid, tandai used=1.
     * Mencegah brute force: OTP langsung dinonaktifkan setelah satu percobaan gagal.
     */
    public function verify(string $email, string $otp, string $purpose = 'register'): bool
    {
        $record = $this->where('email', $email)
                       ->where('purpose', $purpose)
                       ->where('used', 0)
                       ->where('expires_at >=', date('Y-m-d H:i:s'))
                       ->orderBy('id', 'DESC')
                       ->first();

        if (! $record) {
            return false;
        }

        if (! hash_equals($record['otp'], hash('sha256', $otp))) {
            // Tandai OTP sebagai used setelah percobaan gagal untuk mencegah brute force
            $this->update($record['id'], ['used' => 1]);
            return false;
        }

        $this->update($record['id'], ['used' => 1]);

        return true;
    }

    /**
     * Hapus semua OTP yang sudah expired.
     */
    public function cleanExpired(): void
    {
        $this->where('expires_at <', date('Y-m-d H:i:s'))->delete();
    }
}
