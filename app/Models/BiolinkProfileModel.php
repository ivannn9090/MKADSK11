<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * BiolinkProfileModel
 *
 * Setiap "profil" adalah satu CARD dengan slug-nya sendiri.
 * Satu user dapat memiliki BANYAK card.
 *
 * Contoh:
 *   Card "Kegiatan A"  → /biolink/kegiatan-a  → link 1, link 2, link 3
 *   Card "Rapat 2025"  → /biolink/rapat-2025  → link 1, link 2
 */
class BiolinkProfileModel extends Model
{
    protected $table         = 'biolink_profiles';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    protected $allowedFields = [
        'user_id',
        'slug',
        'nama',
        'bio',
        'foto_path',
        'cover_path',
        'bg_path',
        'warna_aksen',
        'sosmed_json',
        'is_active',
    ];

    protected $validationRules = [
        'id'          => 'permit_empty|is_natural_no_zero',
        'user_id'     => 'required|is_natural_no_zero',
        'slug'        => 'required|min_length[3]|max_length[80]'
                       . '|is_unique[biolink_profiles.slug,id,{id}]'
                       . '|regex_match[/^[a-zA-Z0-9_-]+$/]',
        'nama'        => 'required|max_length[255]',
        'warna_aksen' => 'permit_empty|regex_match[/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/]',
    ];

    protected $validationMessages = [
        'slug' => [
            'is_unique'   => 'Slug sudah digunakan, coba yang lain.',
            'regex_match' => 'Slug hanya boleh berisi huruf, angka, tanda hubung, dan underscore.',
        ],
    ];

    // -------------------------------------------------------------------------

    /**
     * Semua card milik satu user beserta jumlah item per card.
     */
    public function getAllByUserId(int $userId): array
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('biolink_profiles bp')
                   ->select('bp.*, COUNT(bi.id) AS total_item')
                   ->join('biolink_items bi', 'bi.profile_id = bp.id', 'left')
                   ->where('bp.user_id', $userId)
                   ->groupBy('bp.id')
                   ->orderBy('bp.created_at', 'DESC')
                   ->get()->getResultArray();

        foreach ($rows as &$r) {
            $r['sosmed_json'] = $r['sosmed_json']
                ? json_decode($r['sosmed_json'], true) : [];
            $r['foto_url']  = !empty($r['foto_path'])
                ? base_url('biolink-foto/' . $r['foto_path']) : null;
            $r['cover_url'] = !empty($r['cover_path'])
                ? base_url('biolink-foto/' . $r['cover_path']) : null;
            $r['bg_url']    = !empty($r['bg_path'])
                ? base_url('biolink-foto/' . $r['bg_path']) : null;
            $r['publik_url']  = base_url('biolink/' . $r['slug']);
        }
        return $rows;
    }

    /**
     * Ambil satu card milik user — verifikasi kepemilikan.
     */
    public function getOwnedById(int $cardId, int $userId, string $role = ''): ?array
    {
        $row = $this->find($cardId);
        if (!$row) return null;
        if ((int)$row['user_id'] !== $userId && $role !== 'admin') return null;
        $row['sosmed_json'] = $row['sosmed_json']
            ? json_decode($row['sosmed_json'], true) : [];
        return $row;
    }

    /**
     * Halaman publik: ambil card aktif beserta link-link aktifnya.
     */
    public function getPublicBySlug(string $slug): ?array
    {
        $card = $this->where('slug', $slug)->where('is_active', 1)->first();
        if (!$card) return null;

        $card['sosmed_json'] = $card['sosmed_json']
            ? json_decode($card['sosmed_json'], true) : [];
        $card['foto_url']  = !empty($card['foto_path'])
            ? base_url('biolink-foto/' . $card['foto_path']) : null;
        $card['cover_url'] = !empty($card['cover_path'])
            ? base_url('biolink-foto/' . $card['cover_path']) : null;
        $card['bg_url']    = !empty($card['bg_path'])
            ? base_url('biolink-foto/' . $card['bg_path']) : null;

        $db    = \Config\Database::connect();
        $items = $db->table('biolink_items')
                    ->select('biolink_items.*, short_links.slug AS short_slug')
                    ->join('short_links', 'short_links.id = biolink_items.short_link_id', 'left')
                    ->where('biolink_items.profile_id', $card['id'])
                    ->where('biolink_items.is_active', 1)
                    ->orderBy('biolink_items.urutan', 'ASC')
                    ->orderBy('biolink_items.id', 'ASC')
                    ->get()->getResultArray();

        $card['items'] = $items;
        return $card;
    }

    /** Generate slug unik dari nama. */
    public function generateSlug(string $nama): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $nama));
        $base = trim($base, '-') ?: 'card';
        $base = substr($base, 0, 70);
        $slug = $base;
        $i    = 2;
        while ($this->where('slug', $slug)->first() !== null) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
