<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * BiolinkItemModel
 *
 * Mengelola tabel biolink_items.
 * Satu profil dapat memiliki banyak item/tombol tautan.
 */
class BiolinkItemModel extends Model
{
    protected $table         = 'biolink_items';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    protected $allowedFields = [
        'profile_id',
        'short_link_id',
        'judul',
        'url_tujuan',
        'ikon',
        'urutan',
        'is_active',
    ];

    protected $validationRules = [
        'profile_id' => 'required|is_natural_no_zero',
        'judul'      => 'required|max_length[255]',
        'url_tujuan' => 'required|valid_url_strict|max_length[2048]',
    ];

    // -------------------------------------------------------------------------

    /** Semua item milik satu profil (termasuk non-aktif, untuk admin). */
    public function getByProfile(int $profileId): array
    {
        return $this->where('profile_id', $profileId)
                    ->orderBy('urutan', 'ASC')
                    ->orderBy('id', 'ASC')
                    ->findAll();
    }

    /**
     * Reorder massal: $order = [ ['id' => 3, 'urutan' => 1], ... ]
     * Menggunakan single query per item agar aman.
     */
    public function reorder(int $profileId, array $order): void
    {
        $db = \Config\Database::connect();
        foreach ($order as $entry) {
            $db->table($this->table)
               ->where('id', (int) $entry['id'])
               ->where('profile_id', $profileId)
               ->update(['urutan' => (int) $entry['urutan']]);
        }
    }
}
