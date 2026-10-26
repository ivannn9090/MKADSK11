<?php

namespace App\Models;

use CodeIgniter\Model;

class ShortLinkModel extends Model
{
    protected $table         = 'short_links';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'user_id',
        'long_url',
        'slug',
        'title',
        'total_klik',
        'qr_path',
    ];

    protected $validationRules = [
        'long_url' => 'required|valid_url_strict',
        'slug'     => 'required|min_length[3]|max_length[64]|is_unique[short_links.slug,id,{id}]|regex_match[/^[a-zA-Z0-9_-]+$/]',
        'user_id'  => 'required|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'slug' => [
            'is_unique'   => 'Slug sudah digunakan, coba yang lain.',
            'regex_match' => 'Slug hanya boleh berisi huruf, angka, tanda hubung, dan underscore.',
        ],
    ];

    // -------------------------------------------------------------------------
    // Query helpers
    // -------------------------------------------------------------------------

    /**
     * Ambil semua link milik satu user beserta statistik dasar.
     */
    public function getByUser(int $userId): array
    {
        return $this->where('user_id', $userId)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }

    /**
     * Ambil link berdasarkan slug (untuk redirect).
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->first();
    }

    /**
     * Increment total_klik +1 secara atomic.
     */
    public function incrementKlik(int $id): void
    {
        $this->set('total_klik', 'total_klik + 1', false)
             ->where('id', $id)
             ->update();
    }

    /**
     * Catat detail akses ke tabel log.
     */
    public function logAccess(int $shortLinkId, string $ip, string $userAgent, string $referer): void
    {
        $db = \Config\Database::connect();
        $db->table('short_link_logs')->insert([
            'short_link_id' => $shortLinkId,
            'ip_address'    => $ip,
            'user_agent'    => mb_substr($userAgent, 0, 512),
            'referer'       => mb_substr($referer, 0, 512),
            'accessed_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Ambil log akses untuk satu link (pagination-ready).
     */
    public function getAccessLogs(int $shortLinkId, int $limit = 50, int $offset = 0): array
    {
        $db = \Config\Database::connect();
        return $db->table('short_link_logs')
                  ->where('short_link_id', $shortLinkId)
                  ->orderBy('accessed_at', 'DESC')
                  ->limit($limit, $offset)
                  ->get()
                  ->getResultArray();
    }

    /**
     * Buat slug acak unik sepanjang $length karakter.
     */
    public function generateUniqueSlug(int $length = 7): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $max   = strlen($chars) - 1;
        do {
            $slug = '';
            for ($i = 0; $i < $length; $i++) {
                $slug .= $chars[random_int(0, $max)];
            }
        } while ($this->findBySlug($slug) !== null);

        return $slug;
    }
}
