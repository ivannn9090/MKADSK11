<?php

namespace App\Models\Schedule;

use CodeIgniter\Model;

class ScheduleCategoryModel extends Model
{
    protected $table         = 'schedule_categories';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['nama', 'kode', 'warna', 'is_aktif'];

    public function getAktif(): array
    {
        return $this->where('is_aktif', 1)->orderBy('id')->findAll();
    }
}
