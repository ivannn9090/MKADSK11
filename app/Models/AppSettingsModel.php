<?php

namespace App\Models;

use CodeIgniter\Model;

class AppSettingsModel extends Model
{
    protected $table         = 'app_settings';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['setting_key', 'setting_value', 'setting_group', 'label', 'description', 'updated_by', 'updated_at'];
    protected $useTimestamps = false;

    /** Cache sederhana per request */
    private array $cache = [];

    /**
     * Ambil nilai satu setting. Jika tidak ditemukan, return $default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $row = $this->where('setting_key', $key)->first();
        $value = ($row !== null && $row['setting_value'] !== null && $row['setting_value'] !== '')
            ? $row['setting_value']
            : $default;

        $this->cache[$key] = $value;

        return $value;
    }

    /**
     * Set / update nilai satu setting.
     * Dinamai setValue() agar tidak konflik dengan signature Model::set().
     */
    public function setValue(string $key, mixed $value, int $userId = 0): void
    {
        unset($this->cache[$key]);

        $existing = $this->where('setting_key', $key)->first();
        $data = [
            'setting_value' => (string) $value,
            'updated_at'    => date('Y-m-d H:i:s'),
            'updated_by'    => $userId ?: null,
        ];

        if ($existing) {
            $this->update($existing['id'], $data);
        } else {
            $this->insert(array_merge(['setting_key' => $key], $data));
        }
    }

    /**
     * Return array key=>value untuk semua setting dalam group tertentu.
     */
    public function getGroup(string $group): array
    {
        $rows = $this->where('setting_group', $group)->findAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    }

    /**
     * Return semua record app_settings.
     */
    public function getAll(): array
    {
        return $this->orderBy('setting_group')->orderBy('id')->findAll();
    }
}
