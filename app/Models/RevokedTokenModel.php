<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * RevokedTokenModel — mencatat JWT yang di-revoke saat logout.
 *
 * Token disimpan sebagai SHA-256 hash — tidak pernah raw token.
 * Cleanup otomatis: entri expired dihapus saat pengecekan.
 */
class RevokedTokenModel extends Model
{
    protected $table         = 'revoked_tokens';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['token_hash', 'user_id', 'expires_at', 'revoked_at'];
    protected $useTimestamps = false;

    /**
     * Revoke token — simpan hash ke database.
     */
    public function revoke(string $token, int $userId, int $expiresAt): void
    {
        $hash = hash('sha256', $token);

        // Upsert — jika hash sudah ada (double logout), lewati
        $existing = $this->where('token_hash', $hash)->first();
        if ($existing) return;

        $this->insert([
            'token_hash' => $hash,
            'user_id'    => $userId,
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            'revoked_at' => date('Y-m-d H:i:s'),
        ]);

        // Bersihkan token yang sudah expired (cleanup pasif)
        $this->cleanExpired();
    }

    /**
     * Cek apakah token sudah di-revoke.
     */
    public function isRevoked(string $token): bool
    {
        $hash = hash('sha256', $token);
        return (bool) $this->where('token_hash', $hash)->first();
    }

    /**
     * Hapus entri token yang sudah expired — tidak perlu cron job.
     */
    public function cleanExpired(): void
    {
        $this->where('expires_at <', date('Y-m-d H:i:s'))->delete();
    }
}
