<?php

namespace App\Models\Ltk;

use CodeIgniter\Model;

class AktivitasLogModel extends Model
{
    protected $table         = 'ltk_aktivitas_log';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['permohonan_id', 'status_dari', 'status_ke', 'label_aktivitas', 'catatan', 'dilakukan_oleh'];

    /**
     * Catat satu entri timeline.
     */
    public function catat(int $permohonanId, string $statusKe, string $label, ?int $userId = null, ?string $catatan = null, ?string $statusDari = null): void
    {
        $this->insert([
            'permohonan_id'   => $permohonanId,
            'status_dari'     => $statusDari,
            'status_ke'       => $statusKe,
            'label_aktivitas' => $label,
            'catatan'         => $catatan,
            'dilakukan_oleh'  => $userId,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Ambil timeline lengkap untuk ditampilkan ke Klien.
     */
    public function getTimeline(int $permohonanId): array
    {
        return $this->select('ltk_aktivitas_log.*, u.nama AS nama_user')
                    ->join('users AS u', 'u.id = ltk_aktivitas_log.dilakukan_oleh', 'left')
                    ->where('permohonan_id', $permohonanId)
                    ->orderBy('created_at', 'ASC')
                    ->findAll();
    }
}
